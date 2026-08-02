<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Exceptions\AppError;
use App\Services\RealtimeService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected RealtimeService $realtimeService
    ) {}

    public function init(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['subject' => 'nullable|string|max:255', 'message' => 'nullable|string']);

            // Resume the user's existing non-terminal chat ticket when present so the
            // conversation persists in one place (My Tickets + admin window) instead of
            // fragmenting into a new "Chat Support" ticket on every widget open.
            // Chat tickets are identified by their CHAT- ticket-number prefix; terminal
            // statuses (RESOLVED / CLOSED) start a fresh conversation instead.
            $existing = SupportTicket::with('user:id,first_name,last_name,email')
                ->where('user_id', $request->user()->id)
                ->where('ticket_number', 'like', 'CHAT-%')
                ->whereIn('status', ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER'])
                ->latest()
                ->first();

            if ($existing) {
                // Mirror the create path: an opening message sent with init is persisted.
                if (!empty($validated['message'])) {
                    $this->ticketService->addMessage($existing->id, $validated['message'], $request->user()->id);
                }

                $ticket = $existing->toArray();
                $ticket['messages'] = TicketMessage::where('ticket_id', $ticket['id'])->orderBy('created_at')->get();
                return response()->json(['success' => true, 'data' => $ticket]);
            }

            // Reuse TicketService so priority/status defaults and category validation live in one place.
            $ticket = $this->ticketService->create($request->user()->id, [
                'ticket_number' => 'CHAT-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'subject' => $validated['subject'] ?? 'Chat Support',
                'description' => $validated['message'] ?? 'Chat initiated by user',
                // Only creates an initial message row when the user actually typed something.
                'message' => $validated['message'] ?? null,
                'category' => 'OTHER',
            ]);

            // Preserve the previous response shape: ticket with its messages loaded.
            $ticket['messages'] = TicketMessage::where('ticket_id', $ticket['id'])->orderBy('created_at')->get();

            return response()->json(['success' => true, 'data' => $ticket]);
        } catch (AppError $e) { return $e->render(); }
        // Let framework exceptions (validation 422, auth 401, etc.) reach Laravel's handler.
        catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { throw $e; }
        catch (\Exception $e) {
            Log::error('[ChatController] Failed to create chat: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create chat. Please try again later.'], 500);
        }
    }

    public function sendMessage(Request $request, string $ticketId): JsonResponse
    {
        try {
            $validated = $request->validate(['content' => 'required|string']);
            $ticket = SupportTicket::where('id', $ticketId)->where('user_id', $request->user()->id)->firstOrFail();
            $msg = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $request->user()->id,
                'content' => $validated['content'],
                'is_from_admin' => false,
            ]);

            // Broadcast so the admin's open chat window + badge update live.
            $this->realtimeService->emitChatMessage([
                'ticketId' => $ticket->id,
                'userId' => $ticket->user_id,
                'message' => $msg->toArray(),
                'isAdmin' => false,
            ]);

            return response()->json(['success' => true, 'data' => $msg]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Ticket not found'], 404); }
    }

    public function sendTyping(Request $request, string $ticketId): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Typing indicator sent']);
    }

    public function getMessages(Request $request, string $ticketId): JsonResponse
    {
        try {
            $ticket = SupportTicket::where('id', $ticketId)->where('user_id', $request->user()->id)->firstOrFail();
            return response()->json(['success' => true, 'data' => $ticket->messages()->orderBy('created_at')->get()]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Ticket not found'], 404); }
    }

    public function getAdminConversations(Request $request): JsonResponse
    {
        // Include WAITING_CUSTOMER so resumed chats stay visible in the admin window
        // even before the admin replies (a customer who reopens a chat resumes an
        // OPEN/IN_PROGRESS/WAITING_CUSTOMER ticket — see init()).
        $tickets = SupportTicket::with(['user', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->whereIn('status', ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER'])
            ->latest()
            ->get();
        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function getAdminMessages(Request $request, string $ticketId): JsonResponse
    {
        try {
            $ticket = SupportTicket::with(['user:id,first_name,last_name,email', 'messages'])->findOrFail($ticketId);
            return response()->json(['success' => true, 'data' => $ticket->messages->sortBy('created_at')->values()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }
    }

    public function adminSendMessage(Request $request, string $ticketId): JsonResponse
    {
        try {
            $validated = $request->validate(['content' => 'required|string|max:5000']);
            // TicketService detects the admin role, flags the message and auto-transitions status.
            $msg = $this->ticketService->addMessage($ticketId, $validated['content'], $request->user()->id);

            // Broadcast so the customer's chat widget updates live with the admin's reply.
            $ticket = SupportTicket::find($ticketId);
            $this->realtimeService->emitChatMessage([
                'ticketId' => $ticketId,
                'userId' => $ticket?->user_id,
                'message' => $msg,
                'isAdmin' => true,
            ]);

            return response()->json(['success' => true, 'data' => $msg]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateStatus(Request $request, string $ticketId): JsonResponse
    {
        try {
            $validated = $request->validate(['status' => 'required|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED']);
            $ticket = SupportTicket::findOrFail($ticketId);
            $ticket->update(['status' => $validated['status']]);
            return response()->json(['success' => true, 'data' => $ticket]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function getStats(Request $request): JsonResponse
    {
        $stats = [
            'total_conversations' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'OPEN')->count(),
            'in_progress' => SupportTicket::where('status', 'IN_PROGRESS')->count(),
            'resolved' => SupportTicket::where('status', 'RESOLVED')->count(),
            'closed' => SupportTicket::where('status', 'CLOSED')->count(),
        ];
        return response()->json(['success' => true, 'data' => $stats]);
    }
}

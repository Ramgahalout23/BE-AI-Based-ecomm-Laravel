<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use App\Exceptions\AppError;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(protected CheckoutService $checkoutService) {}

    public function summary(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->checkoutService->getSummary(Auth::id())]);
    }

    public function calculateShipping(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->checkoutService->calculateShipping(Auth::id())]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['code' => 'required|string', 'subtotal' => 'required|numeric']);
            $result = $this->checkoutService->applyCoupon($validated['code'], $validated['subtotal']);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function removeCoupon(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Coupon removed']);
    }

    /**
     * Process checkout — supports both authenticated users and guest checkout.
     * POST /api/v1/checkout
     *
     * All users send items directly in the request body (productId, quantity).
     * Product prices are looked up from the database to prevent price tampering.
     * Guest users: a user account is created on-the-fly for referential integrity.
     */
    public function checkout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isGuest = !$user;

            // ── Validation rules ──
            $rules = [
                'items' => 'required|array|min:1',
                'items.*.productId' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',

                'shippingAddress.firstName' => 'required|string|max:255',
                'shippingAddress.lastName' => 'required|string|max:255',
                'shippingAddress.addressLine1' => 'required|string|max:255',
                'shippingAddress.addressLine2' => 'nullable|string|max:255',
                'shippingAddress.city' => 'required|string|max:255',
                'shippingAddress.state' => 'nullable|string|max:255',
                'shippingAddress.zipCode' => 'nullable|string|max:20',
                'shippingAddress.country' => 'nullable|string|max:100',
                'shippingAddress.phone' => 'required|string|max:20',
                'shippingAddress.email' => 'nullable|email|max:255',
                'paymentMethod' => 'nullable|string',
                'couponCode' => 'nullable|string',
                'shippingMethod' => 'nullable|string',
                'notes' => 'nullable|string',
            ];

            // Guest-only validation fields
            if ($isGuest) {
                $rules['createAccount'] = 'nullable|boolean';
                $rules['password'] = 'nullable|string|min:8';
            }

            $validated = $request->validate($rules);

            // ── Resolve or create a user (guests get an on-the-fly account) ──
            $accountCreated = false;
            if ($isGuest) {
                $result = $this->resolveGuestUser($validated);
                $user = $result['user'];
                $accountCreated = $result['created'];
                // Login the guest user so Auth::id() works downstream in OrderController::store
                Auth::login($user);
            }

            // ── Create an Address record from the inline shipping data ──
            $sa = $validated['shippingAddress'];
            $address = Address::create([
                'user_id' => $user->id,
                'type' => 'HOME',
                'first_name' => $sa['firstName'],
                'last_name' => $sa['lastName'],
                'phone_number' => $sa['phone'],
                'address_line1' => $sa['addressLine1'],
                'address_line2' => $sa['addressLine2'] ?? null,
                'city' => $sa['city'],
                'state' => $sa['state'] ?? null,
                'zip_code' => $sa['zipCode'] ?? null,
                'country' => $sa['country'] ?? 'India',
            ]);

            // ── Build order items from request, look up prices from DB ──
            $orderItems = [];
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['productId']);
                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product '{$item['productId']}' not found",
                    ], 422);
                }
                $price = (float) $product->price;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => (int) $item['quantity'],
                    'price' => $price,
                ];
                $subtotal += $price * (int) $item['quantity'];
            }
            $shippingCost = $subtotal >= 499 ? 0 : 50;
            $discount = 0;

            // ── Create the order via OrderController's store logic ──
            $orderRequest = new Request([
                'shipping_address_id' => $address->id,
                'items' => $orderItems,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'payment_method' => $validated['paymentMethod'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $orderController = app(OrderController::class);
            $orderResponse = $orderController->store($orderRequest);

            // ── If the user explicitly opted to create an account, attach Sanctum tokens ──
            if ($accountCreated && !empty($validated['createAccount'])) {
                $token = $user->createToken('checkout-token');
                $plainTextToken = $token->plainTextToken;

                $responseData = $orderResponse->getData();
                if (isset($responseData->data)) {
                    $responseData->data->accountCreated = true;
                    $responseData->data->tokens = [
                        'accessToken' => $plainTextToken,
                        'refreshToken' => null,
                    ];
                    $responseData->data->user = $user->toArray();
                }
                $orderResponse->setData($responseData);
            }

            return $orderResponse;
        } catch (AppError $e) {
            return $e->render();
        }
    }

    /**
     * Resolve a guest user for checkout.
     *
     * - If an email is provided and matches an existing user, returns that user
     *   (no account created).
     * - If createAccount is true and a password is provided, creates a full user
     *   with the real password.
     * - Otherwise creates a minimal guest user with a random password.
     *
     * @return array{user: User, created: bool}
     */
    private function resolveGuestUser(array $validated): array
    {
        $email = $validated['shippingAddress']['email'] ?? null;
        $firstName = $validated['shippingAddress']['firstName'] ?? 'Guest';
        $lastName = $validated['shippingAddress']['lastName'] ?? '';
        $phone = $validated['shippingAddress']['phone'] ?? '';
        $createAccount = !empty($validated['createAccount']);
        $password = $validated['password'] ?? null;

        // If email is provided and the user already exists, reuse the account
        if ($email) {
            $existing = User::where('email', $email)->first();
            if ($existing) {
                return ['user' => $existing, 'created' => false];
            }
        }

        // Decide password: use the provided one if creating account, else random
        $hashedPassword = $createAccount && $password
            ? Hash::make($password)
            : Hash::make(Str::random(32));

        // Generate a placeholder email if none was provided
        $userEmail = $email ?? 'guest_' . Str::random(12) . '@checkout.local';

        $user = User::create([
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $userEmail,
            'password'    => $hashedPassword,
            'phone_number'=> $phone,
            'role'        => 'CUSTOMER',
            'is_email_verified' => false,
            'is_active'   => true,
            'is_blocked'  => false,
        ]);

        return ['user' => $user, 'created' => true];
    }
}

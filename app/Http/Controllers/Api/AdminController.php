<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Services\BackupService;
use App\Services\EmailService;
use App\Services\ProductImportService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        protected AdminService $adminService,
        protected BackupService $backupService,
        protected EmailService $emailService
    ) {}

    public function dashboardMetrics(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getDashboard()]);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate   = $request->input('endDate');

        return response()->json([
            'success' => true,
            'data'    => $this->adminService->getDashboardSummary($startDate, $endDate),
        ]);
    }

    public function revenueTrends(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getRevenueTrends($request->days ?? 30)]);
    }

    public function recentOrders(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getRecentOrders()]);
    }

    public function activityLogs(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getActivityLogs()]);
    }

    public function users(Request $request): JsonResponse
    {
        $users = $this->adminService->getUsers($request->only(['search', 'role']));
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function productsList(Request $request): JsonResponse
    {
        $paginator = $this->adminService->getProducts($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'products' => $paginator->items(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pages' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function categoriesList(Request $request): JsonResponse
    {
        $paginator = $this->adminService->getCategoriesPaginated($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $paginator->items(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pages' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function brandsList(Request $request): JsonResponse
    {
        $paginator = $this->adminService->getBrandsPaginated($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'brands' => $paginator->items(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pages' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function userDetail(string $id): JsonResponse
    {
        try {
            $user = $this->adminService->getUserById($id);
            return response()->json(['success' => true, 'data' => $user]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function manageUser(Request $request, string $id): JsonResponse
    {
        try {
            $input = $request->only(['role', 'is_active', 'is_blocked', 'action']);

            // Handle frontend's 'action' param (block/unblock) mapping to is_blocked
            if (isset($input['action']) && !isset($input['is_blocked'])) {
                if ($input['action'] === 'block') {
                    $input['is_blocked'] = true;
                } elseif ($input['action'] === 'unblock') {
                    $input['is_blocked'] = false;
                }
            }

            $user = $this->adminService->manageUser($id, $input);
            return response()->json(['success' => true, 'message' => 'User updated', 'data' => $user]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function systemHealth(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getSystemHealth()]);
    }

    // ── Analytics ──

    public function salesAnalytics(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getSalesAnalytics($request->days ?? 30)]);
    }

    public function productAnalytics(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getProductAnalytics($request->limit ?? 20)]);
    }

    public function userAnalytics(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getUserAnalytics()]);
    }

    public function orderStatusDistribution(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getOrderStatusDistribution()]);
    }

    public function paymentMethodStats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getPaymentMethodStats()]);
    }

    public function customerLifetimeValue(string $userId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getCustomerLifetimeValue($userId)]);
    }

    public function topCustomers(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getTopCustomers($request->limit ?? 20)]);
    }

    public function categoryPerformance(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getCategoryPerformance()]);
    }

    public function dailySales(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getDailySales($request->days ?? 30)]);
    }

    public function hourlyDistribution(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getHourlyDistribution()]);
    }

    public function revenueComparison(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getRevenueComparison()]);
    }

    public function customerGrowth(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getCustomerGrowth($request->months ?? 12)]);
    }

    public function conversionMetrics(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getConversionMetrics()]);
    }

    public function paymentMethodTrends(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getPaymentMethodTrends($request->days ?? 30)]);
    }

    public function orderRevenue(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getOrderRevenueStats()]);
    }

    /**
     * Get review analytics for the admin dashboard.
     * GET /api/v1/admin/analytics/reviews?days=30
     */
    public function reviewAnalytics(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        return response()->json(['success' => true, 'data' => $this->adminService->getReviewAnalytics($days)]);
    }

    // ── Staff ──

    public function staff(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getStaff($request->only(['search', 'role']))]);
    }

    public function staffCreate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'role' => 'nullable|string|in:ADMIN,MANAGER,SUPER_ADMIN,SUPPORT_AGENT,FINANCE',
                'password' => 'nullable|string|min:8',
            ]);
            $staff = $this->adminService->createStaff($validated);
            return response()->json(['success' => true, 'message' => 'Staff created', 'data' => $staff], 201);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function staffUpdate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'role' => 'nullable|string|in:ADMIN,MANAGER,SUPER_ADMIN,SUPPORT_AGENT,FINANCE',
                'is_active' => 'nullable|boolean',
            ]);
            $staff = $this->adminService->updateStaff($id, $validated);
            return response()->json(['success' => true, 'message' => 'Staff updated', 'data' => $staff]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    // ── Backups ──

    public function backupSettings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->backupService->getBackupSettings()]);
    }

    public function backupSettingsUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'backup_frequency' => 'nullable|string|in:manual,daily,weekly',
            'backup_time' => 'nullable|string',
            'backup_day_of_week' => 'nullable|string',
        ]);
        $data = $this->backupService->updateBackupSettings($validated);
        return response()->json(['success' => true, 'message' => 'Backup settings updated', 'data' => $data]);
    }

    public function backupCreate(): JsonResponse
    {
        try {
            $data = $this->backupService->createBackup();
            return response()->json(['success' => true, 'message' => 'Backup created', 'data' => $data], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function backupsList(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->backupService->listBackups()]);
    }

    public function backupDownload(string $filename): JsonResponse
    {
        $path = $this->backupService->getBackupPath($filename);
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Backup file not found'], 404);
        }
        return response()->download($path, $filename);
    }

    public function backupDelete(string $filename): JsonResponse
    {
        try {
            $this->backupService->deleteBackup($filename);
            return response()->json(['success' => true, 'message' => 'Backup deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    // ── Misc Admin ──

    public function auditLogs(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getActivityLogs()]);
    }

    // ── Export Users ──

    /**
     * Export users as CSV download.
     * GET /api/v1/admin/users/export
     */
    public function exportUsers(Request $request): \Illuminate\Http\Response
    {
        try {
            $filters = $request->only(['search', 'role', 'status']);
            $users = $this->adminService->getUsers($filters);
            $csv = $this->adminService->generateUsersCsv($users);
            $filename = 'users-export-' . now()->format('Y-m-d') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Export Orders ──

    /**
     * Export orders as CSV download.
     * GET /api/v1/admin/orders/export
     */
    public function exportOrders(Request $request): \Illuminate\Http\Response
    {
        try {
            $filters = $request->only(['status', 'start_date', 'end_date', 'search']);
            $orders = \App\Models\Order::with(['user' => fn($q) => $q->select(['id', 'first_name', 'last_name', 'email'])])
                ->select(['id', 'order_number', 'user_id', 'total', 'status', 'payment_method', 'created_at']);

            if (!empty($filters['status'])) {
                $orders->where('status', $filters['status']);
            }
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $orders->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
            }

            $orders = $orders->latest()->get();

            // Build CSV
            $headers = ['Order Number', 'Customer', 'Email', 'Total', 'Status', 'Payment Method', 'Date'];
            $rows = $orders->map(fn($o) => [
                $o->order_number,
                ($o->user->first_name ?? '') . ' ' . ($o->user->last_name ?? ''),
                $o->user->email ?? '',
                $o->total,
                $o->status,
                $o->payment_method ?? '',
                $o->created_at->format('Y-m-d H:i:s'),
            ])->toArray();

            $csv = $this->arrayToCsv($headers, $rows);
            $filename = 'orders-export-' . now()->format('Y-m-d') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Bulk Delete Products ──

    /**
     * Bulk delete products.
     * POST /api/v1/admin/products/bulk-delete
     */
    public function bulkDeleteProducts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'string']);
            $deleted = 0;
            $errors = [];
            foreach ($validated['ids'] as $id) {
                try {
                    $product = \App\Models\Product::find($id);
                    if ($product) {
                        $product->delete();
                        $deleted++;
                    } else {
                        $errors[] = "Product {$id} not found";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Product {$id}: {$e->getMessage()}";
                }
            }
            return response()->json([
                'success' => true,
                'message' => "{$deleted} product(s) deleted",
                'data' => ['deleted' => $deleted, 'errors' => $errors],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Convert array data to CSV string with UTF-8 BOM for Excel compatibility.
     */
    private function arrayToCsv(array $headers, array $rows): string
    {
        // UTF-8 BOM (\xEF\xBB\xBF) ensures Excel on Windows renders ₹ and other special characters correctly
        $csv = "\xEF\xBB\xBF";
        $csv .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $row)) . "\n";
        }
        return $csv;
    }

    public function updateUserRole(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['role' => 'required|string|in:CUSTOMER,MANAGER,ADMIN,SUPER_ADMIN']);
            $user = $this->adminService->manageUser($id, ['role' => $validated['role']]);
            return response()->json(['success' => true, 'message' => 'User role updated', 'data' => $user]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function emailPreview(): JsonResponse
    {
        try {
            $html = $this->emailService->generatePreviewHtml();
            return response()->json(['success' => true, 'data' => ['html' => $html]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function emailTest(Request $request): JsonResponse
    {
        $validated = $request->validate(['to' => 'required|email']);
        $sent = $this->emailService->sendEmail(
            $validated['to'],
            'Test Email from THREVOLT',
            '<h1>Test Email</h1><p>This is a test email from THREVOLT admin panel.</p>'
        );
        if ($sent) {
            return response()->json(['success' => true, 'message' => 'Test email sent to ' . $validated['to']]);
        }
        return response()->json(['success' => false, 'message' => 'Failed to send test email'], 500);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }
        $file = $request->file('file');
        $path = $file->store('uploads', 'public');
        return response()->json([
            'success' => true,
            'data' => [
                'url' => '/storage/' . $path,
                'filename' => $file->getClientOriginalName(),
                'mimetype' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]
        ]);
    }

    public function uploadMultiple(Request $request): JsonResponse
    {
        if (!$request->hasFile('files')) {
            return response()->json(['success' => false, 'message' => 'No files uploaded'], 400);
        }
        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store('uploads', 'public');
            $uploaded[] = [
                'url' => '/storage/' . $path,
                'filename' => $file->getClientOriginalName(),
                'mimetype' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }
        return response()->json(['success' => true, 'data' => ['files' => $uploaded]]);
    }

    // ── Product Import ──

    public function importProducts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'csv' => 'required|string',
            ]);

            $importService = app(ProductImportService::class);
            $result = $importService->importFromCSV($validated['csv']);

            return response()->json([
                'success' => true,
                'message' => "Imported {$result['imported']} products, skipped {$result['skipped']}, errors {$result['errors']}",
                'data' => $result,
            ]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cacheClear(): JsonResponse
    {
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        return response()->json(['success' => true, 'message' => 'System cache cleared successfully']);
    }

    public function databaseSeed(Request $request): JsonResponse
    {
        $validated = $request->validate(['confirm' => 'required|boolean']);
        if (!$validated['confirm']) {
            return response()->json(['success' => false, 'message' => 'Confirmation required'], 400);
        }
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        // Seeding changes all entity data — clear cache so dashboard reflects fresh data immediately
        app(\App\Repositories\AdminRepository::class)->clearDashboardCache();

        return response()->json(['success' => true, 'message' => 'Database seed started', 'output' => $output]);
    }

    // ── Queue Monitoring ──

    /**
     * List all failed jobs with pagination.
     */
    public function failedJobs(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);

            $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->latest('failed_at')
                ->paginate(min($perPage, 100));

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $failedJobs->items(),
                    'total' => $failedJobs->total(),
                    'page' => $failedJobs->currentPage(),
                    'per_page' => $failedJobs->perPage(),
                    'total_pages' => $failedJobs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retry a specific failed job by its UUID.
     * The UUID comes from the failed_jobs.uuid column.
     */
    public function retryFailedJob(string $uuid): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => $uuid]);
            return response()->json([
                'success' => true,
                'message' => "Job {$uuid} requeued for retry",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllFailedJobs(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);
            return response()->json([
                'success' => true,
                'message' => 'All failed jobs requeued for retry',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Flush all failed jobs (delete them from the table).
     */
    public function flushFailedJobs(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:flush');
            return response()->json([
                'success' => true,
                'message' => 'All failed jobs flushed',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

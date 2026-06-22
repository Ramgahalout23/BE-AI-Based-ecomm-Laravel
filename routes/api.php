<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\AbandonedCartController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\CampaignTemplateController;
use App\Http\Controllers\Api\AdCampaignController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SMSController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\CuratedLookController;
use App\Http\Controllers\Api\ReelController;

Route::prefix('v1')->group(function () {

    Route::get('/health', function () {
        return response()->json(['status' => 'healthy', 'timestamp' => now()]);
    });

    Route::get('/health/status', function () {
        return response()->json(['status' => 'healthy', 'timestamp' => now(), 'version' => '1.0.0']);
    });

    // ── Public Payment Routes ──
    Route::get('/payments/methods', [PaymentController::class, 'getPaymentMethods']);
    Route::post('/payments/razorpay/create-order', [PaymentController::class, 'createRazorpayOrder']);
    Route::post('/payments/razorpay/verify', [PaymentController::class, 'verifyRazorpayPayment']);
    Route::post('/payments/custom/initiate', [PaymentController::class, 'initiateCustomGateway']);
    Route::get('/payments/callback', [PaymentController::class, 'handleGatewayCallback']);

    // ── Public Marketing Routes ──
    Route::post('/marketing/subscribe', [MarketingController::class, 'publicSubscribe']);
    Route::post('/marketing/unsubscribe', [MarketingController::class, 'publicUnsubscribe']);

    // ── Public Tax Routes ──
    Route::post('/tax/calculate', [TaxController::class, 'calculateTax']);

    // ── Public Product Variant Routes ──
    Route::get('/products/{productId}/variants/attributes', [ProductVariantController::class, 'getVariantByAttributes']);
    Route::get('/products/{productId}/variants', [ProductVariantController::class, 'byProduct']);
    Route::get('/variants/{id}', [ProductVariantController::class, 'show']);

    // ── Email Service Health ──
    Route::get('/email/health', [AdminController::class, 'emailPreview']);

    // ── Public Auth Routes ──
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/send-verification', [AuthController::class, 'sendVerification']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);

        // OAuth Routes
        Route::get('/oauth/status', [AuthController::class, 'oauthStatus']);
        Route::get('/{provider}', [AuthController::class, 'redirectToProvider'])->where('provider', 'google|facebook');
        Route::get('/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->where('provider', 'google|facebook');
    });

    // ── Authenticated Auth Routes (must be after public auth to not conflict) ──
    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/refresh-oauth', [AuthController::class, 'refreshOAuth'])->middleware('admin');
    });

    // Public Product Routes
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);
    Route::get('/products/best-sellers', [ProductController::class, 'bestSellers']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/brand', [BrandController::class, 'index']);
    Route::get('/products/category/{categoryId}', [ProductController::class, 'byCategory']);
    Route::get('/products/{productId}/availability', [ProductController::class, 'checkAvailability']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);

    Route::get('/categories/hierarchy', [CategoryController::class, 'hierarchy']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Public Banner Routes (specific filter endpoints BEFORE generic /{id})
    Route::get('/banners/homepage', [BannerController::class, 'getHomepageBanners']);
    Route::get('/banners/hero', [BannerController::class, 'getHeroBanners']);
    Route::get('/banners/sale', [BannerController::class, 'getSaleBanners']);
    Route::get('/banners/category', [BannerController::class, 'getCategoryBanners']);
    Route::get('/banners/popup', [BannerController::class, 'getPopupBanners']);
    Route::get('/banners', [BannerController::class, 'getActiveBanners']);
    Route::get('/banners/{id}', [BannerController::class, 'show']);

    // Public Review Routes
    Route::get('/reviews/homepage', [ReviewController::class, 'homepage']);
    Route::get('/reviews/product/{productId}', [ReviewController::class, 'productReviews']);
    Route::get('/reviews/stats/{productId}', [ReviewController::class, 'stats']);
    Route::get('/reviews/verified/{productId}', [ReviewController::class, 'verifiedReviews']);
    Route::get('/reviews/user', [ReviewController::class, 'userReviews'])->middleware('auth:sanctum');
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);

    // Public Category Routes (specific BEFORE generic {id})
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::get('/categories/{categoryId}/subcategories', [CategoryController::class, 'subcategories']);
    Route::get('/categories/{categoryId}/stats', [CategoryController::class, 'categoryStats']);

    // Public Coupon Routes (specific routes before generic)
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::get('/coupons/auto-apply/list', [CouponController::class, 'getAutoApply']);
    Route::post('/coupons/best', [CouponController::class, 'getBest']);
    Route::get('/coupons/{code}', [CouponController::class, 'getByCode']);
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
    Route::post('/coupons/apply', [CouponController::class, 'apply']);

    // Public Shipping Routes
    Route::get('/shipping/methods', [ShippingController::class, 'methods']);
    Route::get('/shipping/providers', [ShippingController::class, 'providers']);
    Route::get('/shipping/zones', [ShippingController::class, 'zones']);
    Route::post('/shipping/calculate', [ShippingController::class, 'calculate']);
    Route::get('/shipping/tracking/{trackingNumber}', [ShippingController::class, 'trackShipment']);
    Route::get('/shipping/track/{trackingId}', [ShippingController::class, 'trackShipment']);

    // Public Promotion Routes
    Route::get('/promotions', [PromotionController::class, 'index']);

    // Public Tracking Routes
    Route::post('/tracking/pageview', [TrackingController::class, 'pageView']);
    Route::post('/tracking/session', [TrackingController::class, 'createSession']);
    Route::patch('/tracking/session/{sessionId}/end', [TrackingController::class, 'endSession']);
    Route::post('/tracking/event', [TrackingController::class, 'recordEvent']);
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

    // Public Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/settings/maintenance', [SettingsController::class, 'getMaintenanceStatus']);
    Route::get('/settings/404', [SettingsController::class, 'getCustom404']);
    Route::get('/settings/{key}', [SettingsController::class, 'show']);

    // Public Pages
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);

    // Public SEO (specific routes BEFORE generic {entityType}/{entityId} routes)
    Route::get('/seo/global', [SeoController::class, 'globalSEO']);
    Route::get('/seo/sitemap', [SeoController::class, 'sitemap']);
    Route::get('/seo/robots', [SeoController::class, 'robotsTxt']);
    Route::get('/seo/robots/raw', [SeoController::class, 'robotsTxtRaw']);
    Route::get('/seo/sitemap/raw', [SeoController::class, 'sitemapRaw']);
    Route::get('/seo/pages/{slug}', [SeoController::class, 'show']);
    Route::get('/seo/{entityType}/{entityId}', [SeoController::class, 'showEntitySEO']);

    // Public Reels
    Route::get('/reels', [ReelController::class, 'index']);

    // Public Curated Looks
    Route::get('/curated-looks', [CuratedLookController::class, 'index']);

    // Public Inventory Check (matches TypeScript GET /inventory/:productId/check)
    Route::get('/inventory/{productId}/check', [InventoryController::class, 'check']);

    // ── Public Checkout Routes (supports guest checkout) ──
    Route::post('/checkout', [CheckoutController::class, 'checkout']);
    Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon']);
    Route::delete('/checkout/coupon', [CheckoutController::class, 'removeCoupon']);

    // ── Authenticated User Routes ──
    Route::middleware('auth:sanctum')->group(function () {
        // ── SMS Routes ──
        Route::get('/sms/status', [SMSController::class, 'status']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'addItem']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::patch('/cart/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/cart/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::post('/cart/validate', [CartController::class, 'validateCart']);

        Route::get('/checkout/summary', [CheckoutController::class, 'summary']);
        Route::post('/checkout/shipping', [CheckoutController::class, 'calculateShipping']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::get('/orders/track-by-number/{orderNumber}', [OrderController::class, 'tracking']);

        // Invoice routes must be BEFORE generic /orders/{id}
        Route::get('/orders/{orderId}/invoice', [InvoiceController::class, 'show']);
        Route::get('/orders/{orderId}/invoice/download', [InvoiceController::class, 'download']);

        // ── Order Tracking (authenticated user) ──
        Route::get('/orders/{orderId}/tracking', [OrderController::class, 'getOrderTracking']);
        Route::post('/orders/{orderId}/subscribe-updates', [OrderController::class, 'subscribeToUpdates']);
        Route::post('/orders/{orderId}/return', [OrderController::class, 'requestReturn']);

        // ── Cart Merge ──
        Route::post('/cart/merge', [CartController::class, 'mergeCart']);

        // ── Notifications (authenticated user) ──
        Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);
        Route::get('/notifications/type/{type}', [NotificationController::class, 'getNotificationsByType']);
        Route::get('/notifications/stats', [NotificationController::class, 'getNotificationStats']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
        Route::delete('/wishlist', [WishlistController::class, 'clearWishlist']);
        Route::post('/wishlist/bulk', [WishlistController::class, 'bulkAdd']);
        Route::post('/wishlist/{productId}/move-to-cart', [WishlistController::class, 'moveToCart']);
        Route::get('/wishlist/check/{productId}', [WishlistController::class, 'check']);
        Route::get('/wishlist/count', [WishlistController::class, 'count']);

        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{id}/helpful', [ReviewController::class, 'markHelpful']);
        Route::post('/reviews/{id}/unhelpful', [ReviewController::class, 'markUnhelpful']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::get('/addresses/{id}', [AddressController::class, 'show']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        Route::post('/coupons/apply', [CouponController::class, 'validateCoupon']);
        Route::delete('/coupons/remove', [CheckoutController::class, 'removeCoupon']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{paymentId}', [PaymentController::class, 'getPaymentDetails']);
        Route::post('/payments/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
        Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
        Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
        Route::post('/payments/{paymentId}/verify', [PaymentController::class, 'verifyPayment']);
        Route::get('/payments/refunds/list', [PaymentController::class, 'getUserRefunds']);
        Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'processRefund']);

        Route::get('/user-profile', [UserProfileController::class, 'show']);
        Route::put('/user-profile', [AuthController::class, 'updateProfile']);
        Route::get('/user-profile/stats', [UserProfileController::class, 'stats']);
        Route::get('/user-profile/orders', [UserProfileController::class, 'orders']);
        Route::get('/user-profile/wallet', [UserProfileController::class, 'wallet']);
        Route::get('/user-profile/loyalty', [UserProfileController::class, 'loyalty']);
        // ── User Profile Addresses (specific before generic) ──
        Route::get('/user-profile/addresses/default', [UserProfileController::class, 'defaultAddress']);
        Route::get('/user-profile/addresses', [UserProfileController::class, 'addresses']);
        Route::post('/user-profile/addresses', [UserProfileController::class, 'addAddress']);
        Route::get('/user-profile/addresses/{addressId}', [UserProfileController::class, 'showAddress']);
        Route::put('/user-profile/addresses/{addressId}', [UserProfileController::class, 'updateAddress']);
        Route::delete('/user-profile/addresses/{addressId}', [UserProfileController::class, 'deleteAddress']);
        Route::post('/user-profile/addresses/{addressId}/set-default', [UserProfileController::class, 'setDefaultAddress']);

        // ── Wallet Routes (User) ──
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/balance', [WalletController::class, 'balance']);
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::post('/wallet/recharge', [WalletController::class, 'recharge']);

        // ── Loyalty Routes (User) ──
        Route::get('/loyalty', [LoyaltyController::class, 'show']);
        Route::get('/loyalty/balance', [LoyaltyController::class, 'balance']);
        Route::get('/loyalty/history', [LoyaltyController::class, 'history']);
        Route::get('/loyalty/info', [LoyaltyController::class, 'info']);

        // ── Refund Routes (User) ──
        Route::post('/refund-requests', [RefundController::class, 'store']);
        Route::get('/refund-requests', [RefundController::class, 'index']);
        Route::get('/refunds', [RefundController::class, 'refunds']);

        // ── Return Routes (User) ──
        Route::post('/return-requests', [ReturnController::class, 'store']);
        Route::get('/return-requests', [ReturnController::class, 'index']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::post('/tickets', [SupportTicketController::class, 'store']);
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
        Route::post('/tickets/{id}/messages', [SupportTicketController::class, 'addMessage']);
        Route::post('/abandoned-carts', [AbandonedCartController::class, 'store']);
        Route::get('/abandoned-carts', [AbandonedCartController::class, 'userCarts']);
        Route::get('/abandoned-carts/{id}', [AbandonedCartController::class, 'show']);

        // ── Shipping (User) ──
        Route::get('/shipping/order/{orderId}', [ShippingController::class, 'getShippingByOrder']);
        Route::get('/shipping/my-shipments', [ShippingController::class, 'getUserShipments']);
        Route::get('/shipping/{shippingId}', [ShippingController::class, 'show']);

        // ── Chat Routes ──
        Route::post('/chat/init', [ChatController::class, 'init']);
        Route::post('/chat/{ticketId}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/chat/{ticketId}/typing', [ChatController::class, 'sendTyping']);
        Route::get('/chat/{ticketId}/messages', [ChatController::class, 'getMessages']);
    });

    // ── Admin Shipping Routes (inside admin middleware group) ──
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::post('/shipping/zones', [ShippingController::class, 'createZone']);
        Route::get('/shipping/zones/list', [ShippingController::class, 'zonesList']);
        Route::put('/shipping/zones/{id}', [ShippingController::class, 'updateZone']);
        Route::delete('/shipping/zones/{id}', [ShippingController::class, 'deleteZone']);
        Route::post('/shipping/rates', [ShippingController::class, 'createRate']);
        Route::put('/shipping/rates/{id}', [ShippingController::class, 'updateRate']);
        Route::delete('/shipping/rates/{id}', [ShippingController::class, 'deleteRate']);
        Route::post('/shipping', [ShippingController::class, 'createShipping']);
        Route::put('/shipping/{id}', [ShippingController::class, 'updateShipping']);
        Route::get('/shipping/all', [ShippingController::class, 'getAllShippings']);
        Route::get('/shipping/by-status', [ShippingController::class, 'getShipmentsByStatus']);
        Route::get('/shipping/stats', [ShippingController::class, 'getShippingStats']);
    });

    // ── Admin Routes ──
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        // ── Dashboard & Analytics ──
        Route::get('/dashboard/metrics', [AdminController::class, 'dashboardMetrics']);
        Route::get('/dashboard/summary', [AdminController::class, 'dashboardSummary']);
        Route::get('/dashboard/health', [AdminController::class, 'systemHealth']);
        Route::get('/dashboard/activity-logs', [AdminController::class, 'activityLogs']);
        Route::get('/analytics/revenue-trends', [AdminController::class, 'revenueTrends']);
        Route::get('/analytics/sales', [AdminController::class, 'salesAnalytics']);
        Route::get('/analytics/products', [AdminController::class, 'productAnalytics']);
        Route::get('/analytics/users', [AdminController::class, 'userAnalytics']);
        Route::get('/analytics/order-status', [AdminController::class, 'orderStatusDistribution']);
        Route::get('/analytics/payment-methods', [AdminController::class, 'paymentMethodStats']);
        Route::get('/analytics/customers/{userId}/lifetime-value', [AdminController::class, 'customerLifetimeValue']);
        Route::get('/analytics/top-customers', [AdminController::class, 'topCustomers']);
        Route::get('/analytics/categories', [AdminController::class, 'categoryPerformance']);
        Route::get('/analytics/daily-sales', [AdminController::class, 'dailySales']);
        Route::get('/analytics/hourly-distribution', [AdminController::class, 'hourlyDistribution']);
        Route::get('/analytics/revenue-comparison', [AdminController::class, 'revenueComparison']);
        Route::get('/analytics/customer-growth', [AdminController::class, 'customerGrowth']);
        Route::get('/analytics/conversion-metrics', [AdminController::class, 'conversionMetrics']);
        Route::get('/analytics/reviews', [AdminController::class, 'reviewAnalytics']);
        Route::get('/analytics/payment-method-trends', [AdminController::class, 'paymentMethodTrends']);
        Route::get('/orders/revenue', [AdminController::class, 'orderRevenue']);

        // ── Users ──
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/export', [AdminController::class, 'exportUsers']);
        Route::get('/users/{id}', [AdminController::class, 'userDetail']);
        Route::post('/users/{id}/manage', [AdminController::class, 'manageUser']);

        // ── Staff ──
        Route::get('/staff', [AdminController::class, 'staff']);
        Route::post('/staff', [AdminController::class, 'staffCreate']);
        Route::patch('/staff/{id}', [AdminController::class, 'staffUpdate']);

        // ── Orders ──
        Route::get('/orders/revenue-stats', [OrderController::class, 'getRevenueStats']);
        Route::get('/orders', [OrderController::class, 'allOrders']);
        Route::get('/orders/export', [AdminController::class, 'exportOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

        // ── Products ──
        Route::get('/products', [AdminController::class, 'productsList']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/import', [ProductController::class, 'importProductsFromCSV']);
        Route::post('/products/bulk-delete', [AdminController::class, 'bulkDeleteProducts']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::get('/products/low-stock', [InventoryController::class, 'lowStock']);



        // ── Categories ──
        Route::get('/categories', [AdminController::class, 'categoriesList']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // ── Brands ──
        Route::get('/brands', [AdminController::class, 'brandsList']);
        Route::post('/brands', [BrandController::class, 'store']);
        Route::put('/brands/{id}', [BrandController::class, 'update']);
        Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

        // ── Products (Admin) ──
        Route::patch('/products/{id}/publish', [ProductController::class, 'publishProduct']);
        Route::patch('/products/{id}/archive', [ProductController::class, 'archiveProduct']);
        Route::get('/products/low-stock', [ProductController::class, 'getLowStockProducts']);
        Route::post('/products/import', [ProductController::class, 'importProductsFromCSV']);

        // ── Coupons ──
        Route::get('/coupons', [CouponController::class, 'adminIndex']);
        Route::get('/coupons/{id}', [CouponController::class, 'show']);
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::post('/coupons/bulk-generate', [CouponController::class, 'bulkGenerate']);
        Route::patch('/coupons/{id}', [CouponController::class, 'update']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
        Route::patch('/coupons/{id}/toggle', [CouponController::class, 'toggle']);
        Route::get('/coupons/{id}/analytics', [CouponController::class, 'analytics']);
        Route::get('/coupons/{id}/usage-history', [CouponController::class, 'usageHistory']);

        // ── Banners ──
        Route::get('/banners', [BannerController::class, 'adminIndex']);
        Route::post('/banners', [BannerController::class, 'store']);
        Route::put('/banners/{id}', [BannerController::class, 'update']);
        Route::patch('/banners/{id}/toggle', [BannerController::class, 'toggleStatus']);
        Route::patch('/banners/reorder', [BannerController::class, 'reorder']);
        Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

        // ── Inventory (specific routes BEFORE generic {productId})
        Route::get('/inventory/stats', [InventoryController::class, 'stats']);
        Route::post('/inventory/batch-update', [InventoryController::class, 'batchUpdate']);
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
        Route::post('/inventory/add', [InventoryController::class, 'addStock']);
        Route::patch('/inventory/{id}/stock', [InventoryController::class, 'updateStock']);
        Route::post('/inventory/reduce', [InventoryController::class, 'reduceStock']);
        Route::get('/inventory/{productId}', [InventoryController::class, 'show']);
        Route::get('/inventory/{productId}/movement', [InventoryController::class, 'movement']);

        // ── Reviews ──
        Route::get('/reviews', [ReviewController::class, 'adminIndex']);
        Route::get('/reviews/pending', [ReviewController::class, 'pendingReviews']);
        Route::post('/reviews/{id}/moderate', [ReviewController::class, 'moderate']);
        Route::post('/reviews/{id}/approve', [ReviewController::class, 'approve']);
        Route::post('/reviews/{id}/reject', [ReviewController::class, 'reject']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

        // ── Notifications (Admin) ──
        Route::get('/notifications/all', [NotificationController::class, 'adminGetAll']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'adminDelete']);
        Route::post('/notifications/system', [NotificationController::class, 'store']);
        Route::post('/notifications/bulk', [NotificationController::class, 'sendBulkNotification']);

        // ── Tickets ──
        Route::get('/tickets/stats', [SupportTicketController::class, 'adminStats']);
        Route::get('/tickets', [SupportTicketController::class, 'adminIndex']);
        Route::patch('/tickets/{id}/status', [SupportTicketController::class, 'updateStatus']);
        Route::put('/tickets/{id}', [SupportTicketController::class, 'adminUpdate']);
        Route::post('/tickets/{id}/messages', [SupportTicketController::class, 'adminAddMessage']);
        Route::delete('/tickets/{id}', [SupportTicketController::class, 'adminDestroy']);

        // ── Abandoned Carts (specific before generic {id}) ──
        Route::get('/abandoned-carts/stats', [AbandonedCartController::class, 'stats']);
        Route::get('/abandoned-carts', [AbandonedCartController::class, 'all']);
        Route::get('/abandoned-carts/{id}', [AbandonedCartController::class, 'show']);
        Route::post('/abandoned-carts/{id}/remind', [AbandonedCartController::class, 'sendReminder']);
        Route::delete('/abandoned-carts/{id}', [AbandonedCartController::class, 'destroy']);

        // ── Promotions ──
        Route::get('/promotions', [PromotionController::class, 'adminIndex']);
        Route::post('/promotions', [PromotionController::class, 'store']);
        Route::put('/promotions/{id}', [PromotionController::class, 'update']);
        Route::delete('/promotions/{id}', [PromotionController::class, 'destroy']);

        // ── Settings ──
        Route::post('/settings', [SettingsController::class, 'store']);
        Route::post('/settings/update-multiple', [SettingsController::class, 'update']);
        Route::post('/settings/maintenance', [SettingsController::class, 'toggleMaintenance']);
        Route::put('/settings/404', [SettingsController::class, 'updateCustom404']);
        Route::get('/settings/maintenance/schedules', [SettingsController::class, 'getSchedules']);
        Route::post('/settings/maintenance/schedules', [SettingsController::class, 'createSchedule']);
        Route::put('/settings/maintenance/schedules/{id}', [SettingsController::class, 'updateSchedule']);
        Route::delete('/settings/maintenance/schedules/{id}', [SettingsController::class, 'deleteSchedule']);
        Route::put('/settings/{key}', [SettingsController::class, 'updateByKey']);

        // ── CMS Pages (Admin) ──
        Route::get('/pages', [PageController::class, 'adminIndex']);
        Route::post('/pages', [PageController::class, 'store']);
        Route::put('/pages/{id}', [PageController::class, 'adminUpdate']);
        Route::delete('/pages/{id}', [PageController::class, 'adminDestroy']);

        // ── Misc Admin ──
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
        Route::patch('/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('/email/preview', [AdminController::class, 'emailPreview']);
        Route::post('/email/test', [AdminController::class, 'emailTest']);
        Route::post('/upload', [AdminController::class, 'uploadFile']);
        Route::post('/upload/multiple', [AdminController::class, 'uploadMultiple']);
        Route::post('/cache/clear', [AdminController::class, 'cacheClear']);
        Route::post('/database/seed', [AdminController::class, 'databaseSeed']);

        // ── Backups ──
        Route::get('/backup-settings', [AdminController::class, 'backupSettings']);
        Route::patch('/backup-settings', [AdminController::class, 'backupSettingsUpdate']);
        Route::post('/backup', [AdminController::class, 'backupCreate']);
        Route::get('/backups', [AdminController::class, 'backupsList']);
        Route::get('/backups/{filename}', [AdminController::class, 'backupDownload']);
        Route::delete('/backups/{filename}', [AdminController::class, 'backupDelete']);

        // ── Tracking (specific routes BEFORE generic routes) ──
        Route::get('/tracking/pageviews/stats', [TrackingController::class, 'pageViewStats']);
        Route::get('/tracking/pageviews', [TrackingController::class, 'adminPageViews']);
        Route::get('/tracking/events/stats', [TrackingController::class, 'getEventStats']);
        Route::get('/tracking/events', [TrackingController::class, 'getEvents']);
        Route::get('/tracking/sessions/active', [TrackingController::class, 'activeSessions']);
        Route::get('/tracking/sessions/stats', [TrackingController::class, 'sessionStats']);
        Route::get('/tracking/dashboard', [TrackingController::class, 'dashboard']);
        Route::get('/tracking/journey/{userId}', [TrackingController::class, 'getUserJourney']);

        // ── SEO ──
        Route::get('/seo/list/{entityType}', [SeoController::class, 'listSEO']);
        Route::put('/seo/global', [SeoController::class, 'updateGlobalSEO']);
        Route::put('/seo/robots', [SeoController::class, 'updateRobotsTxt']);
        Route::post('/seo/sitemap/refresh', [SeoController::class, 'refreshSitemap']);
        Route::get('/seo/sitemap/db', [SeoController::class, 'getSitemapFromDB']);
        Route::put('/seo/{entityType}/{entityId}', [SeoController::class, 'updateEntitySEO']);
        Route::get('/seo/{entityType}/{entityId}/full', [SeoController::class, 'getFullEntitySEO']);
        Route::delete('/seo/{id}', [SeoController::class, 'destroySEO']);
        Route::post('/seo/pages/{slug}', [SeoController::class, 'update']);

        // ── SEO Dashboard ──
        Route::get('/seo/dashboard', [SeoController::class, 'dashboard']);

        // ── Advanced SEO ──
        Route::get('/seo/advanced/settings', [SeoController::class, 'advancedSettings']);
        Route::put('/seo/advanced/settings', [SeoController::class, 'updateAdvancedSettings']);
        Route::get('/seo/advanced/schema/product/{entityId}', [SeoController::class, 'generateProductSchema']);
        Route::get('/seo/advanced/schema/organization', [SeoController::class, 'generateOrganizationSchema']);
        Route::get('/seo/advanced/schema/website', [SeoController::class, 'generateWebsiteSchema']);
        Route::post('/seo/advanced/schema/breadcrumb', [SeoController::class, 'generateBreadcrumbSchema']);
        Route::post('/seo/advanced/schema/faq', [SeoController::class, 'generateFAQSchema']);
        Route::post('/seo/advanced/schema/auto/{entityType}/{entityId}', [SeoController::class, 'autoGenerateSchemas']);
        Route::get('/seo/advanced/audit/{entityType}/{entityId}', [SeoController::class, 'auditEntitySEO']);
        Route::post('/seo/advanced/audit/bulk', [SeoController::class, 'bulkAuditSEO']);
        Route::get('/seo/advanced/breadcrumbs/{entityType}/{entityId}', [SeoController::class, 'generateBreadcrumbs']);
        Route::post('/seo/advanced/indexnow', [SeoController::class, 'pushIndexNow']);

        // ── Product Variants ──
        Route::get('/products/{productId}/variants', [ProductVariantController::class, 'byProduct']);
        Route::post('/products/{productId}/variants', [ProductVariantController::class, 'store']);
        Route::post('/products/{productId}/variants/bulk', [ProductVariantController::class, 'bulkCreateVariants']);
        Route::get('/variants', [ProductVariantController::class, 'getAllVariants']);
        Route::put('/variants/{id}', [ProductVariantController::class, 'update']);
        Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);
        Route::get('/variants/low-stock', [ProductVariantController::class, 'lowStock']);
        Route::patch('/variants/{id}/quantity', [ProductVariantController::class, 'updateQuantity']);
        Route::patch('/variants/bulk-quantity', [ProductVariantController::class, 'bulkUpdateQuantities']);
        // ── Payments (Admin) ──
        Route::get('/payments/all', [PaymentController::class, 'getAllPayments']);
        Route::get('/payments/stats', [PaymentController::class, 'getPaymentStats']);
        Route::get('/payments/{id}', [PaymentController::class, 'getPaymentDetails']);
        Route::post('/refunds/{refundId}/approve', [PaymentController::class, 'approveRefund']);
        Route::post('/refunds/{refundId}/reject', [PaymentController::class, 'rejectRefund']);

        // ── Chat (Admin) ──
        Route::get('/chat/conversations', [ChatController::class, 'getAdminConversations']);
        Route::patch('/chat/{ticketId}/status', [ChatController::class, 'updateStatus']);
        Route::get('/chat/stats', [ChatController::class, 'getStats']);

        // ── Tax Rates (Admin) ──
        Route::get('/tax-rates', [TaxController::class, 'getTaxRates']);
        Route::get('/tax-rates/{id}', [TaxController::class, 'getTaxRate']);
        Route::post('/tax-rates', [TaxController::class, 'createTaxRate']);
        Route::put('/tax-rates/{id}', [TaxController::class, 'updateTaxRate']);
        Route::delete('/tax-rates/{id}', [TaxController::class, 'deleteTaxRate']);

        // ── Marketing (Admin) ──
        Route::get('/marketing/dashboard', [MarketingController::class, 'getDashboard']);
        Route::get('/marketing/subscribers/stats', [MarketingController::class, 'getSubscriberStats']);
        Route::get('/marketing/subscribers', [MarketingController::class, 'getSubscribers']);
        Route::get('/marketing/subscribers/export', [MarketingController::class, 'exportSubscribersCSV']);
        Route::post('/marketing/subscribers/import', [MarketingController::class, 'importSubscribersCSV']);
        Route::get('/marketing/subscribers/{id}', [MarketingController::class, 'getSubscriberById']);
        Route::post('/marketing/subscribers', [MarketingController::class, 'createSubscriber']);
        Route::put('/marketing/subscribers/{id}', [MarketingController::class, 'updateSubscriber']);
        Route::delete('/marketing/subscribers/{id}', [MarketingController::class, 'deleteSubscriber']);
        Route::get('/marketing/campaigns', [MarketingController::class, 'getCampaigns']);
        Route::get('/marketing/campaigns/{id}', [MarketingController::class, 'getCampaignById']);
        Route::post('/marketing/campaigns', [MarketingController::class, 'createCampaign']);
        Route::put('/marketing/campaigns/{id}', [MarketingController::class, 'updateCampaign']);
        Route::delete('/marketing/campaigns/{id}', [MarketingController::class, 'deleteCampaign']);
        Route::post('/marketing/campaigns/{id}/clone', [MarketingController::class, 'cloneCampaign']);
        Route::post('/marketing/campaigns/{id}/send', [MarketingController::class, 'sendCampaign']);
        Route::get('/marketing/campaigns/{id}/stats', [MarketingController::class, 'getCampaignStats']);
        Route::get('/marketing/campaigns/{id}/recipients', [MarketingController::class, 'getCampaignRecipients']);
        Route::get('/marketing/campaigns/{id}/recipients/export', [MarketingController::class, 'exportCampaignRecipientsCSV']);
        Route::post('/marketing/campaigns/from-template', [MarketingController::class, 'createCampaignFromTemplate']);

        // ── Campaign Templates (Admin) ──
        Route::get('/campaign-templates', [CampaignTemplateController::class, 'getTemplates']);
        Route::get('/campaign-templates/{id}', [CampaignTemplateController::class, 'getTemplateById']);
        Route::post('/campaign-templates', [CampaignTemplateController::class, 'createTemplate']);
        Route::put('/campaign-templates/{id}', [CampaignTemplateController::class, 'updateTemplate']);
        Route::delete('/campaign-templates/{id}', [CampaignTemplateController::class, 'deleteTemplate']);
        Route::post('/campaign-templates/seed-defaults', [CampaignTemplateController::class, 'getDefaultTemplates']);
        Route::post('/campaign-templates/render', [CampaignTemplateController::class, 'renderTemplate']);

        // ── Ad Campaigns (Admin) ──
        Route::get('/ads/stats', [AdCampaignController::class, 'getStats']);
        Route::get('/ads/analytics/performance', [AdCampaignController::class, 'getPerformanceReport']);
        Route::get('/ads/analytics/brand-presets', [AdCampaignController::class, 'getBrandPresetPerformance']);
        Route::get('/ads/analytics/budget-optimization', [AdCampaignController::class, 'getBudgetOptimization']);
        Route::get('/ads/analytics/templates', [AdCampaignController::class, 'getAdTemplates']);
        Route::get('/ads', [AdCampaignController::class, 'getCampaigns']);
        Route::get('/ads/{id}', [AdCampaignController::class, 'getCampaignById']);
        Route::post('/ads', [AdCampaignController::class, 'createCampaign']);
        Route::put('/ads/{id}', [AdCampaignController::class, 'updateCampaign']);
        Route::delete('/ads/{id}', [AdCampaignController::class, 'deleteCampaign']);
        Route::post('/ads/compare/{campaignId1}/{campaignId2}', [AdCampaignController::class, 'compareCampaigns']);
        Route::post('/ads/ai/generate-copy', [AdCampaignController::class, 'generateAdCopy']);
        Route::post('/ads/ai/generate-variants', [AdCampaignController::class, 'generateAdVariants']);
        Route::post('/ads/ai/generate-strategy', [AdCampaignController::class, 'generateFullStrategy']);
        Route::post('/ads/ai/suggest-audience', [AdCampaignController::class, 'suggestAudience']);
        Route::post('/ads/ai/generate-banner', [AdCampaignController::class, 'generateBannerDesign']);
        Route::get('/ads/{id}/products', [AdCampaignController::class, 'getCampaignProducts']);
        Route::post('/ads/{id}/products', [AdCampaignController::class, 'linkProduct']);
        Route::put('/ads/{id}/products/{productId}', [AdCampaignController::class, 'updateProductLink']);
        Route::delete('/ads/{id}/products/{productId}', [AdCampaignController::class, 'unlinkProduct']);
        Route::post('/ads/{id}/products/bulk', [AdCampaignController::class, 'bulkLinkProducts']);
        Route::post('/ads/{id}/products/{productId}/generate-creative', [AdCampaignController::class, 'generateCreativeFromProduct']);
        Route::post('/ads/test-meta-connection', [AdCampaignController::class, 'testMetaConnection']);
        Route::post('/ads/test-google-connection', [AdCampaignController::class, 'testGoogleConnection']);
        Route::get('/ads/whatsapp-recipients', [AdCampaignController::class, 'getWhatsAppRecipients']);
        Route::post('/ads/{id}/push-meta', [AdCampaignController::class, 'pushToMeta']);
        Route::post('/ads/{id}/sync-stats', [AdCampaignController::class, 'syncMetaStats']);
        Route::post('/ads/{id}/push-google', [AdCampaignController::class, 'pushToGoogle']);
        Route::post('/ads/{id}/sync-google-stats', [AdCampaignController::class, 'syncGoogleStats']);
        Route::post('/ads/{id}/push-whatsapp', [AdCampaignController::class, 'pushToWhatsApp']);

        // ── AI Routes (Admin) ──
        Route::post('/ai/generate-product-description', [AIController::class, 'generateProductDescription']);
        Route::post('/ai/generate-short-description', [AIController::class, 'generateShortDescription']);
        Route::post('/ai/generate-seo-meta', [AIController::class, 'generateSeoMeta']);
        Route::post('/ai/generate-image', [AIController::class, 'generateImage']);
        Route::post('/ai/generate-category-description', [AIController::class, 'generateCategoryDescription']);
        Route::post('/ai/generate-variant-description', [AIController::class, 'generateVariantDescription']);
        Route::post('/ai/generate-variant-images', [AIController::class, 'generateVariantImages']);
        Route::post('/ai/generate-variant-images-stream', [AIController::class, 'generateVariantImagesStream']);
        Route::post('/ai/test-connection', [AIController::class, 'testConnection']);
        Route::post('/ai/generate-page-content', [AIController::class, 'generatePageContent']);

        // ── Email Templates (Admin) ──
        Route::get('/email-templates', [EmailTemplateController::class, 'listTemplates']);
        Route::get('/email-templates/{id}', [EmailTemplateController::class, 'getTemplate']);
        Route::put('/email-templates/{id}', [EmailTemplateController::class, 'updateTemplate']);
        Route::patch('/email-templates/{id}/toggle', [EmailTemplateController::class, 'toggleTemplate']);
        Route::get('/email-templates/{id}/preview', [EmailTemplateController::class, 'previewTemplate']);
        Route::post('/email-templates/{id}/test', [EmailTemplateController::class, 'sendTestEmail']);
        Route::post('/email-templates/{id}/preview-html', [EmailTemplateController::class, 'previewTemplate']);

        // ── Wallet Admin Routes ──
        Route::get('/wallets', [WalletController::class, 'adminIndex']);
        Route::post('/wallets/adjust', [WalletController::class, 'adjust']);
        Route::get('/wallets/{userId}', [WalletController::class, 'adminShow']);

        // ── Loyalty Admin Routes ──
        Route::get('/loyalty/all', [LoyaltyController::class, 'adminIndex']);
        Route::post('/loyalty/adjust', [LoyaltyController::class, 'adjust']);
        Route::get('/loyalty/{userId}', [LoyaltyController::class, 'adminShow']);

        // ── Refund Admin Routes ──
        Route::get('/refund-requests', [RefundController::class, 'adminIndex']);
        Route::post('/refund-requests/{id}/approve', [RefundController::class, 'approve']);
        Route::post('/refund-requests/{id}/reject', [RefundController::class, 'reject']);

        // ── Return Admin Routes ──
        Route::get('/return-requests', [ReturnController::class, 'adminIndex']);
        Route::post('/return-requests/{id}/approve', [ReturnController::class, 'approve']);
        Route::post('/return-requests/{id}/reject', [ReturnController::class, 'reject']);
        Route::post('/return-requests/{id}/complete', [ReturnController::class, 'complete']);
        Route::get('/refunds/all', [ReturnController::class, 'allRefunds']);

        // ── SMS Admin Routes ──
        Route::get('/sms/health', [SMSController::class, 'health']);
        Route::post('/sms/send', [SMSController::class, 'send']);

        // ── Reels (Admin) ──
        Route::get('/reels', [ReelController::class, 'adminIndex']);
        Route::get('/reels/{id}', [ReelController::class, 'show']);
        Route::post('/reels', [ReelController::class, 'store']);
        Route::put('/reels/{id}', [ReelController::class, 'update']);
        Route::patch('/reels/{id}/toggle', [ReelController::class, 'toggleStatus']);
        Route::patch('/reels/reorder', [ReelController::class, 'reorder']);
        Route::delete('/reels/{id}', [ReelController::class, 'destroy']);

        // ── Curated Looks (Admin) ──
        Route::get('/curated-looks', [CuratedLookController::class, 'adminIndex']);
        Route::get('/curated-looks/{id}', [CuratedLookController::class, 'show']);
        Route::post('/curated-looks', [CuratedLookController::class, 'store']);
        Route::put('/curated-looks/{id}', [CuratedLookController::class, 'update']);
        Route::delete('/curated-looks/{id}', [CuratedLookController::class, 'destroy']);
        Route::patch('/curated-looks/reorder', [CuratedLookController::class, 'reorder']);
        Route::post('/curated-looks/{id}/products', [CuratedLookController::class, 'syncProducts']);

        // ── Scheduler Admin Routes (controller-based for route:cache compatibility) ──
        Route::post('/scheduler/backup', [\App\Http\Controllers\Api\SchedulerController::class, 'backup']);
        Route::post('/scheduler/ads', [\App\Http\Controllers\Api\SchedulerController::class, 'ads']);
        Route::post('/scheduler/campaigns', [\App\Http\Controllers\Api\SchedulerController::class, 'campaigns']);
        Route::post('/scheduler/maintenance', [\App\Http\Controllers\Api\SchedulerController::class, 'maintenance']);

        // ── Queue Monitoring (Admin) ──
        Route::get('/queue/failed-jobs', [AdminController::class, 'failedJobs']);
        Route::post('/queue/retry/{uuid}', [AdminController::class, 'retryFailedJob']);
        Route::post('/queue/retry-all', [AdminController::class, 'retryAllFailedJobs']);
        Route::delete('/queue/flush-failed', [AdminController::class, 'flushFailedJobs']);
    });

});

// ── Sitemap XML (outside v1 prefix, at root) ──
Route::get('/sitemap.xml', function () {
    $url = url('/');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $xml .= "  <url><loc>{$url}</loc><priority>1.0</priority></url>\n";
    $xml .= "  <url><loc>{$url}/products</loc><priority>0.9</priority></url>\n";
    $xml .= "  <url><loc>{$url}/categories</loc><priority>0.8</priority></url>\n";
    $xml .= '</urlset>';
    return response($xml, 200)->header('Content-Type', 'application/xml');
});

Route::get('/', function () {
    return response()->json(['message' => 'E-Commerce API v1', 'version' => '1.0.0']);
});

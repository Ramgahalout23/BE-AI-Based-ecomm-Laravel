<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function __construct(
        protected AdminService $adminService,
        protected SettingsService $settingsService
    ) {
        $this->middleware('auth')->except(['login', 'loginPost']);
        $this->middleware('admin')->except(['login', 'loginPost']);
    }

    // ── Dashboard ──
    public function dashboard()
    {
        $dashboard = $this->adminService->getDashboard();

        return view('admin.dashboard', [
            'totalRevenue' => $dashboard['totalRevenue'] ?? 0,
            'totalOrders' => $dashboard['totalOrders'] ?? 0,
            'totalUsers' => $dashboard['totalUsers'] ?? 0,
            'totalProducts' => $dashboard['totalProducts'] ?? 0,
            'pendingOrders' => $dashboard['pendingOrders'] ?? 0,
            'recentOrders' => $dashboard['recentOrders'] ?? collect(),
        ]);
    }

    // ── Products ──
    public function productsIndex(Request $request)
    {
        $products = $this->adminService->getProducts($request->only(['search', 'status']));
        return view('admin.products.index', compact('products'));
    }

    public function productsCreate()
    {
        $data = $this->adminService->getProductFormData();
        return view('admin.products.form', [
            'categories' => $data['categories'],
            'brands' => $data['brands'],
        ]);
    }

    public function productsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'quantity' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            'is_featured' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
        ]);

        $product = $this->adminService->createProduct($validated);
        return redirect()->route('admin.products.show', $product->id)
            ->with('success', 'Product created successfully');
    }

    public function productsShow(string $id)
    {
        $product = $this->adminService->getProductModel($id);
        return view('admin.products.show', compact('product'));
    }

    public function productsEdit(string $id)
    {
        $product = $this->adminService->getProductModel($id);
        $data = $this->adminService->getProductFormData();
        return view('admin.products.form', [
            'product' => $product,
            'categories' => $data['categories'],
            'brands' => $data['brands'],
        ]);
    }

    public function productsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $id,
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'quantity' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            'is_featured' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
        ]);

        $this->adminService->updateProduct($id, $validated);
        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully');
    }

    public function productsDestroy(string $id)
    {
        $this->adminService->deleteProduct($id);
        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully');
    }

    // ── Orders ──
    public function ordersIndex(Request $request)
    {
        $orders = $this->adminService->getOrders($request->only(['status', 'search']));
        return view('admin.orders.index', compact('orders'));
    }

    public function ordersShow(string $id)
    {
        $order = $this->adminService->getOrderModel($id);
        return view('admin.orders.show', compact('order'));
    }

    public function ordersUpdateStatus(Request $request, string $id)
    {
        $request->validate(['status' => 'required|string']);
        $this->adminService->updateOrderStatus($id, $request->status);
        return redirect()->route('admin.orders.show', $id)
            ->with('success', "Order status updated to {$request->status}");
    }

    // ── Users ──
    public function usersIndex(Request $request)
    {
        $users = $this->adminService->getUsers($request->only(['search', 'role']));
        return view('admin.users.index', compact('users'));
    }

    public function usersShow(string $id)
    {
        $user = $this->adminService->getUserModel($id);
        return view('admin.users.show', compact('user'));
    }

    public function usersManage(Request $request, string $id)
    {
        $this->adminService->manageUser($id, $request->only(['role', 'is_active', 'is_blocked']));
        return redirect()->route('admin.users.show', $id)
            ->with('success', 'User updated successfully');
    }

    // ── Coupons ──
    public function couponsIndex()
    {
        $coupons = $this->adminService->getCoupons();
        return view('admin.coupons.index', compact('coupons'));
    }

    public function couponsCreate()
    {
        return view('admin.coupons.form');
    }

    public function couponsStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'nullable|string|in:PERCENTAGE,FIXED,FREE_SHIPPING',
            'discount_type' => 'nullable|string',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_per_user' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $this->adminService->createCoupon($validated);
        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully');
    }

    public function couponsEdit(string $id)
    {
        $coupon = $this->adminService->getCouponById($id);
        abort_unless($coupon, 404, 'Coupon not found');
        return view('admin.coupons.form', compact('coupon'));
    }

    public function couponsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code,' . $id,
            'type' => 'nullable|string|in:PERCENTAGE,FIXED,FREE_SHIPPING',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->updateCoupon($id, $validated);
        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully');
    }

    public function couponsDestroy(string $id)
    {
        $this->adminService->deleteCoupon($id);
        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully');
    }

    // ── Categories ──
    public function categoriesIndex()
    {
        $categories = $this->adminService->getCategories();
        return view('admin.categories.index', compact('categories'));
    }

    public function categoriesCreate()
    {
        return view('admin.categories.form');
    }

    public function categoriesStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->createCategory($validated);
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully');
    }

    public function categoriesEdit(string $id)
    {
        $category = $this->adminService->getCategoryById($id);
        abort_unless($category, 404, 'Category not found');
        $categories = $this->adminService->getCategories();
        return view('admin.categories.form', compact('category', 'categories'));
    }

    public function categoriesUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->updateCategory($id, $validated);
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully');
    }

    public function categoriesDestroy(string $id)
    {
        $this->adminService->deleteCategory($id);
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully');
    }

    // ── Brands ──
    public function brandsIndex()
    {
        $brands = $this->adminService->getBrands();
        return view('admin.brands.index', compact('brands'));
    }

    public function brandsCreate()
    {
        return view('admin.brands.form');
    }

    public function brandsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $this->adminService->createBrand($validated);
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand created successfully');
    }

    public function brandsEdit(string $id)
    {
        $brand = $this->adminService->getBrandById($id);
        abort_unless($brand, 404, 'Brand not found');
        return view('admin.brands.form', compact('brand'));
    }

    public function brandsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $this->adminService->updateBrand($id, $validated);
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand updated successfully');
    }

    public function brandsDestroy(string $id)
    {
        $this->adminService->deleteBrand($id);
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand deleted successfully');
    }

    // ── Banners ──
    public function bannersIndex()
    {
        $banners = $this->adminService->getBanners();
        return view('admin.banners.index', compact('banners'));
    }

    public function bannersCreate()
    {
        return view('admin.banners.form');
    }

    public function bannersStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|string',
            'link_url' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'position' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->createBanner($validated);
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully');
    }

    public function bannersEdit(string $id)
    {
        $banner = $this->adminService->getBannerById($id);
        abort_unless($banner, 404, 'Banner not found');
        return view('admin.banners.form', compact('banner'));
    }

    public function bannersUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|string',
            'link_url' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'position' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->updateBanner($id, $validated);
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully');
    }

    public function bannersDestroy(string $id)
    {
        $this->adminService->deleteBanner($id);
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully');
    }

    // ── Reviews ──
    public function reviewsIndex(Request $request)
    {
        $reviews = $this->adminService->getReviews($request->only(['is_moderated', 'rating']));
        return view('admin.reviews.index', compact('reviews'));
    }

    public function reviewsModerate(Request $request, string $id)
    {
        $request->validate(['action' => 'required|in:approve,reject']);
        $this->adminService->moderateReview($id, $request->action === 'approve');
        return redirect()->route('admin.reviews.index')
            ->with('success', 'Review moderated successfully');
    }

    // ── Tickets ──
    public function ticketsIndex(Request $request)
    {
        $tickets = $this->adminService->getTickets($request->only(['status', 'priority']));
        return view('admin.tickets.index', compact('tickets'));
    }

    public function ticketsShow(string $id)
    {
        $ticket = $this->adminService->getTicketModel($id);
        return view('admin.tickets.show', compact('ticket'));
    }

    public function ticketsUpdateStatus(Request $request, string $id)
    {
        $request->validate(['status' => 'required|string']);
        $this->adminService->updateTicketStatus($id, $request->status);
        return redirect()->route('admin.tickets.show', $id)
            ->with('success', 'Ticket status updated');
    }

    // ── Promotions ──
    public function promotionsIndex()
    {
        $promotions = $this->adminService->getPromotions();
        return view('admin.promotions.index', compact('promotions'));
    }

    public function promotionsCreate()
    {
        return view('admin.promotions.form');
    }

    public function promotionsStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $this->adminService->createPromotion($validated);
        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion created successfully');
    }

    public function promotionsEdit(string $id)
    {
        $promotion = $this->adminService->getPromotionById($id);
        abort_unless($promotion, 404, 'Promotion not found');
        return view('admin.promotions.form', compact('promotion'));
    }

    public function promotionsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $this->adminService->updatePromotion($id, $validated);
        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion updated successfully');
    }

    public function promotionsDestroy(string $id)
    {
        $this->adminService->deletePromotion($id);
        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion deleted successfully');
    }

    // ── Abandoned Carts ──
    public function abandonedCartsIndex()
    {
        $carts = $this->adminService->getAbandonedCarts();
        $stats = $this->adminService->getAbandonedCartStats();
        return view('admin.abandoned-carts.index', compact('carts', 'stats'));
    }

    public function abandonedCartsShow(string $id)
    {
        $cart = $this->adminService->getAbandonedCartModel($id);
        return view('admin.abandoned-carts.show', compact('cart'));
    }

    // ── Inventory ──
    public function inventoryIndex()
    {
        $inventory = $this->adminService->getInventory();
        return view('admin.inventory.index', compact('inventory'));
    }

    public function inventoryShow(string $productId)
    {
        $inventory = $this->adminService->getInventoryByProduct($productId);
        abort_unless($inventory, 404, 'Inventory not found');
        $movement = $this->adminService->getInventoryMovement($productId);
        return view('admin.inventory.show', compact('inventory', 'movement'));
    }

    public function inventoryAddStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $this->adminService->addStock($validated['product_id'], $validated['quantity'], $validated['reason'] ?? '');
        return redirect()->route('admin.inventory.index')
            ->with('success', 'Stock added successfully');
    }

    public function inventoryReduceStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        try {
            $this->adminService->reduceStock($validated['product_id'], $validated['quantity'], $validated['reason'] ?? '');
            return redirect()->route('admin.inventory.index')
                ->with('success', 'Stock reduced successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function inventoryMovement(string $productId)
    {
        $movement = $this->adminService->getInventoryMovement($productId);
        $product = \App\Models\Product::find($productId);
        abort_unless($product, 404, 'Product not found');
        return view('admin.inventory.movement', compact('movement', 'product'));
    }

    // ── Low Stock Products ──
    public function lowStockProducts()
    {
        $products = $this->adminService->getLowStockProducts();
        return view('admin.products.low-stock', compact('products'));
    }

    // ── Product Variants ──
    public function variantsByProduct(string $productId)
    {
        $product = $this->adminService->getProductModel($productId);
        $variants = $this->adminService->getVariantsByProduct($productId);
        return view('admin.variants.index', compact('product', 'variants'));
    }

    public function variantsStore(Request $request, string $productId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:product_variants,sku',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
        ]);
        $validated['product_id'] = $productId;

        $this->adminService->createVariant($validated);
        return redirect()->route('admin.variants.by-product', $productId)
            ->with('success', 'Variant created successfully');
    }

    public function variantsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:product_variants,sku,' . $id,
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $this->adminService->updateVariant($id, $validated);
        return redirect()->back()->with('success', 'Variant updated successfully');
    }

    public function variantsDestroy(string $id)
    {
        $this->adminService->deleteVariant($id);
        return redirect()->back()->with('success', 'Variant deleted successfully');
    }

    // ── Notifications ──
    public function notificationsIndex()
    {
        $notifications = $this->adminService->getNotifications();
        return view('admin.notifications.index', compact('notifications'));
    }

    // ── Settings ──
    public function settings()
    {
        return view('admin.settings.index');
    }

    public function settingsUpdate(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_email' => 'nullable|email',
            'currency' => 'nullable|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->adminService->updateSettings($validated);
        return redirect()->route('admin.settings')
            ->with('success', 'Settings saved successfully');
    }

    // ── Login / Logout ──
    public function login()
    {
        return view('admin.login');
    }

    public function loginPost(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            Auth::logout();
            return back()->with('error', 'Unauthorized access. Admin privileges required.');
        }

        return back()->with('error', 'Invalid credentials');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('admin.login');
    }
}

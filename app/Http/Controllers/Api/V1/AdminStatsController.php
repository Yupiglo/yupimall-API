<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $allowedRoles = ['admin', 'webmaster', 'dev'];
        $hasAccess = in_array($user?->role ?? 'user', $allowedRoles);
        if (!$hasAccess) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $usersCount = User::query()->count();
        $productsCount = Product::query()->count();
        $ordersCount = Order::query()->count();
        $revenueTotal = (float) Order::query()->where('is_paid', true)->sum('total_order_price');

        // Orders by status
        $ordersPending = Order::query()->where('order_status', 'pending')->count();
        $ordersProcessing = Order::query()->where('order_status', 'processing')->count();
        $ordersShipped = Order::query()->where('order_status', 'shipped')->count();
        $ordersDelivered = Order::query()->where('order_status', 'delivered')->count();
        $ordersPaid = Order::query()->where('is_paid', true)->count();

        // Products stats
        $productsOutOfStock = Product::query()->where('quantity', 0)->count();
        $productsSold = (int) Product::query()->sum('sold');

        // Users stats
        $newUsersToday = User::query()->whereDate('created_at', today())->count();

        $activeNow = (int) DB::table('personal_access_tokens')
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', now()->subMinutes(15))
            ->count();

        $recentOrders = Order::query()
            ->with(['user'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => (string) $order->id,
                    'total' => (float) $order->total_order_price,
                    'status' => (string) $order->order_status,
                    'createdAt' => optional($order->created_at)->toISOString(),
                    'user' => [
                        'id' => (string) $order->user_id,
                        'name' => (string) ($order->user?->name ?? ''),
                        'email' => (string) ($order->user?->email ?? ''),
                    ],
                ];
            })
            ->values();

        // Top Customers (by total spent)
        $topCustomers = User::query()
            ->select('users.*')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.is_paid', true)
            ->selectRaw('SUM(orders.total_order_price) as total_spent, COUNT(orders.id) as orders_count')
            ->groupBy('users.id')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => (string) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'totalSpent' => (float) $user->total_spent,
                    'ordersCount' => (int) $user->orders_count,
                    'image' => $user->image_url ?? null,
                ];
            });

        // Team Members (admins/staff)
        $teamMembers = User::query()
            ->whereIn('role', ['admin', 'webmaster', 'dev', 'warehouse'])
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => (string) $user->id,
                    'name' => (string) $user->name,
                    'role' => (string) $user->role,
                    'image' => $user->image_url ?? null,
                ];
            });

        // Recent Deliveries
        $recentDeliveries = Order::query()
            ->whereIn('order_status', ['shipped', 'delivered', 'in_transit'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => (string) $order->id,
                    'status' => (string) $order->order_status,
                    'updatedAt' => optional($order->updated_at)->diffForHumans(),
                    'trackingCode' => (string) ($order->tracking_code ?? ''),
                ];
            });

        return response()->json([
            'message' => 'success',
            'stats' => [
                'revenueTotal' => $revenueTotal,
                'usersCount' => $usersCount,
                'productsCount' => $productsCount,
                'ordersCount' => $ordersCount,
                'ordersPending' => $ordersPending,
                'ordersProcessing' => $ordersProcessing,
                'ordersShipped' => $ordersShipped,
                'ordersDelivered' => $ordersDelivered,
                'ordersPaid' => $ordersPaid,
                'productsOutOfStock' => $productsOutOfStock,
                'productsSold' => $productsSold,
                'newUsersToday' => $newUsersToday,
                'activeNow' => $activeNow,
            ],
            'recentOrders' => $recentOrders,
            'topCustomers' => $topCustomers,
            'teamMembers' => $teamMembers,
            'recentDeliveries' => $recentDeliveries,
        ], 200);
    }

    /**
     * Get sales aggregated by country
     */
    public function salesByCountry(Request $request)
    {
        $user = $request->user();
        $allowedRoles = ['admin', 'webmaster', 'dev'];
        if (!in_array($user?->role ?? 'user', $allowedRoles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Get orders grouped by country entitiy
        $salesByCountry = Order::query()
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->join('countries', 'users.country_id', '=', 'countries.id')
            ->where('orders.is_paid', true)
            ->selectRaw('countries.name as country, COUNT(orders.id) as orders_count, SUM(orders.total_order_price) as total_revenue')
            ->groupBy('countries.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($row) {
                return [
                    'country' => $row->country ?? 'Unknown',
                    'ordersCount' => (int) $row->orders_count,
                    'totalRevenue' => (float) $row->total_revenue,
                ];
            });

        return response()->json([
            'message' => 'success',
            'salesByCountry' => $salesByCountry,
        ], 200);
    }
}

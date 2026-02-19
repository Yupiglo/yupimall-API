<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationalStatsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        if ($role === 'warehouse') {
            return $this->getWarehouseStats($user);
        }

        if ($role === 'stockist') {
            return $this->getStockistStats($user);
        }

        return response()->json(['message' => 'Unauthorized role for operational stats'], 403);
    }

    private function getWarehouseStats($user)
    {
        // Get the country name from the country relationship
        $countryName = $user->country?->name;

        if (!$countryName) {
            return response()->json([
                'status' => 400,
                'message' => 'User country not configured. Please contact an administrator.',
            ], 400);
        }

        // Base query for orders in this warehouse's country
        $orderQuery = Order::query()->where('shipping_country', $countryName);

        $stats = [
            'totalOrders' => $orderQuery->count(),
            'pendingOrders' => (clone $orderQuery)->where('order_status', 'pending')->count(),
            'validatedOrders' => (clone $orderQuery)->where('order_status', 'validated')->count(),
            'revenue' => (float) (clone $orderQuery)->where('is_paid', true)->sum('total_order_price'),
            'totalStockists' => User::where('role', 'stockist')->whereHas('country', fn($q) => $q->where('name', $countryName))->count(),
            'totalConsumers' => User::where('role', 'consumer')->whereHas('country', fn($q) => $q->where('name', $countryName))->count(),
        ];

        $recentOrders = (clone $orderQuery)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map($this->formatOrder());

        $recentDeliveries = (clone $orderQuery)
            ->whereIn('order_status', ['reached_warehouse', 'shipped_to_stockist', 'reached_stockist', 'delivered'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map($this->formatDelivery());

        // Top 5 Customers (users with most orders) - exclude admin and warehouse roles
        $excludedRoles = ['dev', 'webmaster', 'warehouse'];
        $topCustomers = User::select('users.*', DB::raw('COUNT(orders.id) as order_count'), DB::raw('SUM(orders.total_order_price) as total_spent'))
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.shipping_country', $countryName)
            ->whereNotIn('users.role', $excludedRoles)
            ->groupBy('users.id')
            ->orderByDesc('order_count')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'title' => $u->name,
                'subtitle' => ($u->order_count ?? 0) . ' commandes',
                'value' => number_format($u->total_spent ?? 0, 0, ',', ' ') . ' CFA',
                'image' => $u->image_url,
            ]);

        // Top 5 Couriers (delivery personnel with most deliveries)
        $topCouriers = User::select('users.*', DB::raw('COUNT(orders.id) as delivery_count'))
            ->leftJoin('orders', 'users.id', '=', 'orders.delivery_person_id')
            ->where('users.role', 'delivery')
            ->where('orders.order_status', 'delivered')
            ->groupBy('users.id')
            ->orderByDesc('delivery_count')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'title' => $u->name,
                'subtitle' => ($u->delivery_count ?? 0) . ' livraisons',
                'value' => 'Actif',
                'image' => $u->image_url,
            ]);

        // Recent Registrations
        $recentRegistrations = \App\Models\Registration::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->first_name . ' ' . $r->last_name,
                'subtitle' => $r->email,
                'badge' => ucfirst($r->status),
                'badgeColor' => $r->status === 'approved' ? 'success' : ($r->status === 'pending' ? 'warning' : 'error'),
            ]);

        // Orders by Status for Chart
        $ordersByStatus = [
            ['label' => 'En attente', 'quantity' => (clone $orderQuery)->where('order_status', 'pending')->count(), 'color' => '#fb923c'],
            ['label' => 'Validées', 'quantity' => (clone $orderQuery)->where('order_status', 'validated')->count(), 'color' => '#0c24ff'],
            ['label' => 'Au Warehouse', 'quantity' => (clone $orderQuery)->where('order_status', 'reached_warehouse')->count(), 'color' => '#8f1cd2'],
            ['label' => 'En transit', 'quantity' => (clone $orderQuery)->where('order_status', 'shipped_to_stockist')->count(), 'color' => '#707ce5'],
            ['label' => 'Chez Stockist', 'quantity' => (clone $orderQuery)->where('order_status', 'reached_stockist')->count(), 'color' => '#00b230'],
            ['label' => 'Livrées', 'quantity' => (clone $orderQuery)->where('order_status', 'delivered')->count(), 'color' => '#22c55e'],
        ];

        return response()->json([
            'status' => 200,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'recentDeliveries' => $recentDeliveries,
            'topCustomers' => $topCustomers,
            'topCouriers' => $topCouriers,
            'recentRegistrations' => $recentRegistrations,
            'ordersByStatus' => $ordersByStatus,
        ]);
    }

    private function getStockistStats($user)
    {
        // For a stockist, orders assigned to them (stockist column stores user id)
        $orderQuery = Order::query()->where('stockist', $user->id);

        $stats = [
            'totalOrders' => $orderQuery->count(),
            'pendingPickup' => (clone $orderQuery)->where('order_status', 'reached_stockist')->count(),
            'delivered' => (clone $orderQuery)->where('order_status', 'delivered')->count(),
            'revenue' => (float) (clone $orderQuery)->where('is_paid', true)->sum('total_order_price'),
            'myCustomers' => User::where('supervisor_id', $user->id)->count(),
        ];

        $recentOrders = (clone $orderQuery)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map($this->formatOrder());

        // Orders by Status for Chart
        $ordersByStatus = [
            ['label' => 'En attente', 'quantity' => (clone $orderQuery)->where('order_status', 'pending')->count(), 'color' => '#fb923c'],
            ['label' => 'Validées', 'quantity' => (clone $orderQuery)->where('order_status', 'validated')->count(), 'color' => '#0c24ff'],
            ['label' => 'Chez Stockist', 'quantity' => (clone $orderQuery)->where('order_status', 'reached_stockist')->count(), 'color' => '#00b230'],
            ['label' => 'Livrées', 'quantity' => (clone $orderQuery)->where('order_status', 'delivered')->count(), 'color' => '#22c55e'],
        ];

        return response()->json([
            'status' => 200,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'ordersByStatus' => $ordersByStatus,
        ]);
    }

    private function formatOrder()
    {
        return function (Order $order) {
            return [
                'id' => $order->id,
                'trackingCode' => $order->tracking_code,
                'total' => (float) $order->total_order_price,
                'status' => $order->order_status,
                'createdAt' => $order->created_at->toISOString(),
                'userName' => $order->user?->name ?? 'Guest',
            ];
        };
    }

    private function formatDelivery()
    {
        return function (Order $order) {
            return [
                'id' => $order->id,
                'trackingCode' => $order->tracking_code,
                'status' => $order->order_status,
                'updatedAt' => $order->updated_at->diffForHumans(),
            ];
        };
    }
}

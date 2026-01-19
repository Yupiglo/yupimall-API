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
        $country = $user->country;

        // Base query for orders in this warehouse's country
        $orderQuery = Order::query()->where('shipping_country', $country);

        $stats = [
            'totalOrders' => $orderQuery->count(),
            'pendingOrders' => (clone $orderQuery)->where('order_status', 'pending')->count(),
            'validatedOrders' => (clone $orderQuery)->where('order_status', 'validated')->count(),
            'revenue' => (float) (clone $orderQuery)->where('is_paid', true)->sum('total_order_price'),
            'totalStockists' => User::where('role', 'stockist')->where('country', $country)->count(),
            'totalConsumers' => User::where('role', 'consumer')->where('country', $country)->count(),
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

        return response()->json([
            'status' => 200,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'recentDeliveries' => $recentDeliveries,
        ]);
    }

    private function getStockistStats($user)
    {
        // For a stockist, orders assigned to them or created by them (if that's the logic)
        // Usually, orders where stockist_id = user->id
        $orderQuery = Order::query()->where('stockist_id', $user->id);

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

        return response()->json([
            'status' => 200,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
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

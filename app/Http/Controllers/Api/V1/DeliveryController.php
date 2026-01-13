<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Get all delivery personnel (users with role 'delivery')
     */
    public function personnel(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 50);

        if ($limit <= 0) {
            $limit = 50;
        }

        $query = User::query()->where('role', User::ROLE_DELIVERY);

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        // Get active delivery counts for each personnel
        $personnelIds = collect($paginator->items())->pluck('id');
        $activeDeliveryCounts = Order::whereIn('delivery_person_id', $personnelIds)
            ->where('order_status', 'In Transit')
            ->selectRaw('delivery_person_id, COUNT(*) as count')
            ->groupBy('delivery_person_id')
            ->pluck('count', 'delivery_person_id');

        $personnel = collect($paginator->items())->map(function ($user) use ($activeDeliveryCounts) {
            $user->active_deliveries = $activeDeliveryCounts[$user->id] ?? 0;
            return $user;
        });

        return response()->json([
            'page' => $page,
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'message' => 'success',
            'personnel' => $personnel,
        ], 200);
    }

    /**
     * Get active deliveries (orders in transit with delivery person info)
     */
    public function activeDeliveries(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 50);

        if ($limit <= 0) {
            $limit = 50;
        }

        $query = Order::query()
            ->whereIn('order_status', ['In Transit', 'Pending', 'Processing'])
            ->with(['user', 'deliveryPerson', 'items.product']);

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_code', 'LIKE', "%{$search}%")
                    ->orWhere('shipping_name', 'LIKE', "%{$search}%")
                    ->orWhere('shipping_city', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('order_status', $request->input('status'));
        }

        // Filter by delivery person
        if ($request->has('delivery_person_id')) {
            $query->where('delivery_person_id', $request->input('delivery_person_id'));
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'page' => $page,
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'message' => 'success',
            'deliveries' => $paginator->items(),
        ], 200);
    }

    /**
     * Assign a delivery person to an order
     */
    public function assignDeliveryPerson(Request $request, string $orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $deliveryPersonId = $request->input('delivery_person_id');

        if ($deliveryPersonId) {
            $deliveryPerson = User::where('id', $deliveryPersonId)
                ->where('role', User::ROLE_DELIVERY)
                ->first();

            if (!$deliveryPerson) {
                return response()->json(['message' => 'Delivery person not found or invalid role'], 404);
            }
        }

        $order->delivery_person_id = $deliveryPersonId;
        if ($deliveryPersonId && $order->order_status === 'Pending') {
            $order->order_status = 'Processing';
        }
        $order->save();

        return response()->json([
            'message' => 'Delivery person assigned successfully',
            'order' => $order->fresh(['deliveryPerson']),
        ], 200);
    }

    /**
     * Get delivery statistics
     */
    public function stats()
    {
        $totalPersonnel = User::where('role', User::ROLE_DELIVERY)->count();

        $pending = Order::where('order_status', 'Pending')->count();
        $inTransit = Order::where('order_status', 'In Transit')->count();
        $deliveredToday = Order::where('order_status', 'Delivered')
            ->whereDate('delivered_at', today())
            ->count();

        return response()->json([
            'message' => 'success',
            'stats' => [
                'totalPersonnel' => $totalPersonnel,
                'pending' => $pending,
                'inTransit' => $inTransit,
                'deliveredToday' => $deliveredToday,
            ],
        ], 200);
    }

    /**
     * Update delivery status
     */
    public function updateStatus(Request $request, string $orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $validStatuses = ['Pending', 'Processing', 'In Transit', 'Delivered', 'Cancelled'];
        $newStatus = $request->input('status');

        if (!in_array($newStatus, $validStatuses)) {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $order->order_status = $newStatus;

        if ($newStatus === 'Delivered') {
            $order->is_delivered = true;
            $order->delivered_at = now();
        }

        $order->save();

        return response()->json([
            'message' => 'Delivery status updated successfully',
            'order' => $order->fresh(['deliveryPerson']),
        ], 200);
    }
}

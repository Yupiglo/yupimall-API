<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'tracking_code' => $this->order->tracking_code,
            'user_name' => $this->order->user?->name ?? 'Client',
            'status' => $this->order->order_status,
            'country' => $this->order->shipping_country ?? $this->order->user?->country,
            'supervisor_id' => $this->order->user?->supervisor_id,
            'stockist_id' => $this->order->stockist,
            'updated_at' => $this->order->updated_at?->toISOString(),
        ];
    }
}

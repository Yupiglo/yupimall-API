<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Notification;
use App\Services\ActivityLogger;
use App\Events\OrderCreated;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::query()->with(['items.product', 'user']);

        if ($user->role === 'warehouse') {
            $countryName = $user->country?->name;
            if ($countryName) {
                $query->where('shipping_country', $countryName);
            }
        } elseif ($user->role === 'stockist') {
            $query->where('stockist', $user->id);
        }

        $orders = $query->orderByDesc('created_at')->get();

        return response()->json([
            'message' => 'success',
            'orders' => $orders->map(fn(Order $o) => $this->toNodeOrder($o))->values(),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $id)
    {
        $user = $request->user();

        $cart = Cart::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->with(['items'])
            ->first();
        if (!$cart) {
            return response()->json(['message' => 'Cart was not found'], 404);
        }

        $shipping = (array) $request->input('shippingAddress', []);
        $shippingName = $shipping['name'] ?? $request->input('shipping_name');
        $shippingStreet = $shipping['street'] ?? $request->input('shipping_street');
        $shippingCity = $shipping['city'] ?? $request->input('shipping_city');
        $shippingCountry = $shipping['country'] ?? $request->input('shipping_country');
        $shippingZip = $shipping['zip'] ?? $request->input('shipping_zip');
        $shippingPhone = $shipping['phone'] ?? $request->input('shipping_phone');
        $shippingEmail = $shipping['email'] ?? $request->input('shipping_email', $user->email);

        try {
            $order = DB::transaction(function () use ($user, $cart, $shippingName, $shippingStreet, $shippingCity, $shippingCountry, $shippingZip, $shippingPhone, $shippingEmail, $request) {
                $cart->loadMissing('items.product');

                // Validation de l'inventaire avant commande
                foreach ($cart->items as $cartItem) {
                    if (!$cartItem->product || $cartItem->product->quantity < $cartItem->quantity) {
                        throw new \Exception("Le produit '" . ($cartItem->product->title ?? 'Inconnu') . "' n'est plus en stock suffisant.");
                    }
                }

                $total = $cart->total_price_after_discount !== null ? (float) $cart->total_price_after_discount : (float) $cart->total_price;

                $order = Order::create([
                    'user_id' => $user->id,
                    'tracking_code' => Order::generateTrackingCode(),
                    'shipping_name' => $shippingName,
                    'shipping_street' => $shippingStreet,
                    'shipping_city' => $shippingCity,
                    'shipping_country' => $shippingCountry,
                    'shipping_zip' => $shippingZip,
                    'shipping_phone' => $shippingPhone,
                    'shipping_email' => $shippingEmail,
                    'distributor_id' => $request->input('distributorId'),
                    'stockist' => $request->input('stockist'),
                    'payment_method' => $request->input('paymentMethod', 'cash'),
                    'is_paid' => false,
                    'is_delivered' => false,
                    'order_status' => 'pending',
                    'total_order_price' => $total,
                    'order_at' => now(),
                ]);

                foreach ($cart->items as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'total_product_discount' => $cartItem->total_product_discount,
                    ]);

                    Product::query()->where('id', $cartItem->product_id)->update([
                        'quantity' => DB::raw('CASE WHEN quantity - ' . ((int) $cartItem->quantity) . ' > 0 THEN quantity - ' . ((int) $cartItem->quantity) . ' ELSE 0 END'),
                        'sold' => DB::raw('sold + ' . ((int) $cartItem->quantity)),
                    ]);
                }

                $cart->items()->delete();
                $cart->delete();

                return $order->fresh(['items.product']);
            });

            // Generate Payment Link
            $paymentService = new \App\Services\PaymentService();
            $redirectUrl = $paymentService->initializePayment($order);

            ActivityLogger::log(
                "Order Created",
                "User {$user->name} placed a new order #{$order->tracking_code} (Total: {$order->total_order_price}$)",
                "success",
                ['order_id' => $order->id, 'tracking_code' => $order->tracking_code]
            );

            return response()->json([
                'message' => 'success',
                'order' => $this->toNodeOrder($order),
                'payment_url' => $redirectUrl,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeFromUserCart(Request $request)
    {
        $user = $request->user();

        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->with(['items'])
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart was not found'], 404);
        }

        $shipping = (array) $request->input('shippingAddress', []);
        $shippingName = $shipping['name'] ?? $request->input('shipping_name');
        $shippingStreet = $shipping['street'] ?? $request->input('shipping_street');
        $shippingCity = $shipping['city'] ?? $request->input('shipping_city');
        $shippingCountry = $shipping['country'] ?? $request->input('shipping_country');
        $shippingZip = $shipping['zip'] ?? $request->input('shipping_zip');
        $shippingPhone = $shipping['phone'] ?? $request->input('shipping_phone');
        $shippingEmail = $shipping['email'] ?? $request->input('shipping_email', $user->email);

        try {
            $order = DB::transaction(function () use ($user, $cart, $shippingName, $shippingStreet, $shippingCity, $shippingCountry, $shippingZip, $shippingPhone, $shippingEmail, $request) {
                $cart->loadMissing('items');
                $total = $cart->total_price_after_discount !== null ? (float) $cart->total_price_after_discount : (float) $cart->total_price;

                $order = Order::create([
                    'user_id' => $user->id,
                    'tracking_code' => Order::generateTrackingCode(),
                    'shipping_name' => $shippingName,
                    'shipping_street' => $shippingStreet,
                    'shipping_city' => $shippingCity,
                    'shipping_country' => $shippingCountry,
                    'shipping_zip' => $shippingZip,
                    'shipping_phone' => $shippingPhone,
                    'shipping_email' => $shippingEmail,
                    'distributor_id' => $request->input('distributorId'),
                    'stockist' => $request->input('stockist'),
                    'payment_method' => $request->input('paymentMethod', 'cash'),
                    'is_paid' => false,
                    'is_delivered' => false,
                    'order_status' => 'processing',
                    'total_order_price' => $total,
                    'order_at' => now(),
                ]);

                // Create Notifications for various roles
                $notificationData = [
                    'category' => 'order',
                    'type' => 'success',
                    'metadata' => ['order_id' => $order->id, 'tracking_code' => $order->tracking_code]
                ];

                // 1. Notify the country Warehouse (if applicable)
                $orderCountry = $order->shipping_country ?? ($user->country ? $user->country->name : null);
                if ($orderCountry) {
                    // Find warehouse for this country
                    $warehouse = User::where('role', 'warehouse')
                        ->whereHas('country', function ($q) use ($orderCountry) {
                            $q->where('name', $orderCountry);
                        })->first();
                    if ($warehouse) {
                        Notification::create(array_merge($notificationData, [
                            'title' => 'Nouvelle commande ' . ($user->role === 'stockist' ? 'Stockiste' : 'Client'),
                            'message' => "Une nouvelle commande (#{$order->tracking_code}) est arrivée pour votre pays.",
                            'user_id' => $warehouse->id
                        ]));
                    }
                }

                // 2. Notify Admin/Dev/Webmaster (Global)
                Notification::create(array_merge($notificationData, [
                    'title' => 'Commande Reçue: ' . ($user->role === 'warehouse' ? 'Warehouse' : ($user->role === 'stockist' ? 'Stockist' : 'Client')),
                    'message' => "Commande #{$order->tracking_code} passée par {$user->name} ({$user->role}).",
                ]));

                foreach ($cart->items as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'total_product_discount' => $cartItem->total_product_discount,
                    ]);

                    Product::query()->where('id', $cartItem->product_id)->update([
                        'quantity' => DB::raw('CASE WHEN quantity - ' . ((int) $cartItem->quantity) . ' > 0 THEN quantity - ' . ((int) $cartItem->quantity) . ' ELSE 0 END'),
                        'sold' => DB::raw('sold + ' . ((int) $cartItem->quantity)),
                    ]);
                }

                $cart->items()->delete();
                $cart->delete();

                return $order->fresh(['items.product', 'user']);
            });

            // Generate Payment Link
            $paymentService = new \App\Services\PaymentService();
            $redirectUrl = $paymentService->initializePayment($order);

            ActivityLogger::log(
                "Order Created",
                "User {$user->name} placed order #{$order->tracking_code} from cart",
                "success",
                ['order_id' => $order->id, 'tracking_code' => $order->tracking_code]
            );

            // Broadcast event for real-time notifications
            broadcast(new OrderCreated($order))->toOthers();

            return response()->json([
                'message' => 'success',
                'order' => $this->toNodeOrder($order),
                'payment_url' => $redirectUrl,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Store a guest order (no authentication required)
     * Accepts cart items directly from localStorage
     */
    public function storeGuest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shippingAddress' => ['required', 'array'],
            'shippingAddress.name' => ['required', 'string', 'max:255'],
            'shippingAddress.email' => ['required', 'email'],
            'shippingAddress.phone' => ['nullable', 'string'],
            'shippingAddress.street' => ['nullable', 'string'],
            'shippingAddress.city' => ['nullable', 'string'],
            'shippingAddress.country' => ['required', 'string'],
            'stockist' => ['nullable', 'string'],
            'paymentMethod' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 400);
        }

        $items = $request->input('items');
        $shipping = $request->input('shippingAddress');

        try {
            $order = DB::transaction(function () use ($items, $shipping, $request) {
                // Validate stock availability
                $total = 0;
                $orderItems = [];

                foreach ($items as $item) {
                    $product = Product::find($item['product_id']);
                    if (!$product) {
                        throw new \Exception("Produit introuvable (ID: {$item['product_id']})");
                    }
                    if ($product->quantity < $item['quantity']) {
                        throw new \Exception("Stock insuffisant pour '{$product->title}'");
                    }

                    $itemTotal = $product->price * $item['quantity'];
                    $total += $itemTotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                        'total_product_discount' => 0,
                    ];
                }

                // Create the order without user_id (guest order)
                $order = Order::create([
                    'user_id' => null, // Guest order
                    'tracking_code' => Order::generateTrackingCode(),
                    'shipping_name' => $shipping['name'],
                    'shipping_street' => $shipping['street'] ?? null,
                    'shipping_city' => $shipping['city'] ?? null,
                    'shipping_country' => $shipping['country'],
                    'shipping_zip' => $shipping['zip'] ?? null,
                    'shipping_phone' => $shipping['phone'] ?? null,
                    'shipping_email' => $shipping['email'],
                    'distributor_id' => $request->input('distributorId'),
                    'stockist' => $request->input('stockist'),
                    'payment_method' => $request->input('paymentMethod', 'cash'),
                    'is_paid' => false,
                    'is_delivered' => false,
                    'order_status' => 'pending',
                    'total_order_price' => $total,
                    'order_at' => now(),
                ]);

                // Create order items and update stock
                foreach ($orderItems as $orderItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $orderItem['product_id'],
                        'quantity' => $orderItem['quantity'],
                        'price' => $orderItem['price'],
                        'total_product_discount' => $orderItem['total_product_discount'],
                    ]);

                    Product::where('id', $orderItem['product_id'])->update([
                        'quantity' => DB::raw('CASE WHEN quantity - ' . $orderItem['quantity'] . ' > 0 THEN quantity - ' . $orderItem['quantity'] . ' ELSE 0 END'),
                        'sold' => DB::raw('sold + ' . $orderItem['quantity']),
                    ]);
                }

                return $order->fresh(['items.product']);
            });

            // Notify warehouse
            $orderCountry = $order->shipping_country;
            if ($orderCountry) {
                $warehouse = User::where('role', 'warehouse')
                    ->whereHas('country', fn($q) => $q->where('name', $orderCountry))
                    ->first();

                if ($warehouse) {
                    Notification::create([
                        'title' => 'Nouvelle commande Invité',
                        'message' => "Une nouvelle commande invité (#{$order->tracking_code}) est arrivée.",
                        'category' => 'order',
                        'type' => 'success',
                        'user_id' => $warehouse->id,
                        'metadata' => ['order_id' => $order->id, 'tracking_code' => $order->tracking_code],
                    ]);
                }
            }

            // Global notification
            Notification::create([
                'title' => 'Commande Invité Reçue',
                'message' => "Commande invité #{$order->tracking_code} passée par {$shipping['name']}.",
                'category' => 'order',
                'type' => 'success',
                'metadata' => ['order_id' => $order->id, 'tracking_code' => $order->tracking_code],
            ]);

            ActivityLogger::log(
                "Guest Order Created",
                "Guest {$shipping['name']} placed order #{$order->tracking_code} (Total: {$order->total_order_price})",
                "success",
                ['order_id' => $order->id, 'tracking_code' => $order->tracking_code, 'guest_email' => $shipping['email']]
            );

            // Generate Payment Link
            $redirectUrl = null;
            try {
                $paymentService = new \App\Services\PaymentService();
                $redirectUrl = $paymentService->initializePayment($order);
            } catch (\Exception $e) {
                // Payment init failed, but order is still created
            }

            return response()->json([
                'message' => 'Commande créée avec succès',
                'tracking_code' => $order->tracking_code,
                'order' => $this->toNodeOrder($order),
                'payment_url' => $redirectUrl,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.product'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'success',
            'orders' => $orders->map(fn(Order $o) => $this->toNodeOrder($o))->values(),
        ], 200);
    }

    /**
     * Display a single order by ID (for admin panels)
     */
    public function showOne(Request $request, string $id)
    {
        $user = $request->user();
        $query = Order::query()->with(['items.product', 'user']);

        // Data segregation based on role
        if ($user->role === 'warehouse') {
            $countryName = $user->country?->name;
            if ($countryName) {
                $query->where('shipping_country', $countryName);
            }
        } elseif ($user->role === 'stockist') {
            $query->where('stockist', $user->id);
        }

        $order = $query->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Format order for frontend
        $formattedOrder = [
            'id' => $order->id,
            '_id' => (string) $order->id,
            'trackingCode' => $order->tracking_code,
            'tracking_code' => $order->tracking_code,
            'status' => $order->order_status,
            'orderStatus' => $order->order_status, // For webmaster compatibility
            'customer' => $order->shipping_name ?? $order->user?->name ?? 'Guest',
            'customerPhone' => $order->shipping_phone ?? $order->user?->phone,
            'customerEmail' => $order->shipping_email ?? $order->user?->email,
            'total' => (float) $order->total_order_price,
            'createdAt' => $order->created_at?->toISOString(),
            'isPaid' => (bool) $order->is_paid,
            'isDelivered' => (bool) $order->is_delivered,
            'paymentMethod' => $order->payment_method,
            'payment_method' => $order->payment_method, // For webmaster compatibility
            'stockist' => $order->stockist,
            'distributor_id' => $order->distributor_id,
            'paymentProof' => $order->payment_proof,
            // Flat shipping fields for webmaster compatibility
            'shipping_name' => $order->shipping_name,
            'shipping_email' => $order->shipping_email,
            'shipping_phone' => $order->shipping_phone,
            'shipping_street' => $order->shipping_street,
            'shipping_city' => $order->shipping_city,
            'shipping_country' => $order->shipping_country,
            'shipping_zip' => $order->shipping_zip,
            // Nested for warehouse
            'shippingAddress' => [
                'name' => $order->shipping_name,
                'street' => $order->shipping_street,
                'city' => $order->shipping_city,
                'country' => $order->shipping_country,
                'zip' => $order->shipping_zip,
                'phone' => $order->shipping_phone,
                'email' => $order->shipping_email,
            ],
            // User reference
            'user' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'productName' => $item->product?->title ?? 'Unknown Product',
                    'productImage' => $item->product?->img_cover,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'discount' => (float) ($item->total_product_discount ?? 0),
                    // For webmaster compatibility
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'title' => $item->product->title,
                        'price' => (float) $item->product->price,
                        'img_cover' => $item->product->img_cover,
                    ] : null,
                ];
            })->values(),
        ];

        return response()->json([
            'message' => 'success',
            'order' => $formattedOrder,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $oldStatus = $order->order_status;

        $order->update($request->only([
            'order_status',
            'is_paid',
            'is_delivered',
            'payment_method'
        ]));

        // Handle Status Change Notifications
        if ($oldStatus !== $order->order_status) {
            $this->handleStatusNotification($order);
            broadcast(new \App\Events\OrderStatusUpdated($order))->toOthers();
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $this->toNodeOrder($order),
        ]);
    }

    /**
     * Update order status only (for admin panels validation workflow)
     */
    public function updateOrderStatus(Request $request, string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $request->validate([
            'status' => 'required|string',
        ]);

        $oldStatus = $order->order_status;
        $newStatus = $request->input('status');

        $order->order_status = $newStatus;

        // Auto-update flags based on status
        if (in_array($newStatus, ['delivered', 'completed'])) {
            $order->is_delivered = true;
            $order->delivered_at = now();
        }

        $order->save();

        // Handle Status Change Notifications
        if ($oldStatus !== $newStatus) {
            $this->handleStatusNotification($order);
            broadcast(new \App\Events\OrderStatusUpdated($order))->toOthers();
        }

        return response()->json([
            'message' => 'success',
            'order' => [
                'id' => $order->id,
                'status' => $order->order_status,
                'trackingCode' => $order->tracking_code,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Return items to stock? Usually canceling an order returns items.
        // If deleting, we might want to return stock if order wasn't completed.
        // For simplicity, we just delete.
        $order->items()->delete();
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }

    public function uploadProof(Request $request, string $id)
    {
        $request->validate([
            'proof' => 'required|image|max:5120', // 5MB max
        ]);

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($request->hasFile('proof')) {
            $path = $request->file('proof')->store('proofs', 'public');
            $order->payment_proof = $path;
            $order->save();
        }

        return response()->json([
            'message' => 'Proof uploaded successfully',
            'path' => $order->payment_proof,
        ]);
    }

    private function handleStatusNotification(Order $order)
    {
        $title = "Mise à jour Commande #{$order->tracking_code}";
        $message = "";
        $type = "info";
        $targetUserIds = []; // Specific users to notify

        switch ($order->order_status) {
            case 'validated':
                $message = "Commande validée ! En attente de transit vers le Warehouse.";
                $type = "success";
                // Notify country Warehouse
                if ($order->shipping_country) {
                    $warehouse = User::where('role', 'warehouse')->where('country', $order->shipping_country)->first();
                    if ($warehouse)
                        $targetUserIds[] = $warehouse->id;
                }
                break;
            case 'reached_warehouse':
                $message = "La commande est arrivée au Warehouse central du pays.";
                // Notify Admin/Webmaster (global) + Stockist if assigned
                if ($order->stockist) {
                    $stockistUser = User::find($order->stockist);
                    if ($stockistUser)
                        $targetUserIds[] = $stockistUser->id;
                }
                break;
            case 'shipped_to_stockist':
                $message = "La commande a quitté le Warehouse et est en route vers le point de retrait.";
                if ($order->stockist) {
                    $stockistUser = User::find($order->stockist);
                    if ($stockistUser)
                        $targetUserIds[] = $stockistUser->id;
                }
                break;
            case 'reached_stockist':
                $message = "La commande est arrivée chez votre Stockiste. Vous pouvez aller la récupérer !";
                $type = "success";
                if ($order->stockist) {
                    $stockistUser = User::find($order->stockist);
                    if ($stockistUser)
                        $targetUserIds[] = $stockistUser->id;
                }
                break;
            case 'out_for_delivery':
                $message = "Le livreur a récupéré votre commande. Livraison imminente !";
                break;
            case 'delivered':
                $message = "Commande livrée avec succès. Merci !";
                $type = "success";
                break;
            case 'canceled':
                $message = "Commande annulée.";
                $type = "error";
                break;
        }

        if ($message) {
            // Global Notification for Admin/Dev/Webmaster
            Notification::create([
                'title' => $title,
                'message' => $message,
                'category' => 'order',
                'type' => $type,
                'user_id' => null, // Global
                'metadata' => ['order_id' => $order->id, 'tracking_code' => $order->tracking_code, 'status' => $order->order_status]
            ]);

            // Specific Notifications for Warehouse/Stockist/Client
            $userIdsToNotify = array_unique(array_merge($targetUserIds, [$order->user_id]));
            foreach ($userIdsToNotify as $uid) {
                if (!$uid)
                    continue;
                Notification::create([
                    'title' => $title,
                    'message' => $message,
                    'category' => 'order',
                    'type' => $type,
                    'user_id' => $uid,
                    'metadata' => ['order_id' => $order->id, 'tracking_code' => $order->tracking_code, 'status' => $order->order_status]
                ]);
            }
        }
    }

    public function checkOut(Request $request, string $id)
    {
        return response()->json([
            'message' => 'success',
            'sessions' => null,
        ], 200);
    }

    /**
     * Search an order by tracking code (Authenticated)
     */
    public function searchByCode(Request $request, string $code)
    {
        $user = $request->user();
        $query = Order::query()->where('tracking_code', $code)->with(['items.product', 'user']);

        // Data segregation
        if ($user->role === 'warehouse') {
            $query->where('shipping_country', $user->country);
        } elseif ($user->role === 'stockist') {
            $query->where('stockist', $user->id);
        }

        $order = $query->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'message' => 'success',
            'order' => $this->toNodeOrder($order),
        ]);
    }

    /**
     * Track an order by its tracking code (public, no auth required)
     */
    public function track(string $code)
    {
        $order = Order::query()
            ->where('tracking_code', $code)
            ->with(['items.product'])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
                'error' => 'TRACKING_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'message' => 'success',
            'order' => [
                'trackingCode' => $order->tracking_code,
                'status' => $order->order_status,
                'isPaid' => (bool) $order->is_paid,
                'isDelivered' => (bool) $order->is_delivered,
                'totalPrice' => (float) $order->total_order_price,
                'hasUser' => $order->user_id !== null, // For guest conversion check
                'shippingAddress' => [
                    'name' => $order->shipping_name,
                    'city' => $order->shipping_city,
                    'country' => $order->shipping_country,
                    'email' => $order->shipping_email,
                ],
                'items' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->product?->title ?? 'Produit',
                        'quantity' => (int) $item->quantity,
                        'price' => (float) $item->price,
                        'image' => $item->product?->img_cover,
                    ];
                })->values(),
                'orderedAt' => optional($order->order_at)->toISOString(),
                'paidAt' => optional($order->paid_at)->toISOString(),
                'deliveredAt' => optional($order->delivered_at)->toISOString(),
            ],
        ], 200);
    }

    private function toNodeOrder(Order $order): array
    {
        $order->loadMissing(['items.product', 'user']);

        return [
            // Panel-friendly fields
            'id' => $order->id,
            'trackingCode' => $order->tracking_code,
            'status' => $order->order_status,
            'customer' => $order->shipping_name ?? $order->user?->name ?? 'Guest',
            'total' => (float) $order->total_order_price,
            'userName' => $order->shipping_name ?? $order->user?->name ?? 'Guest',
            // Legacy fields
            '_id' => (string) $order->id,
            'userId' => (string) $order->user_id,
            'cartItem' => $order->items->map(function (OrderItem $item) {
                return [
                    '_id' => (string) $item->id,
                    'productId' => $item->product ? [
                        '_id' => (string) $item->product->id,
                        'title' => $item->product->title,
                        'price' => (float) $item->product->price,
                        'imgCover' => $item->product->img_cover,
                    ] : (string) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'totalProductDiscount' => $item->total_product_discount,
                ];
            })->values(),
            'shippingAddress' => [
                'street' => $order->shipping_street,
                'city' => $order->shipping_city,
                'phone' => $order->shipping_phone,
            ],
            'distributorId' => $order->distributor_id,
            'stockist' => $order->stockist,
            'orderStatus' => $order->order_status,
            'paymentMethod' => $order->payment_method,
            'isPaid' => (bool) $order->is_paid,
            'paidAt' => optional($order->paid_at)->toISOString(),
            'isDelivered' => (bool) $order->is_delivered,
            'deliveredAt' => optional($order->delivered_at)->toISOString(),
            'totalOrderPrice' => (float) $order->total_order_price,
            'createdAt' => optional($order->created_at)->toISOString(),
            'updatedAt' => optional($order->updated_at)->toISOString(),
            'paymentProof' => $order->payment_proof,
            'tracking_code' => $order->tracking_code,
        ];
    }
}

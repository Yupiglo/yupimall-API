<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockExit;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockExitController extends Controller
{
    /**
     * Get all stock exits with pagination
     */
    public function index(Request $request)
    {
        $query = StockExit::with(['product:id,title,img_cover', 'user:id,name', 'order:id,tracking_code']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by reason
        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $exits = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($exits);
    }

    /**
     * Get stock exit statistics
     */
    public function stats(Request $request)
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'total_exits' => StockExit::count(),
            'today_exits' => StockExit::whereDate('created_at', $today)->count(),
            'today_quantity' => StockExit::whereDate('created_at', $today)->sum('quantity'),
            'this_month_exits' => StockExit::where('created_at', '>=', $thisMonth)->count(),
            'this_month_quantity' => StockExit::where('created_at', '>=', $thisMonth)->sum('quantity'),
            'by_reason' => StockExit::selectRaw('reason, SUM(quantity) as total')
                ->groupBy('reason')
                ->pluck('total', 'reason'),
        ];

        return response()->json($stats);
    }

    /**
     * Get valid exit reasons
     */
    public function reasons()
    {
        return response()->json([
            'reasons' => [
                StockExit::REASON_SALE => 'Sale',
                StockExit::REASON_DAMAGED => 'Damaged',
                StockExit::REASON_EXPIRED => 'Expired',
                StockExit::REASON_RETURNED => 'Returned to Supplier',
                StockExit::REASON_OTHER => 'Other',
            ]
        ]);
    }

    /**
     * Store a new stock exit and update product quantity
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id' => 'nullable|exists:orders,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|in:sale,damaged,expired,returned,other',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;

        // Check if product has enough stock
        $product = Product::findOrFail($validated['product_id']);
        if ($product->quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient stock. Available: ' . $product->quantity,
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create stock exit
            $exit = StockExit::create($validated);

            // Decrease product quantity
            $product->decrement('quantity', $validated['quantity']);

            DB::commit();

            $exit->load(['product:id,title,img_cover,quantity', 'user:id,name', 'order:id,tracking_code']);

            return response()->json([
                'message' => 'Stock exit created successfully',
                'data' => $exit,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create stock exit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific stock exit
     */
    public function show(StockExit $stockExit)
    {
        $stockExit->load(['product', 'user:id,name', 'order']);
        return response()->json($stockExit);
    }

    /**
     * Update a stock exit (adjusts product quantity accordingly)
     */
    public function update(Request $request, StockExit $stockExit)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'reason' => 'sometimes|string|in:sale,damaged,expired,returned,other',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // If quantity changed, adjust product quantity
            if (isset($validated['quantity']) && $validated['quantity'] !== $stockExit->quantity) {
                $difference = $validated['quantity'] - $stockExit->quantity;
                $product = Product::findOrFail($stockExit->product_id);

                // If increasing exit quantity, check if product has enough stock
                if ($difference > 0 && $product->quantity < $difference) {
                    return response()->json([
                        'message' => 'Insufficient stock to increase exit quantity. Available: ' . $product->quantity,
                    ], 422);
                }

                // Adjust: if difference is positive, we exit more (decrease product)
                // if difference is negative, we exit less (increase product)
                $product->decrement('quantity', $difference);
            }

            $stockExit->update($validated);

            DB::commit();

            $stockExit->load(['product:id,title,img_cover,quantity', 'user:id,name', 'order:id,tracking_code']);

            return response()->json([
                'message' => 'Stock exit updated successfully',
                'data' => $stockExit,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update stock exit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a stock exit (restores product quantity)
     */
    public function destroy(StockExit $stockExit)
    {
        try {
            DB::beginTransaction();

            // Restore product quantity
            $product = Product::findOrFail($stockExit->product_id);
            $product->increment('quantity', $stockExit->quantity);

            $stockExit->delete();

            DB::commit();

            return response()->json([
                'message' => 'Stock exit deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete stock exit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

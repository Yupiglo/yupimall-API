<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockEntry;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockEntryController extends Controller
{
    /**
     * Get all stock entries with pagination
     */
    public function index(Request $request)
    {
        $query = StockEntry::with(['product:id,title,img_cover', 'user:id,name']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by supplier
        if ($request->has('supplier')) {
            $query->where('supplier', 'like', '%' . $request->supplier . '%');
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
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $entries = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($entries);
    }

    /**
     * Get stock entry statistics
     */
    public function stats()
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'total_entries' => StockEntry::count(),
            'today_entries' => StockEntry::whereDate('created_at', $today)->count(),
            'today_quantity' => StockEntry::whereDate('created_at', $today)->sum('quantity'),
            'this_month_entries' => StockEntry::where('created_at', '>=', $thisMonth)->count(),
            'this_month_quantity' => StockEntry::where('created_at', '>=', $thisMonth)->sum('quantity'),
            'total_value' => StockEntry::whereNotNull('unit_price')
                ->selectRaw('SUM(quantity * unit_price) as total')
                ->value('total') ?? 0,
        ];

        return response()->json($stats);
    }

    /**
     * Store a new stock entry and update product quantity
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;

        try {
            DB::beginTransaction();

            // Create stock entry
            $entry = StockEntry::create($validated);

            // Update product quantity
            $product = Product::findOrFail($validated['product_id']);
            $product->increment('quantity', $validated['quantity']);

            DB::commit();

            $entry->load(['product:id,title,img_cover,quantity', 'user:id,name']);

            return response()->json([
                'message' => 'Stock entry created successfully',
                'data' => $entry,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create stock entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific stock entry
     */
    public function show(StockEntry $stockEntry)
    {
        $stockEntry->load(['product', 'user:id,name']);
        return response()->json($stockEntry);
    }

    /**
     * Update a stock entry (adjusts product quantity accordingly)
     */
    public function update(Request $request, StockEntry $stockEntry)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // If quantity changed, adjust product quantity
            if (isset($validated['quantity']) && $validated['quantity'] !== $stockEntry->quantity) {
                $difference = $validated['quantity'] - $stockEntry->quantity;
                $product = Product::findOrFail($stockEntry->product_id);

                // Check if we have enough stock to decrease
                if ($difference < 0 && $product->quantity + $difference < 0) {
                    return response()->json([
                        'message' => 'Cannot reduce entry quantity. Product stock would become negative.',
                    ], 422);
                }

                $product->increment('quantity', $difference);
            }

            $stockEntry->update($validated);

            DB::commit();

            $stockEntry->load(['product:id,title,img_cover,quantity', 'user:id,name']);

            return response()->json([
                'message' => 'Stock entry updated successfully',
                'data' => $stockEntry,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update stock entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a stock entry (reverts product quantity)
     */
    public function destroy(StockEntry $stockEntry)
    {
        try {
            DB::beginTransaction();

            // Revert product quantity
            $product = Product::findOrFail($stockEntry->product_id);

            // Check if we can decrease the quantity
            if ($product->quantity < $stockEntry->quantity) {
                return response()->json([
                    'message' => 'Cannot delete entry. Current product stock is less than entry quantity.',
                ], 422);
            }

            $product->decrement('quantity', $stockEntry->quantity);
            $stockEntry->delete();

            DB::commit();

            return response()->json([
                'message' => 'Stock entry deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete stock entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

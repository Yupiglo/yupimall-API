<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $page = (int) request()->input('page', 1);
        $limit = (int) request()->input('limit', 50);
        if ($limit <= 0) {
            $limit = 50;
        }

        $paginator = Review::query()->with('user')->paginate($limit, ['*'], 'page', $page);

        $reviews = collect($paginator->items())
            ->map(fn(Review $r) => $this->toNodeReview($r))
            ->values();

        return response()->json([
            'page' => $page,
            'message' => 'success',
            'getAllReviews' => $reviews,
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'productId' => ['required'],
            'text' => ['required', 'string'],
            'rate' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $productId = $request->input('productId');

        $exists = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($exists) {
            return response()->json(['message' => 'You created a review before'], 409);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'text' => $request->input('text'),
            'rate' => (int) $request->input('rate', 5),
            'status' => $request->input('status', 'pending'),
        ]);

        return response()->json([
            'message' => 'success',
            'addReview' => $this->toNodeReview($review->fresh('user')),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $review = Review::query()->with('user')->find($id);
        if (!$review) {
            return response()->json(['message' => 'Reveiw was not found'], 404);
        }

        return response()->json([
            'message' => 'success',
            'result' => $this->toNodeReview($review),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $reviewQuery = Review::query()->where('id', $id);
        if (!$isAdmin) {
            $reviewQuery->where('user_id', $user->id);
        }

        $review = $reviewQuery->first();
        if (!$review) {
            return response()->json([
                'message' => "Review was not found or you're not authorized to review this project",
            ], 404);
        }

        $review->fill($request->only(['text', 'rate', 'status']));
        $review->save();

        return response()->json([
            'message' => 'success',
            'updateReview' => $this->toNodeReview($review->fresh('user')),
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = request()->user();
        $isAdmin = $user->isAdmin();
        $reviewQuery = Review::query()->where('id', $id);
        if (!$isAdmin) {
            $reviewQuery->where('user_id', $user->id);
        }

        $review = $reviewQuery->first();
        if (!$review) {
            return response()->json(['message' => 'Review was not found'], 404);
        }

        $review->delete();
        return response()->json(['message' => 'success'], 200);
    }

    private function toNodeReview(Review $review): array
    {
        $review->loadMissing('user');

        return [
            '_id' => (string) $review->id,
            'text' => $review->text,
            'productId' => (string) $review->product_id,
            'userId' => $review->user ? [
                'name' => $review->user->name,
                'email' => $review->user->email,
            ] : (string) $review->user_id,
            'rate' => (int) $review->rate,
            'status' => $review->status,
            'createdAt' => optional($review->created_at)->toISOString(),
            'updatedAt' => optional($review->updated_at)->toISOString(),
        ];
    }
}

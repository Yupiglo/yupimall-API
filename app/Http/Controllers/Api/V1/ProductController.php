<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Product::query()->orderBy('sort_order', 'asc');

        if (request()->filled('q')) {
            $q = trim(request()->string('q')->toString());
            if ($q !== '') {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', '%' . $q . '%')
                        ->orWhere('description', 'like', '%' . $q . '%');
                });
            }
        }

        if (request()->filled('category')) {
            $cat = request()->input('category');
            if (is_numeric($cat)) {
                $query->where('category_id', $cat);
            } else {
                $query->where('category', 'like', '%' . $cat . '%');
            }
        }

        if (request()->filled('subcategory')) {
            $sub = request()->input('subcategory');
            if (is_numeric($sub)) {
                $query->where('subcategory_id', $sub);
            } else {
                $query->where('subcategory', 'like', '%' . $sub . '%');
            }
        }

        if (request()->filled('brand')) {
            $query->where('brand', 'like', '%' . request()->string('brand')->toString() . '%');
        }

        if (request()->filled('minPrice')) {
            $query->where('price', '>=', (float) request()->input('minPrice'));
        }

        if (request()->filled('maxPrice')) {
            $query->where('price', '<=', (float) request()->input('maxPrice'));
        }

        $page = (int) request()->input('page', 1);
        $limit = (int) request()->input('limit', 50);
        if ($limit <= 0) {
            $limit = 50;
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $page);
        $products = collect($paginator->items())->map(fn(Product $p) => $this->toNodeProduct($p))->values();

        return response()->json([
            'page' => $page,
            'perPage' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
            'lastPage' => (int) $paginator->lastPage(),
            'message' => 'success',
            'getAllProducts' => $products,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $payload = $this->mapNodeToDb($request->all());

        $validator = Validator::make($payload, [
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string'],
            'price' => ['required', 'numeric'],
            'pv' => ['nullable', 'numeric'],
            'discountPercentage' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            $details = collect($validator->errors()->messages())
                ->map(function ($messages, $field) {
                    return collect($messages)->map(fn($m) => ['field' => $field, 'message' => $m])->all();
                })
                ->flatten(1)
                ->values();

            return response()->json([
                'message' => 'Validation Error',
                'details' => $details,
            ], 400);
        }

        $images = $this->storeMultipleImages($request, 'images', 'products');
        if (!empty($images)) {
            $payload['images'] = $images;
            $payload['img_cover'] = $images[0];
        }

        if (isset($payload['variants']) && is_string($payload['variants'])) {
            $decoded = json_decode($payload['variants'], true);
            $payload['variants'] = is_array($decoded) ? $decoded : [];
        }

        foreach (['benefits', 'ingredients', 'reviews', 'countries'] as $f) {
            if (isset($payload[$f]) && is_string($payload[$f])) {
                $decoded = json_decode($payload[$f], true);
                $payload[$f] = is_array($decoded) ? $decoded : ($decoded ?: []);
            }
        }

        // Hierarchy resolution
        if (isset($payload['category']) && is_numeric($payload['category'])) {
            $cat = \App\Models\Category::find($payload['category']);
            if ($cat) {
                $payload['category_id'] = $cat->id;
                $payload['category'] = $cat->slug;
            }
        }
        if (isset($payload['subcategory']) && is_numeric($payload['subcategory'])) {
            $sub = \App\Models\Subcategory::find($payload['subcategory']);
            if ($sub) {
                $payload['subcategory_id'] = $sub->id;
                $payload['subcategory'] = $sub->slug;
            }
        }

        $product = Product::create($payload);

        // Create initial Stock Entry if quantity > 0
        if ($product->quantity > 0) {
            \App\Models\StockEntry::create([
                'product_id' => $product->id,
                'user_id' => $request->user() ? $request->user()->id : null,
                'quantity' => $product->quantity,
                'unit_price' => $product->price,
                'notes' => 'Stock initial (Création produit)',
                'reference' => 'INIT-' . strtoupper(Str::random(6)),
            ]);
        }

        // Create Notification
        \App\Models\Notification::create([
            'title' => 'Nouveau produit',
            'message' => "Un nouveau produit '{$product->title}' a été ajouté au catalogue.",
            'category' => 'system',
            'type' => 'info',
            'metadata' => ['product_id' => $product->id]
        ]);

        ActivityLogger::log(
            "Product Created",
            "New product '{$product->title}' was added to category '{$product->category}'",
            "success",
            ['product_id' => $product->id]
        );

        return response()->json([
            'message' => 'Product added successfully',
            'product' => $this->toNodeProduct($product),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'message' => 'success',
            'getSpecificProduct' => $this->toNodeProduct($product),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product was not found'], 404);
        }

        $payload = $this->mapNodeToDb($request->all());

        $existingImages = [];
        if ($request->filled('existingImages')) {
            $raw = $request->input('existingImages');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $existingImages = $decoded;
                }
            } elseif (is_array($raw)) {
                $existingImages = $raw;
            }
        }

        $newImages = $this->storeMultipleImages($request, 'images', 'products');
        $finalImages = array_values(array_filter(array_merge($existingImages, $newImages)));
        if (!empty($finalImages)) {
            $payload['images'] = $finalImages;
            $payload['img_cover'] = $finalImages[0];
        }

        if (isset($payload['variants']) && is_string($payload['variants'])) {
            $decoded = json_decode($payload['variants'], true);
            $payload['variants'] = is_array($decoded) ? $decoded : [];
        }

        foreach (['benefits', 'ingredients', 'reviews', 'countries'] as $f) {
            if (isset($payload[$f]) && is_string($payload[$f])) {
                $decoded = json_decode($payload[$f], true);
                $payload[$f] = is_array($decoded) ? $decoded : ($decoded ?: []);
            }
        }

        // Hierarchy resolution
        if (isset($payload['category']) && is_numeric($payload['category'])) {
            $cat = \App\Models\Category::find($payload['category']);
            if ($cat) {
                $payload['category_id'] = $cat->id;
                $payload['category'] = $cat->slug;
            }
        }
        if (isset($payload['subcategory']) && is_numeric($payload['subcategory'])) {
            $sub = \App\Models\Subcategory::find($payload['subcategory']);
            if ($sub) {
                $payload['subcategory_id'] = $sub->id;
                $payload['subcategory'] = $sub->slug;
            }
        }


        $payload = collect($payload)
            ->filter(fn($v) => $v !== null && $v !== '')
            ->all();

        $product->fill($payload);
        $product->save();

        ActivityLogger::log(
            "Product Updated",
            "Product '{$product->title}' (ID: {$product->id}) details were modified",
            "info",
            ['product_id' => $product->id]
        );

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $this->toNodeProduct($product->fresh()),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product was not found'], 404);
        }

        $product->delete();

        ActivityLogger::log(
            "Product Deleted",
            "Product '{$product->title}' (ID: {$id}) was removed from inventory",
            "warning",
            ['product_id' => $id]
        );

        return response()->json(['message' => 'success'], 200);
    }

    public function filter(Request $request)
    {
        $query = Product::query();

        if ($request->filled('category')) {
            $cat = $request->input('category');
            if (is_numeric($cat)) {
                $query->where('category_id', $cat);
            } else {
                $query->where('category', 'like', '%' . $cat . '%');
            }
        }

        if ($request->filled('subcategory')) {
            $sub = $request->input('subcategory');
            if (is_numeric($sub)) {
                $query->where('subcategory_id', $sub);
            } else {
                $query->where('subcategory', 'like', '%' . $sub . '%');
            }
        }

        if ($request->filled('brand')) {
            $query->where('brand', 'like', '%' . $request->string('brand')->toString() . '%');
        }

        if ($request->filled('minPrice')) {
            $query->where('price', '>=', (float) $request->input('minPrice'));
        }

        if ($request->filled('maxPrice')) {
            $query->where('price', '<=', (float) $request->input('maxPrice'));
        }

        $products = $query->get()->map(fn(Product $p) => $this->toNodeProduct($p))->values();

        return response()->json([
            'message' => 'Filtered products fetched successfully',
            'products' => $products,
            'count' => $products->count(),
        ], 200);
    }

    public function byCategory(Request $request, string $category)
    {
        if (!$category) {
            return response()->json(['message' => 'Category parameter is required'], 400);
        }

        $products = Product::query()
            ->where('category', 'like', '%' . $category . '%')
            ->get()
            ->map(fn(Product $p) => $this->toNodeProduct($p))
            ->values();

        return response()->json([
            'message' => "Products in {$category} category",
            'products' => $products,
            'count' => $products->count(),
            'category' => $category,
        ], 200);
    }

    public function special(Request $request)
    {
        $filter = Product::query();

        $hasAny = false;

        if ($request->has('new')) {
            $hasAny = true;
            $filter->where('is_new', $request->input('new') === 'true' || $request->boolean('new'));
        }

        if ($request->has('sale')) {
            $hasAny = true;
            $filter->where('is_sale', $request->input('sale') === 'true' || $request->boolean('sale'));
        }

        if (!$hasAny) {
            return response()->json(['message' => 'Please provide new or sale in query'], 400);
        }

        $products = $filter->get()->map(fn(Product $p) => $this->toNodeProduct($p))->values();

        return response()->json([
            'message' => 'Special products fetched successfully',
            'products' => $products,
        ], 200);
    }

    private function mapNodeToDb(array $input): array
    {
        $mapped = [];

        foreach ($input as $key => $value) {
            if ($key === 'new') {
                $mapped['is_new'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
                continue;
            }

            if ($key === 'sale') {
                $mapped['is_sale'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
                continue;
            }

            if ($key === 'imgCover') {
                $mapped['img_cover'] = $value;
                continue;
            }

            $mapped[$this->toSnake($key)] = $value;
        }

        unset($mapped['existing_images']);

        return $mapped;
    }

    private function toNodeProduct(Product $product): array
    {
        $cover = $product->img_cover;
        $coverPath = is_string($cover) && $cover !== ''
            ? (str_starts_with($cover, 'uploads/') || str_starts_with($cover, '/uploads/') ? $cover : ('uploads/products/' . ltrim($cover, '/')))
            : null;

        $images = $product->images ?? [];
        if (!is_array($images)) {
            $images = [];
        }
        $imagePaths = collect($images)
            ->filter(fn($v) => is_string($v) && trim($v) !== '')
            ->map(function ($v) {
                $v = trim($v);
                if (str_starts_with($v, 'uploads/') || str_starts_with($v, '/uploads/')) {
                    return $v;
                }
                return 'uploads/products/' . ltrim($v, '/');
            })
            ->values()
            ->all();

        return [
            '_id' => (string) $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'type' => $product->type,
            'brand' => $product->brand,
            'category' => $product->category,
            'subcategory' => $product->subcategory,
            'subcategoryName' => $product->subcategory_id ? (\App\Models\Subcategory::find($product->subcategory_id)->name ?? $product->subcategory) : $product->subcategory,
            'categoryId' => (string) $product->category_id,
            'subcategoryId' => (string) $product->subcategory_id,
            'price' => (float) $product->price,
            'new' => (bool) $product->is_new,
            'sale' => (bool) $product->is_sale,
            'discount' => (float) $product->discount,
            'imgCover' => $coverPath,
            'variants' => $product->variants ?? [],
            'images' => $imagePaths,
            'countries' => $product->countries ?? ['ALL'],
            'benefits' => $product->benefits ?? [],
            'ingredients' => $product->ingredients ?? [],
            'howToUse' => $product->how_to_use,
            'clinicalResearch' => $product->clinical_research,
            'reviewsData' => $product->reviews ?? [], // Using reviewsData to avoid conflict with relation
            'quantity' => (int) $product->quantity,
            'sold' => (int) $product->sold,
            'pv' => (float) $product->pv,
            'discountPercentage' => (float) $product->discount_percentage,
            'createdAt' => optional($product->created_at)->toISOString(),
            'updatedAt' => optional($product->updated_at)->toISOString(),
        ];
    }

    private function toSnake(string $key): string
    {
        return Str::snake($key);
    }

    private function storeMultipleImages(Request $request, string $field, string $folder): array
    {
        if (!$request->hasFile($field)) {
            return [];
        }

        $files = $request->file($field);
        if (!is_array($files)) {
            $files = [$files];
        }

        $stored = [];
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $stored[] = $this->storeUploadedFile($file, $folder);
        }

        return array_values(array_filter($stored));
    }

    private function storeUploadedFile($file, string $folder): string
    {
        $dir = public_path('uploads/' . $folder);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $name = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);
        return $name;
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.sort_order' => 'required|integer',
        ]);

        $items = $request->input('items');

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Product::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['message' => 'Products reordered successfully']);
    }

    public function shuffle()
    {
        $products = Product::all();
        $products->each(function ($product) {
            $product->sort_order = rand(0, 10000);
            $product->save();
        });

        // Re-number sequentially to avoid gaps/duplicates confusion slightly (optional, but clean)
        $sorted = Product::orderBy('sort_order')->get();
        foreach ($sorted as $index => $product) {
            $product->sort_order = $index;
            $product->save();
        }

        return response()->json(['message' => 'Products shuffled successfully']);
    }
}

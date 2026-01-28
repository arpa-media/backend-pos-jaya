<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\ListProductRequest;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Resources\Api\V1\Common\ApiResponse;
use App\Http\Resources\Api\V1\Product\ProductResource;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\OutletScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service)
    {
    }

    /**
     * Resolve outlet id for outlet-scoped operations.
     *
     * Primary source: OutletScope middleware (X-Outlet-Id header).
     * Fallback: for admin only, allow passing outlet_id in query/body.
     * Cashier/manager cannot spoof outlet id.
     */
    private function resolveOutletId(Request $request): ?string
    {
        $outletId = OutletScope::id($request);
        if ($outletId) return $outletId;

        $user = $request->user();
        if (!$user || $user->outlet_id) {
            // Non-admin users must be locked by middleware.
            return null;
        }

        $candidate = $request->input('outlet_id') ?? $request->query('outlet_id');
        if (!is_string($candidate) || trim($candidate) === '') return null;

        $candidate = trim($candidate);
        if (!Outlet::query()->whereKey($candidate)->exists()) return null;

        return $candidate;
    }

    public function index(ListProductRequest $request)
    {
        $filters = $request->validated();
        $forPos = (bool) ($filters['for_pos'] ?? false);

        $outletId = $this->resolveOutletId($request);
        if ($forPos && !$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $paginator = $this->service->paginateForOutlet((string) ($outletId ?? ''), $filters);

        $items = $paginator->items();

        return ApiResponse::ok([
            'items' => ProductResource::collection($items),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'OK');
    }

    public function store(StoreProductRequest $request)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products/'.(string) $outletId, 'public');
        }

        $product = $this->service->create((string) $outletId, $data);

        return ApiResponse::ok(new ProductResource($product), 'Product created', 201);
    }

    public function show(Request $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $product = Product::query()
            ->whereKey($id)
            ->with([
                'outlets',
                'variants' => function ($q) use ($outletId) {
                    $q->where('outlet_id', $outletId)
                      ->with(['prices' => function ($p) use ($outletId) {
                          $p->where('outlet_id', $outletId);
                      }]);
                },
            ])
            ->first();

        if (!$product) {
            return ApiResponse::error('Product not found', 'NOT_FOUND', 404);
        }

        return ApiResponse::ok(new ProductResource($product), 'OK');
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $product = Product::query()
            ->whereKey($id)
            ->with([
                'outlets',
                'variants' => function ($q) use ($outletId) {
                    $q->where('outlet_id', $outletId)
                      ->with(['prices' => function ($p) use ($outletId) {
                          $p->where('outlet_id', $outletId);
                      }]);
                },
            ])
            ->first();

        if (!$product) {
            return ApiResponse::error('Product not found', 'NOT_FOUND', 404);
        }

        $data = $request->validated();
        $oldImagePath = $product->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products/'.(string) $outletId, 'public');
        }

        $updated = $this->service->update((string) $outletId, $product, $data);

        if (!empty($oldImagePath) && array_key_exists('image_path', $data) && $oldImagePath !== $updated->image_path) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return ApiResponse::ok(new ProductResource($updated), 'Product updated');
    }

    public function setOutletActive(Request $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $product = Product::query()->whereKey($id)->first();
        if (!$product) {
            return ApiResponse::error('Product not found', 'NOT_FOUND', 404);
        }

        $this->service->setActiveForOutlet((string) $outletId, $product, (bool) $validated['is_active']);

        // return updated product with pivot state for this outlet
        $product->load(['outlets' => function ($q) use ($outletId) {
            $q->where('outlets.id', $outletId);
        }]);

        return ApiResponse::ok(new ProductResource($product), 'Outlet availability updated');
    }

    public function destroy(Request $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $product = Product::query()
            ->whereKey($id)
            ->with(['variants'])
            ->first();

        if (!$product) {
            return ApiResponse::error('Product not found', 'NOT_FOUND', 404);
        }

        $this->service->delete($product);

        return ApiResponse::ok(null, 'Product deleted');
    }
}

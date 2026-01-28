<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Addon\ListAddonRequest;
use App\Http\Requests\Api\V1\Addon\StoreAddonRequest;
use App\Http\Requests\Api\V1\Addon\UpdateAddonRequest;
use App\Http\Resources\Api\V1\Addon\AddonResource;
use App\Http\Resources\Api\V1\Common\ApiResponse;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AddonController extends Controller
{
    public function index(ListAddonRequest $request)
    {
        $user = $request->user();
        if (!$user->outlet_id) {
            return ApiResponse::error('Outlet not found for user', 'OUTLET_NOT_FOUND', 404);
        }

        $filters = $request->validated();
        $q = $filters['q'] ?? null;
        $isActive = array_key_exists('is_active', $filters) ? (bool) $filters['is_active'] : null;

        $perPage = (int) ($filters['per_page'] ?? 15);
        $sort = $filters['sort'] ?? 'name';
        $dir = $filters['dir'] ?? 'asc';

        $query = Addon::query()->where('outlet_id', $user->outlet_id);

        if ($q) {
            $query->where('name', 'like', '%'.$q.'%');
        }

        if (!is_null($isActive)) {
            $query->where('is_active', $isActive);
        }

        $p = $query->orderBy($sort, $dir)->paginate($perPage)->withQueryString();

        return ApiResponse::ok([
            'items' => AddonResource::collection($p->items()),
            'pagination' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'last_page' => $p->lastPage(),
            ],
        ], 'OK');
    }

    public function store(StoreAddonRequest $request)
    {
        $user = $request->user();
        if (!$user->outlet_id) {
            return ApiResponse::error('Outlet not found for user', 'OUTLET_NOT_FOUND', 404);
        }

        $data = $request->validated();

        $exists = Addon::query()
            ->where('outlet_id', $user->outlet_id)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Addon name already exists in this outlet.'],
            ]);
        }

        $addon = Addon::query()->create([
            'outlet_id' => $user->outlet_id,
            'name' => trim($data['name']),
            'price' => (int) $data['price'],
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        return ApiResponse::ok(new AddonResource($addon), 'Addon created', 201);
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user->outlet_id) {
            return ApiResponse::error('Outlet not found for user', 'OUTLET_NOT_FOUND', 404);
        }

        $addon = Addon::query()
            ->where('outlet_id', $user->outlet_id)
            ->where('id', $id)
            ->first();

        if (!$addon) {
            return ApiResponse::error('Addon not found', 'NOT_FOUND', 404);
        }

        return ApiResponse::ok(new AddonResource($addon), 'OK');
    }

    public function update(UpdateAddonRequest $request, string $id)
    {
        $user = $request->user();
        if (!$user->outlet_id) {
            return ApiResponse::error('Outlet not found for user', 'OUTLET_NOT_FOUND', 404);
        }

        $addon = Addon::query()
            ->where('outlet_id', $user->outlet_id)
            ->where('id', $id)
            ->first();

        if (!$addon) {
            return ApiResponse::error('Addon not found', 'NOT_FOUND', 404);
        }

        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $exists = Addon::query()
                ->where('outlet_id', $user->outlet_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $addon->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => ['Addon name already exists in this outlet.'],
                ]);
            }

            $addon->name = trim($data['name']);
        }

        if (array_key_exists('price', $data)) {
            $addon->price = (int) $data['price'];
        }

        if (array_key_exists('is_active', $data)) {
            $addon->is_active = (bool) $data['is_active'];
        }

        $addon->save();

        return ApiResponse::ok(new AddonResource($addon->fresh()), 'Addon updated');
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user->outlet_id) {
            return ApiResponse::error('Outlet not found for user', 'OUTLET_NOT_FOUND', 404);
        }

        $addon = Addon::query()
            ->where('outlet_id', $user->outlet_id)
            ->where('id', $id)
            ->first();

        if (!$addon) {
            return ApiResponse::error('Addon not found', 'NOT_FOUND', 404);
        }

        $addon->delete();

        return ApiResponse::ok(null, 'Addon deleted');
    }
}

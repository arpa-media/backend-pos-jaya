<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Outlet\UpdateOutletRequest;
use App\Http\Resources\Api\V1\Common\ApiResponse;
use App\Http\Resources\Api\V1\Outlet\OutletResource;
use App\Models\Outlet;
use App\Support\OutletScope;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    /**
     * List all outlets (multi-outlet admin).
     * Protected by permission:admin.access in routes.
     */
    public function index(Request $request)
    {
        $items = Outlet::query()->orderBy('name')->get();

        return ApiResponse::ok([
            'items' => OutletResource::collection($items),
        ], 'OK');
    }

    /**
     * Get current outlet for the request scope.
     * - Cashier: locked to user.outlet_id.
     * - Admin: requires X-Outlet-Id (cannot be ALL for this endpoint).
     */
    public function show(Request $request)
    {
        $outletId = OutletScope::id($request);

        if (!$outletId) {
            return ApiResponse::error(
                message: 'Outlet scope is required',
                errorCode: 'OUTLET_SCOPE_REQUIRED',
                status: 422
            );
        }

        $outlet = Outlet::query()->find($outletId);
        if (!$outlet) {
            return ApiResponse::error('Outlet not found', 'NOT_FOUND', 404);
        }

        return ApiResponse::ok(new OutletResource($outlet), 'OK');
    }

    /**
     * Update outlet settings for the request scope.
     */
    public function update(UpdateOutletRequest $request)
    {
        $outletId = OutletScope::id($request);

        if (!$outletId) {
            return ApiResponse::error(
                message: 'Outlet scope is required',
                errorCode: 'OUTLET_SCOPE_REQUIRED',
                status: 422
            );
        }

        $outlet = Outlet::query()->find($outletId);
        if (!$outlet) {
            return ApiResponse::error('Outlet not found', 'NOT_FOUND', 404);
        }

        $outlet->fill($request->validated());
        $outlet->save();

        return ApiResponse::ok(new OutletResource($outlet->fresh()), 'Outlet updated');
    }
}

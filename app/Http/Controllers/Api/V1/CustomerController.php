<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\SearchCustomerRequest;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Resources\Api\V1\Common\ApiResponse;
use App\Http\Resources\Api\V1\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\Outlet;
use App\Support\OutletScope;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function search(SearchCustomerRequest $request)
    {
        $user = $request->user();
        $v = $request->validated();

        // Resolve outlet:
        // - Cashier/manager is locked to user.outlet_id
        // - Admin uses X-Outlet-Id scope (or optional outlet_id query)
        $outletId = null;
        if ($user && $user->outlet_id) {
            $outletId = (string) $user->outlet_id;

            // Hard-guard: if outlet_id provided, it must match user's outlet
            if (!empty($v['outlet_id']) && (string) $v['outlet_id'] !== $outletId) {
                return ApiResponse::error('Outlet mismatch', 'OUTLET_MISMATCH', 403);
            }
        } else {
            $outletId = OutletScope::id($request) ?: (isset($v['outlet_id']) ? (string) $v['outlet_id'] : null);
        }

        if (!$outletId || !Outlet::query()->whereKey($outletId)->exists()) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $limit = (int) ($v['limit'] ?? 20);
        $limit = max(1, min(50, $limit));

        $q = trim((string) ($v['q'] ?? ''));
        $phone = trim((string) ($v['phone'] ?? ''));

        $query = Customer::query()->where('outlet_id', $outletId);

        if ($q !== '') {
            $qPhone = preg_replace('/\D+/', '', $q);
            $query->where(function ($w) use ($q, $qPhone) {
                $w->where('name', 'like', '%' . $q . '%');
                if ($qPhone) {
                    $w->orWhere('phone', 'like', '%' . $qPhone . '%');
                }
            });
        } elseif ($phone !== '') {
            // Backward compatible exact phone search
            $query->where('phone', $phone);
        } else {
            return ApiResponse::ok(['items' => []], 'OK');
        }

        $items = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return ApiResponse::ok([
            'items' => CustomerResource::collection($items),
        ], 'OK');
    }

    public function store(StoreCustomerRequest $request)
    {
        $user = $request->user();
        if (!$user->outlet_id) {
            return ApiResponse::error('Outlet not found for user', 'OUTLET_NOT_FOUND', 404);
        }

        $v = $request->validated();
        $outletId = (string) $user->outlet_id;

        if ((string) $v['outlet_id'] !== $outletId) {
            return ApiResponse::error('Outlet mismatch', 'OUTLET_MISMATCH', 403);
        }

        $phone = (string) $v['phone'];
        $name = (string) $v['name'];

        $existing = Customer::query()
            ->where('outlet_id', $outletId)
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return ApiResponse::error(
                'Phone already registered',
                'PHONE_EXISTS',
                409,
                [],
                ['customer' => new CustomerResource($existing)]
            );
        }

        $customer = Customer::query()->create([
            'outlet_id' => $outletId,
            'phone' => $phone,
            'name' => $name,
        ]);

        return ApiResponse::ok(new CustomerResource($customer), 'Customer created', 201);
    }
}

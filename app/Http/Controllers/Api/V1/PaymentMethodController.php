<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaymentMethod\ListPaymentMethodRequest;
use App\Http\Requests\Api\V1\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\Api\V1\PaymentMethod\UpdatePaymentMethodRequest;
use App\Http\Resources\Api\V1\Common\ApiResponse;
use App\Http\Resources\Api\V1\PaymentMethod\PaymentMethodResource;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use App\Support\OutletScope;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $service)
    {
    }

    /**
     * Resolve outlet id for outlet-specific operations.
     *
     * Primary: OutletScope middleware (X-Outlet-Id header)
     * Fallback: admin can pass outlet_id in query/body.
     */
    private function resolveOutletId(Request $request): ?string
    {
        $outletId = OutletScope::id($request);
        if ($outletId) return $outletId;

        $user = $request->user();
        if (!$user || $user->outlet_id) {
            return null;
        }

        $candidate = $request->input('outlet_id') ?? $request->query('outlet_id');
        if (!is_string($candidate) || trim($candidate) === '') return null;

        $candidate = trim($candidate);
        if (!Outlet::query()->whereKey($candidate)->exists()) return null;

        return $candidate;
    }

    public function index(ListPaymentMethodRequest $request)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $paginator = $this->service->paginateForOutlet((string) $outletId, $request->validated());

        return ApiResponse::ok([
            'items' => PaymentMethodResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'OK');
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $method = $this->service->create((string) $outletId, $request->validated());

        return ApiResponse::ok(new PaymentMethodResource($method), 'Payment method created', 201);
    }

    public function show(Request $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $method = PaymentMethod::query()->whereKey($id)->with(['outlets' => function ($q) use ($outletId) {
            $q->where('outlets.id', $outletId);
        }])->first();

        if (!$method) {
            return ApiResponse::error('Payment method not found', 'NOT_FOUND', 404);
        }

        return ApiResponse::ok(new PaymentMethodResource($method), 'OK');
    }

    public function update(UpdatePaymentMethodRequest $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $method = PaymentMethod::query()->whereKey($id)->first();

        if (!$method) {
            return ApiResponse::error('Payment method not found', 'NOT_FOUND', 404);
        }

        $updated = $this->service->update((string) $outletId, $method, $request->validated());

        return ApiResponse::ok(new PaymentMethodResource($updated), 'Payment method updated');
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

        $method = PaymentMethod::query()->whereKey($id)->first();
        if (!$method) {
            return ApiResponse::error('Payment method not found', 'NOT_FOUND', 404);
        }

        $this->service->setActiveForOutlet((string) $outletId, $method, (bool) $validated['is_active']);

        // return updated method with pivot for this outlet
        $method->load(['outlets' => function ($q) use ($outletId) {
            $q->where('outlets.id', $outletId);
        }]);

        return ApiResponse::ok(new PaymentMethodResource($method), 'Outlet availability updated');
    }

    public function destroy(Request $request, string $id)
    {
        $outletId = $this->resolveOutletId($request);
        if (!$outletId) {
            return ApiResponse::error('Please select an outlet', 'OUTLET_REQUIRED', 422);
        }

        $method = PaymentMethod::query()->whereKey($id)->first();

        if (!$method) {
            return ApiResponse::error('Payment method not found', 'NOT_FOUND', 404);
        }

        $this->service->delete($method);

        return ApiResponse::ok(null, 'Payment method deleted');
    }
}

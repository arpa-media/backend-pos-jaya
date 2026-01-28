<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\LedgerReportRequest;
use App\Http\Requests\Api\V1\Reports\RecentSalesReportRequest;
use App\Http\Requests\Api\V1\Reports\ReportRangeRequest;
use App\Http\Requests\Api\V1\Reports\DiscountReportRequest;
use App\Http\Requests\Api\V1\Reports\TaxReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function ledger(LedgerReportRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->ledger($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }

    public function itemSold(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->itemSold($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }

    public function recentSales(RecentSalesReportRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->recentSales($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }

    public function itemByProduct(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->itemByProduct($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }

    public function itemByVariant(ReportRangeRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->itemByVariant($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }

    public function tax(TaxReportRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->tax($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }

    public function discount(DiscountReportRequest $request, ReportService $service): JsonResponse
    {
        $data = $service->discount($request->validated(), $request->user()?->outlet_id);
        return response()->json(['data' => $data]);
    }
}

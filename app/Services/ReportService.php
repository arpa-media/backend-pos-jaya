<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private function resolveRange(?string $dateFrom, ?string $dateTo): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $from = $dateFrom ? CarbonImmutable::parse($dateFrom)->startOfDay() : $today;
        $to = $dateTo ? CarbonImmutable::parse($dateTo)->startOfDay() : $today;

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        // inclusive end-of-day
        return [$from, $to->endOfDay()];
    }

    private function paginate(QueryBuilder $q, int $perPage, int $page): LengthAwarePaginator
    {
        // paginate is available on query builder in Laravel
        return $q->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Derived table: 1 payment method name per sale (phase1 usually single payment)
     * - Prevents row duplication when joining sale_payments.
     */
    private function salePaymentMethodSubquery(): QueryBuilder
    {
        return DB::table('sale_payments as sp')
            ->join('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->selectRaw('sp.sale_id, MIN(pm.name) as payment_method_name')
            ->groupBy('sp.sale_id');
    }

    public function ledger(array $params, ?string $outletId): array
    {
        [$from, $to] = $this->resolveRange($params['date_from'] ?? null, $params['date_to'] ?? null);
        $perPage = (int) ($params['per_page'] ?? 20);
        $page = (int) ($params['page'] ?? 1);

        $pmSub = $this->salePaymentMethodSubquery();

        $q = DB::table('sales as s')
            ->join('outlets as o', 'o.id', '=', 's.outlet_id')
            ->leftJoin('sale_items as si', 'si.sale_id', '=', 's.id')
            ->leftJoinSub($pmSub, 'spm', function ($join) {
                $join->on('spm.sale_id', '=', 's.id');
            })
            ->whereBetween('s.created_at', [$from, $to])
            ->where('s.status', '=', 'PAID');

        if (!empty($outletId)) {
            $q->where('s.outlet_id', '=', $outletId);
        }

        if (!empty($params['payment_method_name'])) {
            $q->where('spm.payment_method_name', '=', $params['payment_method_name']);
        }

        if (!empty($params['channel'])) {
            $q->where('s.channel', '=', $params['channel']);
        }

        $q->select([
            's.id as sale_id',
            'o.code as outlet_code',
            's.sale_number',
            'si.product_name as item',
            'si.variant_name as variant',
            'si.qty',
            DB::raw("'-' as unit"),
            'si.unit_price',
            's.channel',
            DB::raw("COALESCE(spm.payment_method_name, '-') as payment_method_name"),
            DB::raw('COALESCE(si.line_total, 0) as total'),
            's.created_at',
        ]);

        $q->orderByDesc('s.created_at')->orderByDesc('s.id');

        $paginator = $this->paginate($q, $perPage, $page);

        $items = collect($paginator->items())->map(function ($r) {
            return [
                'sale_id' => (string) $r->sale_id,
                'outlet_code' => (string) ($r->outlet_code ?? ''),
                'sale_number' => (string) $r->sale_number,
                'item' => (string) ($r->item ?? ''),
                'variant' => (string) ($r->variant ?? ''),
                'qty' => (int) ($r->qty ?? 0),
                'unit' => (string) ($r->unit ?? '-'),
                'unit_price' => (int) ($r->unit_price ?? 0),
                'channel' => (string) ($r->channel ?? ''),
                'payment_method_name' => (string) ($r->payment_method_name ?? '-'),
                'total' => (int) ($r->total ?? 0),
                'created_at' => is_string($r->created_at)
                    ? str_replace('T', ' ', preg_replace('/\..*$/', '', $r->created_at))
                    : (optional($r->created_at)->format('Y-m-d H:i:s') ?? null),
            ];
        })->values()->all();

        // Summary: based on sales table (not affected by item rows)
        $sumQ = DB::table('sales as s')
            ->leftJoinSub($pmSub, 'spm', function ($join) {
                $join->on('spm.sale_id', '=', 's.id');
            })
            ->leftJoin('sale_items as si', 'si.sale_id', '=', 's.id')
            ->whereBetween('s.created_at', [$from, $to])
            ->where('s.status', '=', 'PAID');

        if (!empty($outletId)) $sumQ->where('s.outlet_id', '=', $outletId);
        if (!empty($params['payment_method_name'])) $sumQ->where('spm.payment_method_name', '=', $params['payment_method_name']);
        if (!empty($params['channel'])) $sumQ->where('s.channel', '=', $params['channel']);

        $summary = $sumQ->selectRaw('
            COALESCE(SUM(DISTINCT s.grand_total),0) as grand_total,
            COUNT(DISTINCT s.id) as transaction_count,
            COALESCE(SUM(si.qty),0) as items_sold
        ')->first();

        return [
            'range' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'summary' => [
                'grand_total' => (int) ($summary->grand_total ?? 0),
                'transaction_count' => (int) ($summary->transaction_count ?? 0),
                'items_sold' => (int) ($summary->items_sold ?? 0),
            ],
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function recentSales(array $params, ?string $outletId): array
    {
        [$from, $to] = $this->resolveRange($params['date_from'] ?? null, $params['date_to'] ?? null);
        $perPage = (int) ($params['per_page'] ?? 20);
        $page = (int) ($params['page'] ?? 1);

        $q = DB::table('sales as s')
            ->join('outlets as o', 'o.id', '=', 's.outlet_id')
            ->leftJoin('sale_items as si', 'si.sale_id', '=', 's.id')
            ->whereBetween('s.created_at', [$from, $to]);

        if (!empty($outletId)) $q->where('s.outlet_id', '=', $outletId);

        $q->select([
            's.id as sale_id',
            'o.code as outlet_code',
            's.sale_number',
            DB::raw('COALESCE(SUM(si.qty),0) as items_sold'),
            's.grand_total as total',
            's.paid_total as paid',
            's.created_at',
        ])
        ->groupBy('s.id', 'o.code', 's.sale_number', 's.grand_total', 's.paid_total', 's.created_at')
        ->orderByDesc('s.created_at');

        $p = $this->paginate($q, $perPage, $page);

        $items = collect($p->items())->map(function ($r) {
            return [
                'sale_id' => (string) $r->sale_id,
                'outlet_code' => (string) ($r->outlet_code ?? ''),
                'sale_number' => (string) $r->sale_number,
                'customer_name' => '-', // phase1: sales table has no customer_id
                'items_sold' => (int) ($r->items_sold ?? 0),
                'total' => (int) ($r->total ?? 0),
                'paid' => (int) ($r->paid ?? 0),
                'created_at' => is_string($r->created_at)
                    ? str_replace('T', ' ', preg_replace('/\..*$/', '', $r->created_at))
                    : (optional($r->created_at)->format('Y-m-d H:i:s') ?? null),
            ];
        })->values()->all();

        return [
            'range' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'data' => $items,
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'last_page' => $p->lastPage(),
                'total' => $p->total(),
            ],
        ];
    }

    public function itemSold(array $params, ?string $outletId): array
    {
        [$from, $to] = $this->resolveRange($params['date_from'] ?? null, $params['date_to'] ?? null);
        $perPage = (int) ($params['per_page'] ?? 50);
        $page = (int) ($params['page'] ?? 1);

        $q = DB::table('sales as s')
            ->leftJoin('sale_items as si', 'si.sale_id', '=', 's.id')
            ->whereBetween('s.created_at', [$from, $to])
            ->where('s.status', '=', 'PAID');

        if (!empty($outletId)) $q->where('s.outlet_id', '=', $outletId);

        $q->select([
            'si.product_name as item',
            'si.variant_name as variant',
            DB::raw('SUM(si.qty) as qty'),
            DB::raw('AVG(si.unit_price) as unit_price'),
            DB::raw('SUM(si.line_total) as total'),
        ])
        ->groupBy('si.product_name', 'si.variant_name')
        ->orderByDesc(DB::raw('SUM(si.qty)'));

        $p = $this->paginate($q, $perPage, $page);

        $items = collect($p->items())->map(fn($r) => [
            'item' => (string) ($r->item ?? ''),
            'variant' => (string) ($r->variant ?? ''),
            'qty' => (int) ($r->qty ?? 0),
            'unit_price' => (int) ($r->unit_price ?? 0),
            'total' => (int) ($r->total ?? 0),
        ])->values()->all();

        return [
            'range' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'data' => $items,
            'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ];
    }

    public function itemByProduct(array $params, ?string $outletId): array
    {
        [$from, $to] = $this->resolveRange($params['date_from'] ?? null, $params['date_to'] ?? null);
        $perPage = (int) ($params['per_page'] ?? 50);
        $page = (int) ($params['page'] ?? 1);

        $q = DB::table('sales as s')
            ->leftJoin('sale_items as si', 'si.sale_id', '=', 's.id')
            ->whereBetween('s.created_at', [$from, $to])
            ->where('s.status', '=', 'PAID');

        if (!empty($outletId)) $q->where('s.outlet_id', '=', $outletId);

        $q->select([
            'si.product_name as item_product',
            DB::raw('SUM(si.qty) as qty'),
            DB::raw('AVG(si.unit_price) as unit_price'),
            DB::raw('SUM(si.line_total) as total'),
        ])
        ->groupBy('si.product_name')
        ->orderByDesc(DB::raw('SUM(si.qty)'));

        $p = $this->paginate($q, $perPage, $page);

        $items = collect($p->items())->map(fn($r) => [
            'item_product' => (string) ($r->item_product ?? ''),
            'qty' => (int) ($r->qty ?? 0),
            'unit_price' => (int) ($r->unit_price ?? 0),
            'total' => (int) ($r->total ?? 0),
        ])->values()->all();

        return [
            'range' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'data' => $items,
            'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ];
    }

    public function itemByVariant(array $params, ?string $outletId): array
    {
        // same as itemSold (already groups by product+variant)
        return $this->itemSold($params, $outletId);
    }

    public function tax(array $params, ?string $outletId): array
    {
        [$from, $to] = $this->resolveRange($params['date_from'] ?? null, $params['date_to'] ?? null);
        $perPage = (int) ($params['per_page'] ?? 20);
        $page = (int) ($params['page'] ?? 1);

        $pmSub = $this->salePaymentMethodSubquery();

        $q = DB::table('sales as s')
            ->leftJoinSub($pmSub, 'spm', function ($join) {
                $join->on('spm.sale_id', '=', 's.id');
            })
            ->whereBetween('s.created_at', [$from, $to])
            ->where('s.status', '=', 'PAID');

        if (!empty($outletId)) $q->where('s.outlet_id', '=', $outletId);

        if (!empty($params['sale_number'])) {
            $q->where('s.sale_number', 'like', '%' . $params['sale_number'] . '%');
        }
        if (!empty($params['channel'])) {
            $q->where('s.channel', '=', $params['channel']);
        }
        if (!empty($params['payment_method_name'])) {
            $q->where('spm.payment_method_name', '=', $params['payment_method_name']);
        }

        $q->select([
            's.id as sale_id',
            's.sale_number',
            's.channel',
            DB::raw("COALESCE(spm.payment_method_name, '-') as payment_method_name"),
            's.grand_total as total',
            's.tax_total as tax',
            's.created_at',
        ])
        ->orderByDesc('s.created_at');

        $p = $this->paginate($q, $perPage, $page);

        $items = collect($p->items())->map(function ($r) {
            return [
                'sale_id' => (string) $r->sale_id,
                'sale_number' => (string) $r->sale_number,
                'channel' => (string) ($r->channel ?? ''),
                'payment_method_name' => (string) ($r->payment_method_name ?? '-'),
                'total' => (int) ($r->total ?? 0),
                'tax' => (int) ($r->tax ?? 0),
                'created_at' => is_string($r->created_at)
                    ? str_replace('T', ' ', preg_replace('/\..*$/', '', $r->created_at))
                    : (optional($r->created_at)->format('Y-m-d H:i:s') ?? null),
            ];
        })->values()->all();

        return [
            'range' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'data' => $items,
            'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ];
    }

    public function discount(array $params, ?string $outletId): array
    {
        [$from, $to] = $this->resolveRange($params['date_from'] ?? null, $params['date_to'] ?? null);
        $perPage = (int) ($params['per_page'] ?? 20);
        $page = (int) ($params['page'] ?? 1);

        $pmSub = $this->salePaymentMethodSubquery();

        $q = DB::table('sales as s')
            ->leftJoinSub($pmSub, 'spm', function ($join) {
                $join->on('spm.sale_id', '=', 's.id');
            })
            ->whereBetween('s.created_at', [$from, $to])
            ->where('s.status', '=', 'PAID')
            ->where('s.discount_amount', '>', 0);

        if (!empty($outletId)) $q->where('s.outlet_id', '=', $outletId);

        if (!empty($params['sale_number'])) {
            $q->where('s.sale_number', 'like', '%' . $params['sale_number'] . '%');
        }
        if (!empty($params['channel'])) {
            $q->where('s.channel', '=', $params['channel']);
        }
        if (!empty($params['payment_method_name'])) {
            $q->where('spm.payment_method_name', '=', $params['payment_method_name']);
        }

        $q->select([
            's.id as sale_id',
            's.sale_number',
            's.channel',
            DB::raw("COALESCE(spm.payment_method_name, '-') as payment_method_name"),
            's.grand_total as total',
            's.discount_amount as discount',
            's.created_at',
        ])
        ->orderByDesc('s.created_at');

        $p = $this->paginate($q, $perPage, $page);

        $items = collect($p->items())->map(function ($r) {
            return [
                'sale_id' => (string) $r->sale_id,
                'sale_number' => (string) $r->sale_number,
                'channel' => (string) ($r->channel ?? ''),
                'payment_method_name' => (string) ($r->payment_method_name ?? '-'),
                'total' => (int) ($r->total ?? 0),
                'discount' => (int) ($r->discount ?? 0),
                'created_at' => is_string($r->created_at)
                    ? str_replace('T', ' ', preg_replace('/\..*$/', '', $r->created_at))
                    : (optional($r->created_at)->format('Y-m-d H:i:s') ?? null),
            ];
        })->values()->all();

        return [
            'range' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'data' => $items,
            'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ];
    }
}

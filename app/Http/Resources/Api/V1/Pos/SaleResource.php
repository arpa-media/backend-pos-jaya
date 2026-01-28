<?php

namespace App\Http\Resources\Api\V1\Pos;

use App\Http\Resources\Api\V1\Customers\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $s = $this->resource;

        $channelsInSale = [];
        if ($s->relationLoaded('items')) {
            $channelsInSale = $s->items
                ->pluck('channel')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [
            'id' => (string) $s->id,
            'outlet_id' => (string) $s->outlet_id,
            'cashier_id' => (string) $s->cashier_id,
            'sale_number' => (string) $s->sale_number,
            'channel' => (string) $s->channel,
            'channels' => $channelsInSale,
            'status' => (string) $s->status,

            'bill_name' => (string) $s->bill_name,
            'customer_id' => $s->customer_id ? (string) $s->customer_id : null,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($s->customer)),

            'subtotal' => (int) $s->subtotal,

            // Discount (new fields)
            'discount_type' => (string) ($s->discount_type ?? 'NONE'),
            'discount_value' => (int) ($s->discount_value ?? 0),
            'discount_amount' => (int) ($s->discount_amount ?? 0),
            'discount_reason' => $s->discount_reason,

            // Backward compat
            'discount_total' => (int) $s->discount_total,

            // Tax snapshot
            'tax_id' => $s->tax_id ? (string) $s->tax_id : null,
            'tax_name' => (string) ($s->tax_name_snapshot ?? 'Tax'),
            'tax_percent' => (int) ($s->tax_percent_snapshot ?? 0),
            'tax_total' => (int) $s->tax_total,

            'service_charge_total' => (int) $s->service_charge_total,
            'grand_total' => (int) $s->grand_total,
            'paid_total' => (int) $s->paid_total,
            'change_total' => (int) $s->change_total,

            'note' => $s->note,

            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => optional($s->created_at)->toISOString(),
            'updated_at' => optional($s->updated_at)->toISOString(),
            'deleted_at' => optional($s->deleted_at)->toISOString(),
        ];
    }
}

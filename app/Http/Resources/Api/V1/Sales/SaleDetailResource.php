<?php

namespace App\Http\Resources\Api\V1\Sales;

use App\Http\Resources\Api\V1\Customers\CustomerResource;
use App\Http\Resources\Api\V1\Pos\SaleItemResource;
use App\Http\Resources\Api\V1\Pos\SalePaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleDetailResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $s = $this->resource;

        return [
            'id' => (string) $s->id,
            'outlet_id' => (string) $s->outlet_id,

            'sale_number' => (string) $s->sale_number,
            'channel' => (string) $s->channel,
            'status' => (string) $s->status,

            'bill_name' => (string) $s->bill_name,
            'customer_id' => $s->customer_id ? (string) $s->customer_id : null,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($s->customer)),

            'cashier_id' => (string) $s->cashier_id,
            'cashier_name' => $s->cashier_name,

            'payment_method_name' => $s->payment_method_name,
            'payment_method_type' => $s->payment_method_type,

            'subtotal' => (int) $s->subtotal,

            'discount_type' => (string) ($s->discount_type ?? 'NONE'),
            'discount_value' => (int) ($s->discount_value ?? 0),
            'discount_amount' => (int) ($s->discount_amount ?? 0),
            'discount_reason' => $s->discount_reason,
            'discount_total' => (int) $s->discount_total,

            'tax_id' => $s->tax_id ? (string) $s->tax_id : null,
            // Patch-8: receipt should show "Tax (name)" instead of "No Tax".
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

            'created_at' => optional($s->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($s->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}

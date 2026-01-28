<?php

namespace App\Http\Requests\Api\V1\Customers;

use Illuminate\Foundation\Http\FormRequest;

class SearchCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // enforced by middleware permission:customer.view
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->query('phone');
        if (is_string($phone)) {
            $phone = preg_replace('/\D+/', '', $phone);
        }

        $this->merge([
            'outlet_id' => $this->query('outlet_id'),
            'phone' => $phone,
            'q' => $this->query('q'),
            'limit' => $this->query('limit'),
        ]);
    }

    public function rules(): array
    {
        return [
            // Admin: outlet id can come from X-Outlet-Id middleware
            // Cashier: backend will hard-guard outlet mismatch
            'outlet_id' => ['nullable', 'string', 'max:30'],

            // Backward compatible: old search uses exact phone
            // New: allow flexible q (name/phone partial)
            'phone' => ['nullable', 'string', 'regex:/^\d{3,15}$/'],
            'q' => ['nullable', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

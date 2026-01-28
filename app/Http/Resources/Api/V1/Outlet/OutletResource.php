<?php

namespace App\Http\Resources\Api\V1\Outlet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $outlet = $this->resource;

        return [
            'id' => (string) $outlet->id,
            'name' => (string) $outlet->name,
            'address' => $outlet->address,
            'phone' => $outlet->phone,
            'timezone' => (string) $outlet->timezone,
            'created_at' => optional($outlet->created_at)->toISOString(),
            'updated_at' => optional($outlet->updated_at)->toISOString(),
        ];
    }
}

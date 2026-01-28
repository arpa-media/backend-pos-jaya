<?php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'nisj' => $user->nisj ? (string) $user->nisj : null,
            'email' => (string) $user->email,
            'outlet' => $user->outlet ? [
                'id' => (string) $user->outlet->id,
                'code' => (string) $user->outlet->code,
                'name' => (string) $user->outlet->name,
                'timezone' => (string) ($user->outlet->timezone ?? 'Asia/Jakarta'),
            ] : null,
            'roles' => $user->roles?->pluck('name')->values() ?? [],
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\UserResource as BaseUserResource;
use Illuminate\Http\Request;

class UserResource extends BaseUserResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'user_type' => $this->is_host ? 'Host' : 'Guest',
            'statusLabel' => $this->status ? 'Active' : 'Inactive',
            'created_at' => $this->created_at?->format('d M Y h:i A'),
        ];
    }
}

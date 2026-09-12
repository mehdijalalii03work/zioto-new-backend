<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'national_id' => $this->national_code,
            'birth_date' => $this->birth_date,
            'father_name' => $this->father_name,
            'gender' => $this->gender,
            'birth_place' => $this->birth_place,
            'shahkar_verified' => $this->shahkar_verified,
            'identity_verification_status' => $this->identity_verification_status,
            'identity_verified_at' => $this->identity_verified_at?->toISOString(),
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}

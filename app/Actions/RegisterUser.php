<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'] ?? $data['name'] . "'s Org",
                'slug' => Str::slug($data['email'] . '-' . Str::random(6)),
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'],
            ]);
        });
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@faceidia.uz');
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD') ?: \Illuminate\Support\Str::random(24);
        $adminName = env('ADMIN_DEFAULT_NAME', 'Bosh Administrator');

        MoonshineUser::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            ]
        );
    }
}

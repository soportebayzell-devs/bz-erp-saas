<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates a demo tenant + admin user so you can log in immediately
     * after deploy. Change credentials before going to a real client.
     */
    public function run(): void
    {
        // ── Demo tenant ────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-academy'],
            [
                'id'            => Str::uuid(),
                'name'          => 'Demo Academy',
                'slug'          => 'demo-academy',
                'domain'        => null,
                'timezone'      => 'America/Guatemala',
                'currency'      => 'GTQ',
                'primary_color' => '#2563EB',
                'is_active'     => true,
                'settings'      => [
                    'features' => [
                        'crm'      => true,
                        'students' => true,
                        'courses'  => true,
                        'finance'  => true,
                    ],
                ],
            ]
        );

        // ── Admin user ─────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@demo-academy.com', 'tenant_id' => $tenant->id],
            [
                'id'        => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name'      => 'Admin User',
                'email'     => 'admin@demo-academy.com',
                'password'  => Hash::make('password'),   // ← change before production
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // ── Sales user ─────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'sales@demo-academy.com', 'tenant_id' => $tenant->id],
            [
                'id'        => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name'      => 'Sales Rep',
                'email'     => 'sales@demo-academy.com',
                'password'  => Hash::make('password'),
                'role'      => 'sales',
                'is_active' => true,
            ]
        );

        $this->command->info("✅ Demo tenant created: {$tenant->slug}");
        $this->command->info("✅ Admin user: admin@demo-academy.com / password");
    }
}

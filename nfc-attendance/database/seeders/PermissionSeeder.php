<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * One permission per manageable resource. A user is granted whichever
     * combination applies to them; a super admin bypasses these entirely.
     */
    public const PERMISSIONS = [
        'manage_entry_logs' => 'Manage Entry Logs',
        'manage_students' => 'Manage Students',
        'manage_employees' => 'Manage Employees',
        'manage_devices' => 'Manage Devices',
        'manage_nfc_cards' => 'Manage NFC Cards',
    ];

    public function run(): void
    {
        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}

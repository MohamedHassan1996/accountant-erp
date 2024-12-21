<?php

namespace Database\Seeders\Roles;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // premissions
        $permissions = [
            'all_users',
            'create_user',
            'edit_user',
            'update_user',
            'delete_user',
            'change_user_status',

            'all_roles',
            'create_role',
            'edit_role',
            'update_role',
            'delete_role',

            'all_projects',
            'create_project',
            'edit_project',
            'update_project',
            'delete_project',

            'all_tasks',
            'create_task',
            'edit_task',
            'update_task',
            'delete_task',

            'all-task-time-logs',
            'create-task-time-log',
            'delete-task-time-log',

            'all_tickets',
            'create_ticket',
            'edit_ticket',
            'update_ticket',
            'delete_ticket',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission], [
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // roles
        $superAdmin = Role::create(['name' => 'superAdmin']);
        $superAdmin->givePermissionTo(Permission::all());


    }
}

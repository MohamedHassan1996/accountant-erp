<?php

namespace Database\Seeders\User;

use App\Enums\User\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        //$usersCount = max((int) $this->command->ask('How many users would you like?', 10),1);

        $user = User::create([
            'first_name' => 'Mahmoud',
            'last_name' => 'Ahmed',
            'username'=> 'admin',
            'email'=> 'lTqFP@example.com',
            'status' => UserStatus::ACTIVE->value,
            'address' => 'جوجر',
            'phone' => '01018557045',
            'password' => 'M@Ns123456',
            'per_hour_rate' => 20.00
        ]);

        $role = Role::findByName('superAdmin');

        $user->assignRole($role);

    }
}

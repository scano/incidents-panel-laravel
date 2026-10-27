<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach (['ana' => 'Ana', 'luis' => 'Luis'] as $email => $name) {
            User::firstOrCreate(['email' => $email.'@example.test'], [
                'name' => $name,
                'password' => 'Incidencias123!',
            ]);
        }
    }
}

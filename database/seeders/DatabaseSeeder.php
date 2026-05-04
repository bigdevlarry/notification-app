<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::find(1) === null) {
            User::factory()->create(['id' => 1]);
        }
    }
}

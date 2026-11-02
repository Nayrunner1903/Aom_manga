<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(fn (User $user) => $this->seedFor($user));
    }

    public function seedFor(User $user): void
    {
        $user->createDefaultCategories();
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(LessonSeeder::class);
        $this->call(PatternSeeder::class);
        $this->call(ExpansionLessonSeeder::class);
        $this->call(JsonLessonSeeder::class);
        $this->call(ImageSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
    }
}

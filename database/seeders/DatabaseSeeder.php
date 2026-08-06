<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * テーブルごとのシーダーを呼び出す
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}

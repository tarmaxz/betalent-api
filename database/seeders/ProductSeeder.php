<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Notebook Dell',
            'amount' => 3500.00,
            'description' => 'Notebook Dell Inspiron 15',
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Mouse Logitech',
            'amount' => 150.00,
            'description' => 'Mouse Logitech MX Master 3',
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Teclado Mecânico',
            'amount' => 450.00,
            'description' => 'Teclado Mecânico RGB',
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Monitor LG 27"',
            'amount' => 1200.00,
            'description' => 'Monitor LG UltraWide 27 polegadas',
            'is_active' => true,
        ]);
    }
}

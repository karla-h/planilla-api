<?php

namespace Database\Seeders;

use App\Models\DiscountType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiscountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // DiscountType::factory(3)->create();
        
        DiscountType::create([
            'description' => 'Faltas',
            'value'=> 40,
        ]);

        DiscountType::create([
            'description' => 'Tardanzas',
            'value'=> 10,
        ]);
    }
}

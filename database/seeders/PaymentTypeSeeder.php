<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PaymentType::factory(3)->create();
        PaymentType::create([
            'description' => 'Horas Extras',
            'value'=> '35',
        ]);
        PaymentType::create([
            'description' => 'Bonificación',
            'value'=> '35',
        ]);
        PaymentType::create([
            'description' => 'Bono Cumpleaños',
            'value'=> '35',
        ]);
    }
}

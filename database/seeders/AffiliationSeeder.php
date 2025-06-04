<?php

namespace Database\Seeders;

use App\Models\Affiliation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AffiliationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Affiliation::factory(3)->create();
        Affiliation::create([
            'description' => 'AFP',
            'percent' => 11,
        ]);
        
        Affiliation::create([
            'description' => 'ONP',
            'percent' => 13,
        ]);
        
        Affiliation::create([
            'description' => 'Seguro',
            'percent' => 4,
        ]);
    }
}

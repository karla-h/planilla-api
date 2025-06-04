<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HeadquarterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // \App\Models\Headquarter::factory(5)->create();
        \App\Models\Headquarter::create([
            'name' => 'Pacasmayo',
            'address' => 'Pcas'
        ]);

        \App\Models\Headquarter::create([
            'name' => 'Jaén',
            'address' => 'jane'
        ]);
    }
}

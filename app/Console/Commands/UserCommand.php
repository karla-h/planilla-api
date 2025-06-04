<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Generando Usuario...');
            $res = User::create([
                'name' => '45980776',
                'role' => 'admin',
                'email' => 'andresdeza@grupoprogresando.com',
                'password'=> bcrypt('45980776L'),
            ]);
            $this->info('Usuario generado correctamente.' . $res['status']);
        } catch (\Exception $e) {
            $this->error('Error al generar el usuario: ' . $e->getMessage());
        }
    }
}

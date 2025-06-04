<?php

namespace App\Console\Commands;

use App\Architecture\Application\Services\PayRollService;
use Illuminate\Console\Command;

class PayRollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:payroll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar Planillas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Generando las planillas...');
            $res = $this->payRollService->createForAllEmployees();
            $this->info('Planillas generadas correctamente.' . $res['status']);
        } catch (\Exception $e) {
            $this->error('Error al generar las planillas: ' . $e->getMessage());
        }
    }

    /**
     * The PayRollService instance.
     *
     * @var PayRollService
     */
    protected $payRollService;

    /**
     * Crear una nueva instancia del comando.
     *
     * @param PayRollService $payRollService
     * @return void
     */
    public function __construct(PayRollService $payRollService)
    {
        parent::__construct();
        $this->payRollService = $payRollService;
    }
}

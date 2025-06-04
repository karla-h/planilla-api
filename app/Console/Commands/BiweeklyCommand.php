<?php

namespace App\Console\Commands;

use App\Architecture\Application\Services\BiweeklyPaymentService;
use Illuminate\Console\Command;

class BiweeklyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:biweekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera Pagos quincenales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Generando las quincenas...');
            $res = $this->service->createForAllEmployees();
            $this->info('Quincenas generadas correctamente. ' . json_encode($res));
        } catch (\Exception $e) {
            $this->error('Error al generar las quincenas: ' . $e->getMessage());
        }
    }

    /**
     * The BiweeklyPaymentService instance.
     *
     * @var BiweeklyPaymentService
     */
    protected BiweeklyPaymentService $service;

    /**
     * Crear una nueva instancia del comando.
     *
     * @param BiweeklyPaymentService $payRollService
     * @return void
     */
    public function __construct(BiweeklyPaymentService $service)
    {
        parent::__construct();
        $this->service = $service;
    }
}

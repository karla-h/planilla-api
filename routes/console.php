<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:payroll')->monthly();
Schedule::command('app:biweekly')
    ->daily()
    ->when(function () {
        $today = now()->day;
        $lastDay = now()->endOfMonth()->day;
        return $today === 14 || $today === ($lastDay - 1);
    });

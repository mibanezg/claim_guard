<?php

use App\Jobs\CheckExpiredLettersJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Multitenancy\Models\Tenant;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verifica cartas vencidas una vez al día para todos los tenants
Schedule::call(function () {
    Tenant::all()->each(function (Tenant $tenant) {
        CheckExpiredLettersJob::dispatch($tenant->id)->onQueue('default');
    });
})->dailyAt('07:00')->name('check-expired-letters');

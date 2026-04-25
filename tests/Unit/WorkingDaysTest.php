<?php

use App\Services\WorkingDaysService;
use Carbon\Carbon;

// WorkingDaysService no necesita BD — es pura lógica de fechas

$service = new WorkingDaysService();

describe('isWorkingDay', function () use ($service) {

    it('reconoce el fin de semana como no hábil', function () use ($service) {
        expect($service->isWorkingDay(Carbon::parse('2026-04-25')))->toBeFalse() // Sábado
            ->and($service->isWorkingDay(Carbon::parse('2026-04-26')))->toBeFalse(); // Domingo
    });

    it('reconoce feriados fijos como no hábiles', function () use ($service) {
        expect($service->isWorkingDay(Carbon::parse('2026-01-01')))->toBeFalse() // Año Nuevo
            ->and($service->isWorkingDay(Carbon::parse('2026-05-01')))->toBeFalse() // Día del Trabajo
            ->and($service->isWorkingDay(Carbon::parse('2026-09-18')))->toBeFalse() // Independencia
            ->and($service->isWorkingDay(Carbon::parse('2026-09-19')))->toBeFalse() // Glorias del Ejército
            ->and($service->isWorkingDay(Carbon::parse('2026-12-25')))->toBeFalse(); // Navidad
    });

    it('reconoce Semana Santa 2026 como no hábil', function () use ($service) {
        expect($service->isWorkingDay(Carbon::parse('2026-04-03')))->toBeFalse() // Viernes Santo
            ->and($service->isWorkingDay(Carbon::parse('2026-04-04')))->toBeFalse(); // Sábado Santo
    });

    it('un lunes laboral es hábil', function () use ($service) {
        expect($service->isWorkingDay(Carbon::parse('2026-04-27')))->toBeTrue(); // Lunes post-Easter
    });

});

describe('addWorkingDays', function () use ($service) {

    it('salta fines de semana', function () use ($service) {
        // Viernes → sumar 1 día hábil → lunes (saltando sáb y dom)
        $result = $service->addWorkingDays(Carbon::parse('2026-04-24'), 1);
        expect($result->toDateString())->toBe('2026-04-27');
    });

    it('salta feriados y fin de semana combinados', function () use ($service) {
        // Sep 15 (mar) → 16(1) 17(2) [18 feriado vier] [19 feriado+sáb] [20 dom] 21(3) 22(4) 23(5)
        $result = $service->addWorkingDays(Carbon::parse('2026-09-15'), 5);
        expect($result->toDateString())->toBe('2026-09-23');
    });

    it('retorna la misma fecha para 0 días', function () use ($service) {
        $date   = Carbon::parse('2026-06-01');
        $result = $service->addWorkingDays($date, 0);
        expect($result->toDateString())->toBe('2026-06-01');
    });

});

describe('countWorkingDays', function () use ($service) {

    it('cuenta días hábiles entre dos fechas saltando feriados', function () use ($service) {
        // 15-sep a 23-sep: hábiles son 16, 17, 21, 22, 23 = 5 (salta 18, 19, 20)
        $count = $service->countWorkingDays(
            Carbon::parse('2026-09-15'),
            Carbon::parse('2026-09-23')
        );
        expect($count)->toBe(5);
    });

    it('retorna 0 cuando las fechas son iguales', function () use ($service) {
        $same = Carbon::parse('2026-05-04');
        expect($service->countWorkingDays($same, $same))->toBe(0);
    });

});

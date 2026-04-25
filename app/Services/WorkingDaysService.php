<?php

namespace App\Services;

use Carbon\Carbon;

class WorkingDaysService
{
    /**
     * Agrega días hábiles a una fecha, excluyendo fines de semana y feriados chilenos.
     */
    public function addWorkingDays(Carbon $date, int $days): Carbon
    {
        if ($days <= 0) return $date->copy();

        $result = $date->copy()->startOfDay();
        $added  = 0;

        while ($added < $days) {
            $result->addDay();
            if ($this->isWorkingDay($result)) {
                $added++;
            }
        }

        return $result;
    }

    /**
     * Cuenta días hábiles entre dos fechas.
     */
    public function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $count  = 0;
        $cursor = $from->copy()->addDay();

        while ($cursor->lte($to)) {
            if ($this->isWorkingDay($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function isWorkingDay(Carbon $date): bool
    {
        if ($date->isWeekend()) return false;
        return !in_array($date->toDateString(), $this->getHolidays($date->year));
    }

    /**
     * Feriados chilenos para el año dado (fijos + Semana Santa precalculada).
     */
    private function getHolidays(int $year): array
    {
        $fixed = [
            "{$year}-01-01", // Año Nuevo
            "{$year}-05-01", // Día del Trabajo
            "{$year}-05-21", // Glorias Navales
            "{$year}-07-16", // Virgen del Carmen
            "{$year}-08-15", // Asunción de la Virgen
            "{$year}-09-18", // Independencia Nacional
            "{$year}-09-19", // Glorias del Ejército
            "{$year}-10-31", // Día de las Iglesias Evangélicas
            "{$year}-11-01", // Todos los Santos
            "{$year}-12-08", // Inmaculada Concepción
            "{$year}-12-25", // Navidad
        ];

        // Viernes Santo y Sábado Santo (precalculado 2025-2030)
        $easter = [
            2025 => ['2025-04-18', '2025-04-19'],
            2026 => ['2026-04-03', '2026-04-04'],
            2027 => ['2027-03-26', '2027-03-27'],
            2028 => ['2028-04-14', '2028-04-15'],
            2029 => ['2029-03-30', '2029-03-31'],
            2030 => ['2030-04-19', '2030-04-20'],
        ];

        // Día de los Pueblos Indígenas + Encuentro de Dos Mundos (observado)
        $variable = [
            2025 => ['2025-06-20', '2025-10-13'],
            2026 => ['2026-06-19', '2026-10-12'],
            2027 => ['2027-06-21', '2027-10-11'],
            2028 => ['2028-06-19', '2028-10-09'],
            2029 => ['2029-06-18', '2029-10-15'],
            2030 => ['2030-06-17', '2030-10-14'],
        ];

        return array_merge(
            $fixed,
            $easter[$year] ?? [],
            $variable[$year] ?? [],
        );
    }
}

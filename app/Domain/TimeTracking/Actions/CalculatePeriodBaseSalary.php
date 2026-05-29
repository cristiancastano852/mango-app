<?php

namespace App\Domain\TimeTracking\Actions;

use Carbon\CarbonInterface;

class CalculatePeriodBaseSalary
{
    /**
     * Prorratea el salario base mensual para el periodo [start, end] usando el mes comercial
     * de 30 días (15 por quincena), de modo que el monto no dependa de los días calendario
     * reales del mes.
     *
     * Fórmula: base = salario_mensual × (días_comerciales_pagables / 30).
     *
     * Esto colapsa los casos en una sola cuenta:
     *   - Mes completo (1–fin)        → 30 días comerciales → salario completo.
     *   - Quincena completa (1–15)    → 15 días comerciales → mitad del salario.
     *   - Quincena completa (16–fin)  → 15 días comerciales → mitad (febrero y octubre por igual).
     *   - Rango parcial (ej. 1–8)     → 8 días comerciales  → salario × 8/30.
     *
     * En esta fase los días pagables son los del rango (cubre ingreso/retiro/rango parcial);
     * el descuento por ausencias queda fuera de alcance (ver docs/novedades-y-prorrateo-por-ausencias.md).
     */
    public function execute(float $monthlySalary, CarbonInterface $start, CarbonInterface $end): float
    {
        $commercialDays = $this->commercialDaysBetween($start, $end);

        return round($monthlySalary * $commercialDays / 30, 2);
    }

    /**
     * Cuenta los días comerciales entre dos fechas (inclusive), mes a mes.
     *
     * Reglas del mes comercial:
     *   - El día 31 no cuenta (se topa en 30).
     *   - El último día de un mes corto (febrero) se completa hasta el día comercial 30,
     *     de modo que la segunda quincena siempre suma 15 días sin importar la longitud real.
     */
    public function commercialDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->lessThan($start)) {
            return 0;
        }

        $total = 0;
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $sliceStart = $start->greaterThan($monthStart) ? $start->copy() : $monthStart;
            $sliceEnd = $end->lessThan($monthEnd) ? $end->copy() : $monthEnd;

            $commercialStart = min($sliceStart->day, 30);
            $commercialEnd = $sliceEnd->isSameDay($monthEnd)
                ? 30
                : min($sliceEnd->day, 30);

            $total += max(0, $commercialEnd - $commercialStart + 1);

            // Reasignar: con CarbonImmutable (Date::use en AppServiceProvider) addMonth() no muta.
            $cursor = $cursor->addMonth();
        }

        return $total;
    }
}

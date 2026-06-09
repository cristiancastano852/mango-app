<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Salario mínimo legal mensual vigente (SMLV)
    |--------------------------------------------------------------------------
    |
    | Valor por defecto del salario base mensual con el que se siembra la
    | configuración de cada empresa nueva (surcharge_rules.default_monthly_salary).
    | Cada empresa puede editarlo desde su configuración de recargos.
    |
    | SMLV 2026 Colombia: $1.750.905 (Decreto 0159 de 2026). No incluye el
    | auxilio de transporte, que se configura por separado (ver más abajo).
    |
    */
    'smlv_monthly' => (float) env('PAYROLL_SMLV_MONTHLY', 1750905),

    /*
    |--------------------------------------------------------------------------
    | Auxilio de transporte mensual
    |--------------------------------------------------------------------------
    |
    | Valor por defecto del auxilio de transporte con el que se siembra la
    | configuración de cada empresa nueva (surcharge_rules.transport_allowance).
    | Cada empresa puede editarlo desde su configuración de recargos.
    |
    | Auxilio de transporte 2026 Colombia: $249.095 (Decretos 1469 y 1470 de
    | 2025). Solo aplica a empleados con salario mensual (modo `monthly`) que lo
    | reciben; se prorratea por periodo igual que el salario base.
    |
    */
    'transport_allowance_monthly' => (float) env('PAYROLL_TRANSPORT_ALLOWANCE_MONTHLY', 249095),

    /*
    |--------------------------------------------------------------------------
    | Divisor de horas-mes para el valor hora
    |--------------------------------------------------------------------------
    |
    | Divisor usado para derivar el valor hora por defecto a partir del salario
    | base mensual: valor_hora = salario_mensual / divisor. 220 corresponde a la
    | convención de la jornada de 42 horas semanales (reforma laboral 2025).
    | Solo aplica al sembrar el default; el admin puede editar el valor hora.
    |
    */
    'hourly_divisor' => (int) env('PAYROLL_HOURLY_DIVISOR', 220),

    /*
    |--------------------------------------------------------------------------
    | Días del mes comercial
    |--------------------------------------------------------------------------
    |
    | Base fija para prorratear el salario del periodo. La nómina colombiana usa
    | el mes comercial de 30 días (15 por quincena) sin importar que el mes real
    | tenga 28, 30 o 31 días.
    |
    */
    'commercial_month_days' => 30,

];

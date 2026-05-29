<?php

namespace App\Domain\TimeTracking\Enums;

enum PayrollDeductionReason: string
{
    case FaltaInjustificada = 'FaltaInjustificada';
    case LicenciaNoRemunerada = 'LicenciaNoRemunerada';
    case PermisoNoRemunerado = 'PermisoNoRemunerado';
    case Otro = 'Otro';
}

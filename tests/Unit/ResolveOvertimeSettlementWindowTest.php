<?php

namespace Tests\Unit;

use App\Domain\TimeTracking\Actions\ResolveOvertimeSettlementWindow;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ResolveOvertimeSettlementWindowTest extends TestCase
{
    private function resolve(string $start, string $end, string $mode): array
    {
        return (new ResolveOvertimeSettlementWindow)->execute(
            Carbon::parse($start),
            Carbon::parse($end),
            $mode,
        );
    }

    public function test_daily_mode_returns_the_period_range_unchanged(): void
    {
        $window = $this->resolve('2026-06-01', '2026-06-15', 'daily');

        $this->assertSame('2026-06-01', $window['start']);
        $this->assertSame('2026-06-15', $window['end']);
        $this->assertFalse($window['deferred']);
    }

    public function test_weekly_period_closing_midweek_settles_up_to_prior_sunday(): void
    {
        // Quincena 1: 1–15 jun (cierre lunes 15). Domingos dueños: 7 y 14.
        $window = $this->resolve('2026-06-01', '2026-06-15', 'weekly');

        $this->assertSame('2026-06-01', $window['start']);
        $this->assertSame('2026-06-14', $window['end']);
        $this->assertTrue($window['deferred']);
    }

    public function test_next_period_window_reaches_back_to_capture_deferred_week(): void
    {
        // Quincena 2: 16–30 jun. Domingos dueños: 21 y 28; la ventana arranca el lunes 15.
        $window = $this->resolve('2026-06-16', '2026-06-30', 'weekly');

        $this->assertSame('2026-06-15', $window['start']);
        $this->assertSame('2026-06-28', $window['end']);
        $this->assertTrue($window['deferred']);
    }

    public function test_weekly_period_without_any_sunday_settles_no_overtime(): void
    {
        // Lunes–viernes: ningún domingo dueño.
        $window = $this->resolve('2026-06-01', '2026-06-05', 'weekly');

        $this->assertNull($window['start']);
        $this->assertNull($window['end']);
        $this->assertTrue($window['deferred']);
    }

    public function test_weekly_period_ending_on_sunday_has_no_deferral(): void
    {
        $window = $this->resolve('2026-06-01', '2026-06-14', 'weekly');

        $this->assertSame('2026-06-01', $window['start']);
        $this->assertSame('2026-06-14', $window['end']);
        $this->assertFalse($window['deferred']);
    }
}

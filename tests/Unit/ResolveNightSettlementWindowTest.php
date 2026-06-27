<?php

namespace Tests\Unit;

use App\Domain\TimeTracking\Actions\ResolveNightSettlementWindow;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ResolveNightSettlementWindowTest extends TestCase
{
    private function resolve(string $start, string $end, string $mode): array
    {
        return (new ResolveNightSettlementWindow)->execute(
            Carbon::parse($start),
            Carbon::parse($end),
            $mode,
        );
    }

    public function test_immediate_mode_returns_the_period_range_unchanged(): void
    {
        $window = $this->resolve('2026-06-01', '2026-06-15', 'immediate');

        $this->assertSame('2026-06-01', $window['start']);
        $this->assertSame('2026-06-15', $window['end']);
        $this->assertFalse($window['deferred']);
    }

    public function test_deferred_mode_shifts_the_window_one_day_back(): void
    {
        // 2ª quincena 16–30: la ventana de recargo nocturno corre a 15–29.
        $window = $this->resolve('2026-06-16', '2026-06-30', 'deferred');

        $this->assertSame('2026-06-15', $window['start']);
        $this->assertSame('2026-06-29', $window['end']);
        $this->assertTrue($window['deferred']);
    }

    public function test_deferred_window_reaches_into_previous_month(): void
    {
        // 1ª quincena 1–15: la ventana arranca el último día del mes anterior (31 may).
        $window = $this->resolve('2026-06-01', '2026-06-15', 'deferred');

        $this->assertSame('2026-05-31', $window['start']);
        $this->assertSame('2026-06-14', $window['end']);
        $this->assertTrue($window['deferred']);
    }
}

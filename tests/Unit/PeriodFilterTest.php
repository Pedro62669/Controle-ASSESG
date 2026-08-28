<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PeriodFilter;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeriodFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-20 10:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function periodos_predefinidos_resolvem_os_intervalos_esperados(): void
    {
        $casos = [
            ['today', '2026-05-20', '2026-05-20'],
            ['month', '2026-05-01', '2026-05-31'],
            ['year', '2026-01-01', '2026-12-31'],
        ];

        foreach ($casos as [$period, $expectedStart, $expectedEnd]) {
            [$start, $end] = PeriodFilter::from($period)->resolve();

            $this->assertSame($expectedStart, $start->toDateString(), "Início incorreto para {$period}.");
            $this->assertSame($expectedEnd, $end->toDateString(), "Fim incorreto para {$period}.");
        }
    }

    #[Test]
    public function semana_cobre_sete_dias(): void
    {
        [$start, $end] = PeriodFilter::Week->resolve();

        $this->assertSame(6, (int) $start->diffInDays($end));
    }

    #[Test]
    public function periodo_customizado_usa_as_datas_informadas(): void
    {
        [$start, $end] = PeriodFilter::Custom->resolve('2026-03-10', '2026-04-05');

        $this->assertSame('2026-03-10', $start->toDateString());
        $this->assertSame('2026-04-05', $end->toDateString());
    }

    #[Test]
    public function periodo_customizado_invertido_e_corrigido(): void
    {
        [$start, $end] = PeriodFilter::Custom->resolve('2026-04-05', '2026-03-10');

        $this->assertSame('2026-03-10', $start->toDateString());
        $this->assertSame('2026-04-05', $end->toDateString());
    }

    #[Test]
    public function periodo_customizado_sem_datas_cai_no_mes_corrente(): void
    {
        [$start, $end] = PeriodFilter::Custom->resolve(null, null);

        $this->assertSame('2026-05-01', $start->toDateString());
        $this->assertSame('2026-05-31', $end->toDateString());
    }
}

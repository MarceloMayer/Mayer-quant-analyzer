<?php

namespace App\Filament\Widgets;

use App\Services\Metrics\MonthlyPerformanceService;
use Filament\Widgets\Widget;

class MonthlyPerformanceTable extends Widget
{
    protected string $view = 'filament.widgets.monthly-performance-table';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $performance = [];

    public string $heading = 'Performance mensal';

    public ?string $description = null;

    /**
     * @return array<int, string>
     */
    public function months(): array
    {
        return array_values(MonthlyPerformanceService::MONTHS);
    }

    /**
     * @return array<string, string>
     */
    public function monthLabels(): array
    {
        return [
            'Jan' => 'Jan',
            'Fev' => 'Feb',
            'Mar' => 'Mar',
            'Abr' => 'Apr',
            'Mai' => 'May',
            'Jun' => 'Jun',
            'Jul' => 'Jul',
            'Ago' => 'Aug',
            'Set' => 'Sep',
            'Out' => 'Oct',
            'Nov' => 'Nov',
            'Dez' => 'Dec',
        ];
    }

    /**
     * @return array<int, array{year: int|string, months: array<string, float|null>, ytd: float|null}>
     */
    public function rows(): array
    {
        return collect($this->performance)
            ->map(fn (array $row): array => $this->normalizeRow($row))
            ->sortByDesc(fn (array $row): int => (int) $row['year'])
            ->values()
            ->all();
    }

    public function formatMoney(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $value = (float) $value;

        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).'R$ '.number_format(abs($value), 2, ',', '.');
    }

    public function valueClasses(mixed $value, bool $isYtd = false): string
    {
        $classes = ['mqa-money-cell'];

        if ($value === null) {
            $classes[] = 'mqa-money-empty';
        } elseif ((float) $value > 0) {
            $classes[] = 'mqa-money-positive';
        } elseif ((float) $value < 0) {
            $classes[] = 'mqa-money-negative';
        } else {
            $classes[] = 'mqa-money-neutral';
        }

        if ($isYtd) {
            $classes[] = 'mqa-money-ytd';
        }

        return implode(' ', $classes);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{year: int|string, months: array<string, float|null>, ytd: float|null}
     */
    private function normalizeRow(array $row): array
    {
        $months = [];

        foreach ($this->months() as $month) {
            $value = data_get($row, "months.{$month}");
            $months[$month] = $value === null ? null : (float) $value;
        }

        return [
            'year' => $row['year'] ?? '',
            'months' => $months,
            'ytd' => array_key_exists('ytd', $row)
                ? ($row['ytd'] === null ? null : (float) $row['ytd'])
                : array_sum(array_filter($months, fn (mixed $value): bool => $value !== null)),
        ];
    }
}

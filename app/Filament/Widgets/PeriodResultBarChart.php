<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PeriodResultBarChart extends Widget
{
    protected string $view = 'filament.widgets.period-result-bar-chart';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    public string $heading = 'Resultado por período';

    public ?string $description = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bars(): array
    {
        $rows = collect($this->rows)
            ->map(fn (array $row): array => [
                'label' => (string) ($row['label'] ?? $row['key'] ?? ''),
                'value' => (float) ($row['net_profit'] ?? 0),
                'trades' => (int) ($row['trade_count'] ?? 0),
            ])
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $maxAbs = max(1.0, (float) $rows->max(fn (array $row): float => abs((float) $row['value'])));
        $width = 900;
        $height = 240;
        $left = 48;
        $right = 18;
        $top = 18;
        $bottom = 38;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $zeroY = $top + ($plotHeight / 2);
        $slot = $plotWidth / max(1, $rows->count());
        $barWidth = max(8, min(42, $slot * 0.58));

        return $rows
            ->map(function (array $row, int $index) use ($left, $slot, $barWidth, $zeroY, $plotHeight, $maxAbs): array {
                $value = (float) $row['value'];
                $barHeight = (abs($value) / $maxAbs) * ($plotHeight / 2);
                $x = $left + ($index * $slot) + (($slot - $barWidth) / 2);
                $y = $value >= 0 ? $zeroY - $barHeight : $zeroY;

                return [
                    ...$row,
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                    'width' => round($barWidth, 2),
                    'height' => round(max(1, $barHeight), 2),
                    'label_x' => round($left + ($index * $slot) + ($slot / 2), 2),
                    'zero_y' => round($zeroY, 2),
                ];
            })
            ->all();
    }

    public function formatMoney(mixed $value): string
    {
        $value = (float) $value;

        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).'R$ '.number_format(abs($value), 2, ',', '.');
    }

    public function fill(mixed $value): string
    {
        return (float) $value >= 0 ? '#10b981' : '#f43f5e';
    }
}

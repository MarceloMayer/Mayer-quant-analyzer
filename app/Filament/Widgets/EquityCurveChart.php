<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;

class EquityCurveChart extends Widget
{
    protected string $view = 'filament.widgets.equity-curve-chart';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $curve = [];

    public string $heading = 'Curva de capital';

    public ?string $description = null;

    public string $strokeColor = '#4ade80';

    public string $fillColor = '#86efac';

    public string $pointColor = '#059669';

    /**
     * @return array<int, array{date: mixed, net_profit: float, equity: float}>
     */
    public function points(): array
    {
        return collect($this->curve)
            ->map(fn (array $point): array => [
                'date' => $point['date'] ?? null,
                'net_profit' => (float) ($point['net_profit'] ?? 0),
                'equity' => (float) ($point['equity'] ?? 0),
                'label' => (string) ($point['label'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function svgPoints(): string
    {
        $points = $this->points();

        if ($points === []) {
            return '';
        }

        $width = 900;
        $height = 220;
        $padding = 18;
        $leftPadding = 54;
        $rightPadding = 18;
        $values = array_column($points, 'equity');
        $min = min($values);
        $max = max($values);

        if ($min === $max) {
            $min -= 1;
            $max += 1;
        }

        $range = $max - $min;
        $plotWidth = $width - $leftPadding - $rightPadding;
        $plotHeight = $height - ($padding * 2);
        $lastIndex = max(count($points) - 1, 1);

        return collect($values)
            ->map(function (float $value, int $index) use ($padding, $leftPadding, $plotWidth, $plotHeight, $height, $min, $range, $lastIndex): string {
                $x = $leftPadding + (($index / $lastIndex) * $plotWidth);
                $y = $height - $padding - ((($value - $min) / $range) * $plotHeight);

                return round($x, 2).','.round($y, 2);
            })
            ->implode(' ');
    }

    public function svgAreaPoints(): string
    {
        $line = $this->svgPoints();

        if ($line === '') {
            return '';
        }

        return '54,202 '.$line.' 882,202';
    }

    /**
     * @return array<int, array{label: string, y: float}>
     */
    public function yAxisTicks(): array
    {
        $points = $this->points();

        if ($points === []) {
            return [];
        }

        $values = array_column($points, 'equity');
        $min = min($values);
        $max = max($values);

        if ($min === $max) {
            $min -= 1;
            $max += 1;
        }

        $middle = $min + (($max - $min) / 2);

        return collect([$max, $middle, $min])
            ->map(fn (float $value): array => [
                'label' => $this->formatCompactMoney($value),
                'y' => $this->yPosition($value, $min, $max),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, x: float, anchor: string}>
     */
    public function xAxisTicks(): array
    {
        $points = $this->points();
        $count = count($points);

        if ($count === 0) {
            return [];
        }

        $indexes = array_values(array_unique([
            0,
            (int) floor(($count - 1) / 2),
            $count - 1,
        ]));
        $lastIndex = max($count - 1, 1);

        return collect($indexes)
            ->map(fn (int $index): array => [
                'label' => $this->formatDate($points[$index]['date'] ?? null),
                'x' => 54 + (($index / $lastIndex) * 828),
                'anchor' => match ($index) {
                    0 => 'start',
                    $count - 1 => 'end',
                    default => 'middle',
                },
            ])
            ->all();
    }

    public function formatDate(mixed $date): string
    {
        $date = $this->date($date);

        return $date?->format('d/m/Y') ?? '-';
    }

    public function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    public function formatCompactMoney(mixed $value): string
    {
        $value = (float) $value;
        $absolute = abs($value);
        $prefix = $value < 0 ? '-' : '';

        if ($absolute >= 1000000) {
            return $prefix.number_format($absolute / 1000000, 1, ',', '.').' mi';
        }

        if ($absolute >= 1000) {
            return $prefix.number_format($absolute / 1000, 1, ',', '.').' mil';
        }

        return $prefix.number_format($absolute, 0, ',', '.');
    }

    public function moneyClasses(mixed $value): string
    {
        return match (true) {
            (float) $value > 0 => 'text-emerald-600 dark:text-emerald-400',
            (float) $value < 0 => 'text-rose-600 dark:text-rose-400',
            default => 'text-gray-600 dark:text-gray-300',
        };
    }

    public function startDate(): string
    {
        $points = $this->points();

        return $this->formatDate($points[0]['date'] ?? null);
    }

    public function endDate(): string
    {
        $points = $this->points();
        $lastPoint = $points[array_key_last($points)] ?? null;

        return $this->formatDate($lastPoint['date'] ?? null);
    }

    public function finalEquity(): float
    {
        $points = $this->points();
        $lastPoint = $points[array_key_last($points)] ?? null;

        return (float) ($lastPoint['equity'] ?? 0);
    }

    public function chartStrokeClasses(): string
    {
        return 'stroke-emerald-500 dark:stroke-emerald-300';
    }

    public function chartFillClasses(): string
    {
        return 'fill-emerald-100/80 dark:fill-emerald-500/20';
    }

    public function pointTooltip(array $point): string
    {
        return trim(($point['label'] ?: $this->formatDate($point['date'] ?? null)).' | '.$this->formatMoney($point['equity'] ?? 0));
    }

    private function yPosition(float $value, float $min, float $max): float
    {
        $height = 220;
        $padding = 18;
        $range = $max - $min;
        $plotHeight = $height - ($padding * 2);

        return round($height - $padding - ((($value - $min) / $range) * $plotHeight), 2);
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}

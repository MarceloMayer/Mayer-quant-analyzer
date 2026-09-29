<?php

namespace App\Services\Charts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns one or more dated equity series into the SVG geometry (polylines, ticks, zero line)
 * consumed by the shared `filament.partials.dual-line-chart` view. All series share the same
 * time and value axes, so the curves are directly comparable.
 */
class DatedLineChartBuilder
{
    private const CHART_WIDTH = 900;

    private const CHART_HEIGHT = 260;

    private const CHART_TOP = 18;

    private const CHART_BOTTOM = 212;

    private const CHART_LEFT = 62;

    private const CHART_RIGHT = 882;

    private const MAX_CHART_POINTS = 320;

    /**
     * @param  array<int, array{label: string, color: string, points: array<int, array{date?: mixed, equity?: float|int}>}>  $series
     * @return array<string, mixed>
     */
    public function build(array $series): array
    {
        $series = array_map(fn (array $item): array => [
            'label' => $item['label'],
            'color' => $item['color'],
            'points' => $this->datedEquity($item['points']),
        ], $series);

        $allPoints = array_merge(...array_column($series, 'points'));

        if ($allPoints === []) {
            return ['has_data' => false, 'series' => [], 'y_ticks' => [], 'x_ticks' => [], 'zero_y' => null];
        }

        $timestamps = array_column($allPoints, 'timestamp');
        $values = array_column($allPoints, 'equity');
        $minTimestamp = min($timestamps);
        $maxTimestamp = max($timestamps);
        $minValue = min(min($values), 0.0);
        $maxValue = max(max($values), 0.0);

        if ($minValue === $maxValue) {
            $minValue -= 1;
            $maxValue += 1;
        }

        $headroom = ($maxValue - $minValue) * 0.04;
        $minValue -= $headroom;
        $maxValue += $headroom;

        if ($minTimestamp === $maxTimestamp) {
            $maxTimestamp = $minTimestamp + 1;
        }

        $plotted = collect($series)
            ->map(function (array $item) use ($minTimestamp, $maxTimestamp, $minValue, $maxValue): array {
                $points = $this->downsample($item['points']);
                $coordinates = collect($points)
                    ->map(fn (array $point): string => $this->x($point['timestamp'], $minTimestamp, $maxTimestamp)
                        .','
                        .$this->y((float) $point['equity'], $minValue, $maxValue))
                    ->implode(' ');
                $lastPoint = $points === [] ? null : $points[array_key_last($points)];

                return [
                    'label' => $item['label'],
                    'color' => $item['color'],
                    'line' => $coordinates,
                    'has_points' => $points !== [],
                    'final_equity' => $lastPoint === null ? 0.0 : round((float) $lastPoint['equity'], 2),
                    'final_x' => $lastPoint === null ? null : $this->x($lastPoint['timestamp'], $minTimestamp, $maxTimestamp),
                    'final_y' => $lastPoint === null ? null : $this->y((float) $lastPoint['equity'], $minValue, $maxValue),
                ];
            })
            ->all();

        return [
            'has_data' => true,
            'width' => self::CHART_WIDTH,
            'height' => self::CHART_HEIGHT,
            'left' => self::CHART_LEFT,
            'right' => self::CHART_RIGHT,
            'top' => self::CHART_TOP,
            'bottom' => self::CHART_BOTTOM,
            'series' => $plotted,
            'y_ticks' => $this->yTicks($minValue, $maxValue),
            'x_ticks' => $this->xTicks($minTimestamp, $maxTimestamp),
            'zero_y' => $minValue <= 0 && $maxValue >= 0 ? $this->y(0.0, $minValue, $maxValue) : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array<int, array{timestamp: int, equity: float, date: string}>
     */
    private function datedEquity(array $equityCurve): array
    {
        return collect($equityCurve)
            ->map(function (array $point): ?array {
                $date = $this->date($point['date'] ?? null);

                if ($date === null) {
                    return null;
                }

                return [
                    'timestamp' => $date->getTimestamp(),
                    'equity' => (float) ($point['equity'] ?? 0),
                    'date' => $date->format('Y-m-d'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{timestamp: int, equity: float, date: string}>  $points
     * @return array<int, array{timestamp: int, equity: float, date: string}>
     */
    private function downsample(array $points): array
    {
        $count = count($points);

        if ($count <= self::MAX_CHART_POINTS) {
            return $points;
        }

        $step = $count / self::MAX_CHART_POINTS;
        $sampled = [];

        for ($index = 0; $index < self::MAX_CHART_POINTS; $index++) {
            $sampled[] = $points[(int) floor($index * $step)];
        }

        $sampled[] = $points[$count - 1];

        return $sampled;
    }

    private function x(int $timestamp, int $minTimestamp, int $maxTimestamp): float
    {
        $ratio = ($timestamp - $minTimestamp) / max($maxTimestamp - $minTimestamp, 1);

        return round(self::CHART_LEFT + ($ratio * (self::CHART_RIGHT - self::CHART_LEFT)), 2);
    }

    private function y(float $value, float $minValue, float $maxValue): float
    {
        $ratio = ($value - $minValue) / max($maxValue - $minValue, 0.0000001);

        return round(self::CHART_BOTTOM - ($ratio * (self::CHART_BOTTOM - self::CHART_TOP)), 2);
    }

    /**
     * @return array<int, array{label: string, y: float}>
     */
    private function yTicks(float $minValue, float $maxValue): array
    {
        $middle = $minValue + (($maxValue - $minValue) / 2);

        return collect([$maxValue, $middle, $minValue])
            ->map(fn (float $value): array => [
                'label' => $this->compactMoney($value),
                'y' => $this->y($value, $minValue, $maxValue),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, x: float, anchor: string}>
     */
    private function xTicks(int $minTimestamp, int $maxTimestamp): array
    {
        $middleTimestamp = (int) (($minTimestamp + $maxTimestamp) / 2);

        return [
            ['label' => $this->formatTimestamp($minTimestamp), 'x' => $this->x($minTimestamp, $minTimestamp, $maxTimestamp), 'anchor' => 'start'],
            ['label' => $this->formatTimestamp($middleTimestamp), 'x' => $this->x($middleTimestamp, $minTimestamp, $maxTimestamp), 'anchor' => 'middle'],
            ['label' => $this->formatTimestamp($maxTimestamp), 'x' => $this->x($maxTimestamp, $minTimestamp, $maxTimestamp), 'anchor' => 'end'],
        ];
    }

    private function formatTimestamp(int $timestamp): string
    {
        return CarbonImmutable::createFromTimestamp($timestamp)->format('d/m/Y');
    }

    private function compactMoney(float $value): string
    {
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

    private function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value);
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

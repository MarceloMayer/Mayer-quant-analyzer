<?php

namespace App\Services\Reports;

use App\Services\Metrics\MonthlyPerformanceService;
use Carbon\CarbonImmutable;

/**
 * Draws the report charts as standalone SVG images (returned as data URIs). The PDF renderer
 * runs no JavaScript, so none of the on-screen Livewire/Alpine charts can be reused there.
 * Everything is laid out in a fixed 1000-unit-wide viewBox; the report scales it to page width.
 */
class PdfChartBuilder
{
    private const WIDTH = 1000;

    private const LEFT = 92;

    private const RIGHT = 18;

    private const TOP = 16;

    private const BOTTOM = 40;

    private const MAX_POINTS = 420;

    private const FONT = 'Helvetica, Arial, sans-serif';

    private const GRID = '#e5e7eb';

    private const MUTED = '#6b7280';

    private const GREEN = '#059669';

    private const RED = '#e11d48';

    /**
     * @param  array<int, array<string, mixed>>  $curve  Points with `date` and `equity` keys.
     */
    public function equityCurve(array $curve, int $height = 265): ?string
    {
        $points = $this->timePoints($curve, 'equity');

        if (count($points) < 2) {
            return null;
        }

        return $this->timeSeries($this->downsample($points, 'last'), $height, self::GREEN, includeZero: true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $curve  Points with `date` and `value` keys (<= 0).
     */
    public function drawdownCurve(array $curve, int $height = 280): ?string
    {
        $points = $this->timePoints($curve, 'value');

        if (count($points) < 2) {
            return null;
        }

        return $this->timeSeries($this->downsample($points, 'min'), $height, self::RED, includeZero: true);
    }

    /**
     * One bar per calendar month between the first and last month that had trades.
     *
     * @param  array<int, array<string, mixed>>  $monthlyTable  Rows from the analyzer's `monthly_table`.
     */
    public function monthlyBars(array $monthlyTable, int $height = 280): ?string
    {
        $bars = [];
        $started = false;
        $pendingGap = [];
        $months = array_values(MonthlyPerformanceService::MONTHS);

        $rows = collect($monthlyTable)->sortBy(fn (array $row): int => (int) $row['year'])->values();

        foreach ($rows as $row) {
            foreach (array_values($row['months'] ?? []) as $index => $month) {
                $hasTrades = (int) ($month['trades'] ?? 0) > 0;
                $bar = [
                    'value' => (float) ($month['profit'] ?? 0),
                    'label' => (string) $row['year'],
                    'title' => $months[$index] ?? '',
                ];

                if ($hasTrades) {
                    $started = true;
                    array_push($bars, ...$pendingGap);
                    $pendingGap = [];
                    $bars[] = $bar;
                } elseif ($started) {
                    $pendingGap[] = ['value' => 0.0] + $bar;
                }
            }
        }

        if ($bars === []) {
            return null;
        }

        return $this->bars($bars, $height, showValues: false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyTable
     */
    public function yearlyBars(array $monthlyTable, int $height = 250): ?string
    {
        $bars = collect($monthlyTable)
            ->sortBy(fn (array $row): int => (int) $row['year'])
            ->map(fn (array $row): array => [
                'value' => (float) ($row['total'] ?? 0),
                'label' => (string) $row['year'],
                'title' => (string) $row['year'],
            ])
            ->values()
            ->all();

        return $bars === [] ? null : $this->bars($bars, $height, showValues: true);
    }

    /**
     * @param  array<int, array{timestamp: int, value: float}>  $points
     */
    private function timeSeries(array $points, int $height, string $color, bool $includeZero): string
    {
        $timestamps = array_column($points, 'timestamp');
        $values = array_column($points, 'value');
        $minTs = min($timestamps);
        $maxTs = max($timestamps);
        $minTs === $maxTs && $maxTs++;

        [$ticks, $minValue, $maxValue] = $this->niceScale(
            $includeZero ? min(min($values), 0.0) : min($values),
            $includeZero ? max(max($values), 0.0) : max($values),
        );

        $plotLeft = self::LEFT;
        $plotRight = self::WIDTH - self::RIGHT;
        $plotTop = self::TOP;
        $plotBottom = $height - self::BOTTOM;

        $x = fn (int $ts): float => $plotLeft + (($ts - $minTs) / ($maxTs - $minTs)) * ($plotRight - $plotLeft);
        $y = fn (float $v): float => $plotBottom - (($v - $minValue) / ($maxValue - $minValue)) * ($plotBottom - $plotTop);

        $svg = $this->open($height);

        foreach ($ticks as $tick) {
            $ty = round($y($tick), 1);
            $svg .= '<line x1="'.$plotLeft.'" y1="'.$ty.'" x2="'.$plotRight.'" y2="'.$ty.'" stroke="'.self::GRID.'" stroke-width="1"/>';
            $svg .= $this->text($plotLeft - 12, $ty + 7, $this->compactMoney($tick), 'end');
        }

        $dateTicks = $this->dateTicks($minTs, $maxTs, 6);

        foreach ($dateTicks as $index => $tickTs) {
            $tx = round($x($tickTs), 1);
            $anchor = match (true) {
                $index === 0 => 'start',
                $index === array_key_last($dateTicks) => 'end',
                default => 'middle',
            };
            $svg .= '<line x1="'.$tx.'" y1="'.$plotBottom.'" x2="'.$tx.'" y2="'.($plotBottom + 6).'" stroke="'.self::GRID.'" stroke-width="1"/>';
            $svg .= $this->text($tx, $plotBottom + 28, CarbonImmutable::createFromTimestamp($tickTs)->format('m/Y'), $anchor);
        }

        $line = collect($points)
            ->map(fn (array $p): string => round($x($p['timestamp']), 1).','.round($y($p['value']), 1))
            ->implode(' ');
        $baseline = round($y(0.0), 1);
        $first = $points[0];
        $last = $points[array_key_last($points)];

        $svg .= '<polygon points="'.round($x($first['timestamp']), 1).','.$baseline.' '.$line.' '.round($x($last['timestamp']), 1).','.$baseline.'" fill="'.$color.'" fill-opacity="0.14" stroke="none"/>';
        $svg .= '<line x1="'.$plotLeft.'" y1="'.$baseline.'" x2="'.$plotRight.'" y2="'.$baseline.'" stroke="#9ca3af" stroke-width="1.5"/>';
        $svg .= '<polyline points="'.$line.'" fill="none" stroke="'.$color.'" stroke-width="3" stroke-linejoin="round"/>';
        $svg .= '<circle cx="'.round($x($last['timestamp']), 1).'" cy="'.round($y($last['value']), 1).'" r="6" fill="'.$color.'"/>';

        return $this->close($svg);
    }

    /**
     * @param  array<int, array{value: float, label: string, title: string}>  $bars
     */
    private function bars(array $bars, int $height, bool $showValues): string
    {
        $values = array_column($bars, 'value');
        [$ticks, $minValue, $maxValue] = $this->niceScale(min(min($values), 0.0), max(max($values), 0.0));

        $plotLeft = self::LEFT;
        $plotRight = self::WIDTH - self::RIGHT;
        $plotTop = self::TOP + ($showValues ? 16 : 0);
        $plotBottom = $height - self::BOTTOM;
        $slot = ($plotRight - $plotLeft) / count($bars);
        $barWidth = min($slot * 0.72, 90);

        $y = fn (float $v): float => $plotBottom - (($v - $minValue) / ($maxValue - $minValue)) * ($plotBottom - $plotTop);
        $zero = round($y(0.0), 1);

        $svg = $this->open($height);

        foreach ($ticks as $tick) {
            $ty = round($y($tick), 1);
            $svg .= '<line x1="'.$plotLeft.'" y1="'.$ty.'" x2="'.$plotRight.'" y2="'.$ty.'" stroke="'.self::GRID.'" stroke-width="1"/>';
            $svg .= $this->text($plotLeft - 12, $ty + 7, $this->compactMoney($tick), 'end');
        }

        foreach ($bars as $index => $bar) {
            $center = $plotLeft + ($index + 0.5) * $slot;
            $left = round($center - $barWidth / 2, 1);
            $top = round($y(max($bar['value'], 0.0)), 1);
            $bottom = round($y(min($bar['value'], 0.0)), 1);
            $barHeight = max($bottom - $top, $bar['value'] === 0.0 ? 0 : 1.5);
            $color = $bar['value'] >= 0 ? self::GREEN : self::RED;

            if ($barHeight > 0) {
                $svg .= '<rect x="'.$left.'" y="'.$top.'" width="'.round($barWidth, 1).'" height="'.round($barHeight, 1).'" fill="'.$color.'" fill-opacity="0.88"/>';
            }

            if ($showValues) {
                $labelY = $bar['value'] >= 0 ? $top - 8 : $bottom + 22;
                $svg .= $this->text(round($center, 1), round($labelY, 1), $this->compactMoney($bar['value'], signed: true), 'middle', $color, true);
            }

            if ($showValues) {
                $svg .= $this->text(round($center, 1), $plotBottom + 28, $bar['label'], 'middle');
            }
        }

        if (! $showValues) {
            $svg .= $this->yearLabels($bars, $plotLeft, $slot, $plotBottom);
        }

        $svg .= '<line x1="'.$plotLeft.'" y1="'.$zero.'" x2="'.$plotRight.'" y2="'.$zero.'" stroke="#9ca3af" stroke-width="1.5"/>';

        return $this->close($svg);
    }

    /**
     * Centres each year label under its run of monthly bars, with a divider between years.
     *
     * @param  array<int, array{value: float, label: string, title: string}>  $bars
     */
    private function yearLabels(array $bars, float $plotLeft, float $slot, float $plotBottom): string
    {
        $runs = [];

        foreach ($bars as $index => $bar) {
            $runs[$bar['label']] ??= ['start' => $index, 'end' => $index];
            $runs[$bar['label']]['end'] = $index;
        }

        $svg = '';

        foreach ($runs as $label => $run) {
            $left = $plotLeft + $run['start'] * $slot;
            $right = $plotLeft + ($run['end'] + 1) * $slot;

            $svg .= '<line x1="'.round($left, 1).'" y1="'.$plotBottom.'" x2="'.round($left, 1).'" y2="'.($plotBottom + 8).'" stroke="#9ca3af" stroke-width="1"/>';

            if ($right - $left >= 62) {
                $svg .= $this->text(round(($left + $right) / 2, 1), $plotBottom + 28, (string) $label, 'middle');
            }
        }

        $end = round($plotLeft + count($bars) * $slot, 1);

        return $svg.'<line x1="'.$end.'" y1="'.$plotBottom.'" x2="'.$end.'" y2="'.($plotBottom + 8).'" stroke="#9ca3af" stroke-width="1"/>';
    }

    private function open(int $height): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::WIDTH.'" height="'.$height.'" viewBox="0 0 '.self::WIDTH.' '.$height.'">'
            .'<rect x="0" y="0" width="'.self::WIDTH.'" height="'.$height.'" fill="#ffffff"/>';
    }

    private function close(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg.'</svg>');
    }

    private function text(float|int $x, float|int $y, string $content, string $anchor, string $fill = self::MUTED, bool $bold = false): string
    {
        return '<text x="'.$x.'" y="'.$y.'" font-family="'.self::FONT.'" font-size="21" text-anchor="'.$anchor.'" fill="'.$fill.'"'
            .($bold ? ' font-weight="bold"' : '').'>'.htmlspecialchars($content, ENT_XML1).'</text>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $curve
     * @return array<int, array{timestamp: int, value: float}>
     */
    private function timePoints(array $curve, string $key): array
    {
        $points = [];

        foreach ($curve as $point) {
            if (blank($point['date'] ?? null)) {
                continue;
            }

            try {
                $timestamp = CarbonImmutable::parse((string) $point['date'])->getTimestamp();
            } catch (\Throwable) {
                continue;
            }

            $points[] = ['timestamp' => $timestamp, 'value' => (float) ($point[$key] ?? 0)];
        }

        return $points;
    }

    /**
     * Keeps the chart light: one point per bucket, picking the last value (equity) or the deepest
     * value (drawdown, so troughs are never smoothed away).
     *
     * @param  array<int, array{timestamp: int, value: float}>  $points
     * @return array<int, array{timestamp: int, value: float}>
     */
    private function downsample(array $points, string $mode): array
    {
        $count = count($points);

        if ($count <= self::MAX_POINTS) {
            return $points;
        }

        $sampled = [];
        $bucketSize = $count / self::MAX_POINTS;

        for ($bucket = 0; $bucket < self::MAX_POINTS; $bucket++) {
            $slice = array_slice($points, (int) floor($bucket * $bucketSize), max((int) ceil($bucketSize), 1));

            if ($slice === []) {
                continue;
            }

            $sampled[] = $mode === 'min'
                ? array_reduce($slice, fn (?array $carry, array $p): array => $carry === null || $p['value'] < $carry['value'] ? $p : $carry)
                : $slice[array_key_last($slice)];
        }

        $sampled[] = $points[$count - 1];

        return $sampled;
    }

    /**
     * @return array{0: array<int, float>, 1: float, 2: float}
     */
    private function niceScale(float $min, float $max, int $targetTicks = 5): array
    {
        if ($min === $max) {
            $max = $min + 1;
        }

        $step = $this->niceNumber(($max - $min) / max($targetTicks - 1, 1), round: true);
        $niceMin = floor($min / $step) * $step;
        $niceMax = ceil($max / $step) * $step;
        $ticks = [];

        for ($tick = $niceMin; $tick <= $niceMax + $step / 2; $tick += $step) {
            $ticks[] = round($tick, 6);
        }

        return [$ticks, $niceMin, $niceMax];
    }

    private function niceNumber(float $range, bool $round): float
    {
        $exponent = floor(log10(max($range, 1e-9)));
        $fraction = $range / (10 ** $exponent);

        $niceFraction = match (true) {
            $round && $fraction < 1.5 => 1,
            $round && $fraction < 3 => 2,
            $round && $fraction < 7 => 5,
            $round => 10,
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 5 => 5,
            default => 10,
        };

        return $niceFraction * (10 ** $exponent);
    }

    /**
     * @return array<int, int>
     */
    private function dateTicks(int $minTs, int $maxTs, int $count): array
    {
        $ticks = [];

        for ($i = 0; $i < $count; $i++) {
            $ticks[] = (int) round($minTs + (($maxTs - $minTs) * $i / ($count - 1)));
        }

        return $ticks;
    }

    private function compactMoney(float $value, bool $signed = false): string
    {
        $absolute = abs($value);
        $sign = $value < 0 ? '-' : ($signed && $value > 0 ? '+' : '');

        return match (true) {
            $absolute >= 1_000_000 => $sign.rtrim(rtrim(number_format($absolute / 1_000_000, 2, ',', '.'), '0'), ',').' mi',
            $absolute >= 10_000 => $sign.rtrim(rtrim(number_format($absolute / 1000, 1, ',', '.'), '0'), ',').' mil',
            default => $sign.number_format($absolute, 0, ',', '.'),
        };
    }
}

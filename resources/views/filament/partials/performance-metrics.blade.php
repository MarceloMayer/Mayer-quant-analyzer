@php
    $formatMoney = fn (float|int|null $value): string => number_format((float) $value, 2, ',', '.');
    $formatPercent = fn (float|int|null $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $monthLabels = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];

    $linePoints = function (array $series, string $key = 'value'): string {
        if ($series === []) {
            return '';
        }

        $width = 900;
        $height = 220;
        $values = array_map(fn (array $point): float => (float) $point[$key], $series);
        $min = min($values);
        $max = max($values);

        if ($min === $max) {
            $min -= 1;
            $max += 1;
        }

        $range = $max - $min;
        $lastIndex = max(count($series) - 1, 1);

        return collect($values)
            ->map(function (float $value, int $index) use ($width, $height, $min, $range, $lastIndex): string {
                $x = ($index / $lastIndex) * $width;
                $y = $height - ((($value - $min) / $range) * $height);

                return round($x, 2) . ',' . round($y, 2);
            })
            ->implode(' ');
    };

    $equityCurve = $metrics['equity_curve'] ?? [];
    $drawdownCurve = $metrics['drawdown_curve'] ?? [];
    $monthlyTable = $metrics['monthly_table'] ?? [];
    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;
@endphp

<div class="space-y-6">
    @if (! $hasTrades)
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            {{ $emptyMessage }}
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Trades</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $metrics['total_trades'] }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Resultado líquido</div>
                <div @class([
                    'mt-2 text-2xl font-semibold',
                    'text-emerald-600 dark:text-emerald-400' => $metrics['net_profit'] >= 0,
                    'text-rose-600 dark:text-rose-400' => $metrics['net_profit'] < 0,
                ])>{{ $formatMoney($metrics['net_profit']) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Taxa de acerto</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatPercent($metrics['win_rate']) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Fator de lucro</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $metrics['profit_factor'] ?? '∞' }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Drawdown máximo</div>
                <div class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ $formatMoney($metrics['max_drawdown']) }}</div>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Média por trade</div>
                <div class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $formatMoney($metrics['average_trade']) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Lucro bruto</div>
                <div class="mt-2 text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ $formatMoney($metrics['gross_profit']) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Perda bruta</div>
                <div class="mt-2 text-lg font-semibold text-rose-600 dark:text-rose-400">{{ $formatMoney($metrics['gross_loss']) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Melhor trade</div>
                <div class="mt-2 text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ $formatMoney($metrics['best_trade']) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Pior trade</div>
                <div class="mt-2 text-lg font-semibold text-rose-600 dark:text-rose-400">{{ $formatMoney($metrics['worst_trade']) }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">Curva de capital</div>
                <svg viewBox="0 0 900 260" class="h-72 w-full overflow-visible" preserveAspectRatio="none" role="img">
                    <line x1="0" y1="230" x2="900" y2="230" class="stroke-gray-200 dark:stroke-gray-800" stroke-width="1" />
                    <polyline points="{{ $linePoints($equityCurve) }}" fill="none" class="stroke-primary-600 dark:stroke-primary-400" stroke-width="3" vector-effect="non-scaling-stroke" />
                </svg>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">Drawdown</div>
                <svg viewBox="0 0 900 260" class="h-72 w-full overflow-visible" preserveAspectRatio="none" role="img">
                    <line x1="0" y1="20" x2="900" y2="20" class="stroke-gray-200 dark:stroke-gray-800" stroke-width="1" />
                    <polyline points="{{ $linePoints($drawdownCurve) }}" fill="none" class="stroke-rose-600 dark:stroke-rose-400" stroke-width="3" vector-effect="non-scaling-stroke" />
                </svg>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-950 dark:border-gray-800 dark:text-white">Tabela mensal por ano</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Ano</th>
                            @foreach ($monthLabels as $label)
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">{{ $label }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Total</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Trades</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($monthlyTable as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $row['year'] }}</td>
                                @foreach ($row['months'] as $month)
                                    <td @class([
                                        'whitespace-nowrap px-3 py-2 text-right',
                                        'text-emerald-600 dark:text-emerald-400' => $month['profit'] > 0,
                                        'text-rose-600 dark:text-rose-400' => $month['profit'] < 0,
                                        'text-gray-500 dark:text-gray-400' => $month['profit'] == 0,
                                    ])>{{ $formatMoney($month['profit']) }}</td>
                                @endforeach
                                <td @class([
                                    'whitespace-nowrap px-3 py-2 text-right font-semibold',
                                    'text-emerald-600 dark:text-emerald-400' => $row['total'] > 0,
                                    'text-rose-600 dark:text-rose-400' => $row['total'] < 0,
                                    'text-gray-500 dark:text-gray-400' => $row['total'] == 0,
                                ])>{{ $formatMoney($row['total']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $row['trades'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@once
    <style>
        .mqa-report-card {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .dark .mqa-report-card {
            border-color: rgb(31 41 55);
            background: rgb(17 24 39);
        }

        .mqa-report-header {
            border-bottom: 1px solid rgb(229 231 235);
            padding: 0.875rem 1rem;
        }

        .dark .mqa-report-header {
            border-color: rgb(31 41 55);
        }

        .mqa-report-title {
            color: rgb(17 24 39);
            font-size: 0.875rem;
            font-weight: 650;
            line-height: 1.25rem;
        }

        .dark .mqa-report-title {
            color: rgb(255 255 255);
        }

        .mqa-report-description {
            margin-top: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-report-description {
            color: rgb(156 163 175);
        }

        .mqa-table-scroll {
            overflow-x: auto;
            padding: 1rem;
        }

        .mqa-financial-table {
            width: 100%;
            min-width: 1680px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.8125rem;
            line-height: 1.25rem;
        }

        .mqa-financial-table th,
        .mqa-financial-table td {
            border: 1px solid rgb(229 231 235);
            padding: 0.625rem 0.75rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .dark .mqa-financial-table th,
        .dark .mqa-financial-table td {
            border-color: rgb(55 65 81);
        }

        .mqa-financial-table thead th {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
            font-weight: 650;
        }

        .dark .mqa-financial-table thead th {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .mqa-financial-table tbody tr:nth-child(even) td {
            background: rgb(249 250 251);
        }

        .dark .mqa-financial-table tbody tr:nth-child(even) td {
            background: rgb(15 23 42);
        }

        .mqa-text-left {
            text-align: left;
        }

        .mqa-text-right {
            text-align: right;
        }

        .mqa-money-cell {
            text-align: right;
            font-weight: 600;
        }

        .mqa-money-positive {
            background: rgb(236 253 245) !important;
            color: rgb(4 120 87);
        }

        .mqa-money-negative {
            background: rgb(255 241 242) !important;
            color: rgb(190 18 60);
        }

        .mqa-money-neutral,
        .mqa-money-empty {
            background: rgb(249 250 251) !important;
            color: rgb(107 114 128);
        }

        .mqa-money-empty {
            text-align: center;
            font-weight: 500;
        }

        .mqa-money-ytd {
            border-left: 2px solid rgb(156 163 175) !important;
            font-weight: 750;
        }

        .dark .mqa-money-positive {
            background: rgb(6 78 59 / 0.32) !important;
            color: rgb(110 231 183);
        }

        .dark .mqa-money-negative {
            background: rgb(127 29 29 / 0.35) !important;
            color: rgb(253 164 175);
        }

        .dark .mqa-money-neutral,
        .dark .mqa-money-empty {
            background: rgb(31 41 55 / 0.72) !important;
            color: rgb(156 163 175);
        }
    </style>
@endonce

<x-filament-widgets::widget>
    <div class="mqa-report-card">
        @if (filled($heading) || filled($description))
            <div class="mqa-report-header">
                @if (filled($heading))
                    <div class="mqa-report-title">{{ $heading }}</div>
                @endif

                @if (filled($description))
                    <p class="mqa-report-description">{{ $description }}</p>
                @endif
            </div>
        @endif

        <div class="mqa-table-scroll">
            <table class="mqa-financial-table">
                <colgroup>
                    <col style="width: 96px;">
                    @foreach ($this->months() as $month)
                        <col style="width: 126px;">
                    @endforeach
                    <col style="width: 148px;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="mqa-text-left">Year</th>
                        @foreach ($this->months() as $month)
                            <th class="mqa-text-right">{{ $this->monthLabels()[$month] ?? $month }}</th>
                        @endforeach
                        <th class="mqa-text-right">YTD</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows() as $row)
                        <tr>
                            <td class="mqa-text-left" style="font-weight: 700;">{{ $row['year'] }}</td>

                            @foreach ($this->months() as $month)
                                @php
                                    $value = array_key_exists($month, $row['months']) ? $row['months'][$month] : null;
                                @endphp

                                <td class="{{ $this->valueClasses($value) }}">{{ $this->formatMoney($value) }}</td>
                            @endforeach

                            <td class="{{ $this->valueClasses($row['ytd'], true) }}">{{ $this->formatMoney($row['ytd']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" style="padding: 1rem; text-align: center; color: rgb(107 114 128);">
                                Nenhuma performance mensal disponível.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-widgets::widget>

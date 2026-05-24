<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DrawdownInfoWidget extends Widget
{
    protected string $view = 'filament.widgets.drawdown-info-widget';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public string $heading = 'Drawdown';

    public float|int|null $max_drawdown = null;

    public float|int|null $max_drawdown_percent = null;

    public float|int|null $peak = null;

    public float|int|null $valley = null;

    public function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    public function formatPercent(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.').'%';
    }

    public function negativeMoney(mixed $value): string
    {
        $value = abs((float) $value);

        return $value > 0 ? "-{$this->formatMoney($value)}" : $this->formatMoney(0);
    }

    public function negativePercent(mixed $value): string
    {
        $value = abs((float) $value);

        return $value > 0 ? "-{$this->formatPercent($value)}" : $this->formatPercent(0);
    }

    public function drawdownClasses(mixed $value): string
    {
        return (float) $value > 0
            ? 'text-rose-600 dark:text-rose-400'
            : 'text-gray-600 dark:text-gray-300';
    }

    /**
     * @return array<int, array{label: string, value: string, classes: string}>
     */
    public function items(): array
    {
        return [
            [
                'label' => 'Drawdown máximo',
                'value' => $this->negativeMoney($this->max_drawdown),
                'classes' => $this->drawdownClasses($this->max_drawdown),
            ],
            [
                'label' => 'Drawdown percentual',
                'value' => $this->negativePercent($this->max_drawdown_percent),
                'classes' => $this->drawdownClasses($this->max_drawdown_percent),
            ],
            [
                'label' => 'Topo da curva',
                'value' => $this->formatMoney($this->peak),
                'classes' => 'text-gray-950 dark:text-white',
            ],
            [
                'label' => 'Fundo da curva',
                'value' => $this->formatMoney($this->valley),
                'classes' => 'text-gray-950 dark:text-white',
            ],
        ];
    }
}

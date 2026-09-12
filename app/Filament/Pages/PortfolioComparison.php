<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Metrics\PortfolioComparisonService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class PortfolioComparison extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Comparar Portfólios';

    protected static ?string $title = 'Comparação de Portfólios';

    protected static ?int $navigationSort = 6;

    protected ?string $subheading = 'Selecione dois portfólios salvos para comparar retorno, risco, consistência e o efeito de rodá-los juntos.';

    #[Url(as: 'a')]
    public string $firstPortfolioId = '';

    #[Url(as: 'b')]
    public string $secondPortfolioId = '';

    public string $correlationPeriod = PortfolioComparisonService::PERIOD_MONTHLY;

    /** @var array<string, mixed> */
    public array $comparison = [];

    public bool $isCompared = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if ($this->firstPortfolioId !== '' && $this->secondPortfolioId !== '') {
            $this->compare();
        }
    }

    /**
     * @return Collection<int, Portfolio>
     */
    #[Computed]
    public function availablePortfolios(): Collection
    {
        return Portfolio::query()
            ->where('user_id', auth()->id())
            ->withCount('strategies')
            ->orderBy('name')
            ->get();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.portfolio-comparison')
                ->viewData(fn (): array => [
                    'availablePortfolios' => $this->availablePortfolios,
                    'firstPortfolioId' => $this->firstPortfolioId,
                    'secondPortfolioId' => $this->secondPortfolioId,
                    'periodOptions' => app(PortfolioComparisonService::class)->periodOptions(),
                    'comparison' => $this->comparison,
                    'isCompared' => $this->isCompared,
                    'errorMessage' => $this->errorMessage,
                    'portfoliosUrl' => PortfolioResource::getUrl('index'),
                ]),
        ]);
    }

    public function updatedFirstPortfolioId(): void
    {
        $this->resetComparison();
    }

    public function updatedSecondPortfolioId(): void
    {
        $this->resetComparison();
    }

    public function updatedCorrelationPeriod(): void
    {
        if ($this->isCompared) {
            $this->compare();
        }
    }

    public function compare(): void
    {
        $this->errorMessage = null;
        $this->comparison = [];
        $this->isCompared = false;

        $first = $this->ownedPortfolio($this->firstPortfolioId);
        $second = $this->ownedPortfolio($this->secondPortfolioId);

        if ($first === null || $second === null) {
            $this->errorMessage = 'Selecione dois portfólios para comparar.';

            return;
        }

        if ($first->id === $second->id) {
            $this->errorMessage = 'Selecione dois portfólios diferentes.';

            return;
        }

        $this->comparison = app(PortfolioComparisonService::class)->compare($first, $second, [
            'correlation_period' => $this->correlationPeriod,
        ]);

        $this->isCompared = true;
    }

    public function swapPortfolios(): void
    {
        [$this->firstPortfolioId, $this->secondPortfolioId] = [$this->secondPortfolioId, $this->firstPortfolioId];

        if ($this->isCompared) {
            $this->compare();
        }
    }

    public function clearComparison(): void
    {
        $this->firstPortfolioId = '';
        $this->secondPortfolioId = '';

        $this->resetComparison();
    }

    private function ownedPortfolio(string $portfolioId): ?Portfolio
    {
        if ((int) $portfolioId <= 0) {
            return null;
        }

        return Portfolio::query()
            ->where('user_id', auth()->id())
            ->find((int) $portfolioId);
    }

    private function resetComparison(): void
    {
        $this->comparison = [];
        $this->isCompared = false;
        $this->errorMessage = null;
    }
}

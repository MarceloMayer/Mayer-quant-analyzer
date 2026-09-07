<?php

namespace App\Services\Portfolio;

class PortfolioCombinationGeneratorService
{
    /**
     * Generates all valid portfolio combinations with exactly strategiesPerPortfolio strategies each.
     * Returns sorted inner arrays to guarantee unique order for hash comparison.
     *
     * @param  int[]  $strategyIds
     * @return array<int, int[]>
     */
    public function generate(array $strategyIds, int $strategiesPerPortfolio): array
    {
        $strategyIds = array_unique($strategyIds);
        sort($strategyIds);
        $n = count($strategyIds);

        if ($strategiesPerPortfolio > $n) {
            return [];
        }

        return $this->buildCombinations($strategyIds, $strategiesPerPortfolio);
    }

    /**
     * Counts total combinations without generating them: C(n, strategiesPerPortfolio).
     */
    public function countCombinations(int $n, int $strategiesPerPortfolio): int
    {
        if ($n < 2 || $strategiesPerPortfolio > $n) {
            return 0;
        }

        return $this->binomialCoefficient($n, $strategiesPerPortfolio);
    }

    /**
     * Generates a stable hash for a set of strategy IDs for deduplication.
     *
     * @param  int[]  $strategyIds
     */
    public function combinationHash(array $strategyIds): string
    {
        $sorted = $strategyIds;
        sort($sorted);

        return sha1(implode('-', $sorted));
    }

    /**
     * @param  int[]  $items
     * @return array<int, int[]>
     */
    private function buildCombinations(array $items, int $k): array
    {
        if ($k === 0) {
            return [[]];
        }

        if (empty($items)) {
            return [];
        }

        $first = array_shift($items);
        $withFirst = array_map(
            fn (array $c): array => array_merge([$first], $c),
            $this->buildCombinations($items, $k - 1),
        );

        return array_merge($withFirst, $this->buildCombinations($items, $k));
    }

    private function binomialCoefficient(int $n, int $k): int
    {
        if ($k > $n || $k < 0) {
            return 0;
        }

        if ($k === 0 || $k === $n) {
            return 1;
        }

        $k = min($k, $n - $k);
        $result = 1.0;

        for ($i = 0; $i < $k; $i++) {
            $result = $result * ($n - $i) / ($i + 1);
        }

        return (int) round($result);
    }
}

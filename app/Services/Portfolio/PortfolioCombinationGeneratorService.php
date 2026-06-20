<?php

namespace App\Services\Portfolio;

class PortfolioCombinationGeneratorService
{
    /**
     * Generates all valid portfolio combinations of size 2..maxStrategies.
     * Returns sorted inner arrays to guarantee unique order for hash comparison.
     *
     * @param  int[]  $strategyIds
     * @return array<int, int[]>
     */
    public function generate(array $strategyIds, int $maxStrategies): array
    {
        $strategyIds = array_unique($strategyIds);
        sort($strategyIds);
        $n = count($strategyIds);
        $combinations = [];

        $upperBound = min($maxStrategies, $n);

        for ($k = 2; $k <= $upperBound; $k++) {
            foreach ($this->buildCombinations($strategyIds, $k) as $combination) {
                $combinations[] = $combination;
            }
        }

        return $combinations;
    }

    /**
     * Counts total combinations without generating them.
     * Sum of C(n, k) for k from 2 to min(maxStrategies, n).
     */
    public function countCombinations(int $n, int $maxStrategies): int
    {
        if ($n < 2) {
            return 0;
        }

        $total = 0;
        $upperBound = min($maxStrategies, $n);

        for ($k = 2; $k <= $upperBound; $k++) {
            $total += $this->binomialCoefficient($n, $k);
        }

        return $total;
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

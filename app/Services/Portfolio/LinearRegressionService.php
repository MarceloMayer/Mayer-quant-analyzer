<?php

namespace App\Services\Portfolio;

class LinearRegressionService
{
    /**
     * Calculates R² (coefficient of determination) for a series of y-values
     * against a linear regression line where x = sequential index (0, 1, 2, ...).
     * Returns a value between 0 (erratic) and 1 (perfectly linear/smooth).
     *
     * @param  float[]  $values
     */
    public function calculateR2(array $values): float
    {
        $n = count($values);

        if ($n < 2) {
            return 0.0;
        }

        $xMean = ($n - 1) / 2;
        $yMean = array_sum($values) / $n;

        $ssXX = 0.0;
        $ssXY = 0.0;
        $ssYY = 0.0;

        foreach ($values as $i => $y) {
            $xDiff = $i - $xMean;
            $yDiff = (float) $y - $yMean;

            $ssXX += $xDiff ** 2;
            $ssXY += $xDiff * $yDiff;
            $ssYY += $yDiff ** 2;
        }

        if ($ssXX <= 0 || $ssYY <= 0) {
            return 0.0;
        }

        $r = $ssXY / sqrt($ssXX * $ssYY);

        return round(min(1.0, max(0.0, $r ** 2)), 4);
    }
}

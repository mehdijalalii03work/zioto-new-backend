<?php

namespace App\Services\Pricing;

use App\Models\User;

/**
 * Role × time-period labor coefficient matrix.
 *
 * Port of WordPress Zioto_Pricing_Labor_Calculator.
 */
class LaborCalculator
{
    public function getRoles(): array
    {
        return PricingSettings::laborRoles();
    }

    public function getTimePeriods(): array
    {
        return PricingSettings::timePeriods();
    }

    public function getCurrentTimePeriod(): string
    {
        $currentHour = (int) now()->format('G');

        foreach ($this->getTimePeriods() as $period) {
            if ($this->isTimeInPeriod($currentHour, $period['start'] ?? '00:00', $period['end'] ?? '23:59')) {
                return $period['slug'];
            }
        }

        return 'daily';
    }

    /**
     * Get user's labor role slug. Guests and null users → basic.
     */
    public function getUserLaborRole(?int $userId = null): string
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return 'basic';
        }

        $user = User::find($userId);

        if (! $user) {
            return 'basic';
        }

        return $user->labor_role ?: 'basic';
    }

    /**
     * Full coefficient matrix for a product (period × role), default 1.0.
     *
     * @return array<string, array<string, float>>
     */
    public function getProductCoefficients(object $product): array
    {
        $coefficients = $product->labor_coefficients ?? null;

        if (! is_array($coefficients) || $coefficients === []) {
            return $this->defaultCoefficients();
        }

        return $coefficients;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function defaultCoefficients(): array
    {
        $matrix = [];

        foreach ($this->getTimePeriods() as $period) {
            $slug = $period['slug'];
            $matrix[$slug] = [];

            foreach ($this->getRoles() as $role) {
                $matrix[$slug][$role['slug']] = 1.0;
            }
        }

        return $matrix;
    }

    public function coefficientFor(object $product, string $period, string $role): float
    {
        $coefficients = $this->getProductCoefficients($product);

        $value = $coefficients[$period][$role] ?? 0;
        $value = (float) $value;

        return $value < 0 ? 0 : $value;
    }

    private function isTimeInPeriod(int $hour, string $start, string $end): bool
    {
        $startHour = (int) substr($start, 0, 2);
        $endHour = (int) substr($end, 0, 2);

        if ($startHour > $endHour) {
            return $hour >= $startHour || $hour < $endHour;
        }

        return $hour >= $startHour && $hour < $endHour;
    }
}

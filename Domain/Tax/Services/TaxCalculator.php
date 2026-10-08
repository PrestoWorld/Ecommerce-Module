<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Tax\Services;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Models\TaxRate;

/**
 * Tax Calculator - Calculates taxes for orders/invoices
 */
final class TaxCalculator
{
    /** @var TaxRate[] */
    private array $taxRates = [];

    public function __construct(
        private string $businessId,
    ) {}

    public function addTaxRate(TaxRate $rate): self
    {
        if ($rate->businessId !== $this->businessId) {
            throw new \InvalidArgumentException('Tax rate belongs to different business');
        }
        $this->taxRates[] = $rate;
        // Sort by priority
        usort($this->taxRates, fn ($a, $b) => $a->priority <=> $b->priority);
        return $this;
    }

    public function setTaxRates(array $rates): self
    {
        $this->taxRates = $rates;
        usort($this->taxRates, fn ($a, $b) => $a->priority <=> $b->priority);
        return $this;
    }

    /**
     * Calculate tax for a line item
     * 
     * @return array{tax_amount: Money, tax_breakdown: array<string, Money>}
     */
    public function calculateForItem(
        Money $unitPrice,
        int $quantity,
        string $productCategory,
        int $timestamp = 0
    ): array {
        $timestamp = $timestamp ?: time();
        $lineTotal = $unitPrice->multiply($quantity);
        $taxBreakdown = [];
        $totalTax = Money::fromVnd(0, $lineTotal->currency);

        foreach ($this->taxRates as $rate) {
            if (!$rate->isValidAt($timestamp)) continue;
            if (!$rate->isApplicableToCategory($productCategory)) continue;

            $taxAmount = $rate->calculate($lineTotal);
            $taxBreakdown[$rate->code] = $taxAmount;

            if ($rate->isCompound) {
                // Compound tax applies on subtotal + previous taxes
                $lineTotal = $lineTotal->add($taxAmount);
            }

            $totalTax = $totalTax->add($taxAmount);
        }

        return [
            'tax_amount' => $totalTax,
            'tax_breakdown' => $taxBreakdown,
        ];
    }

    /**
     * Calculate tax for multiple items (order/cart)
     * 
     * @param array<array{unit_price: Money, quantity: int, category: string}> $items
     * @return array{total_tax: Money, breakdown: array<string, Money>, items: array<int, array{tax_amount: Money, tax_breakdown: array<string, Money>}>}
     */
    public function calculateForOrder(array $items, int $timestamp = 0): array
    {
        $totalTax = Money::fromVnd(0);
        $breakdown = [];
        $itemTaxes = [];

        foreach ($items as $index => $item) {
            $result = $this->calculateForItem(
                $item['unit_price'],
                $item['quantity'],
                $item['category'],
                $timestamp
            );
            $itemTaxes[$index] = $result;
            $totalTax = $totalTax->add($result['tax_amount']);

            foreach ($result['tax_breakdown'] as $code => $amount) {
                if (!isset($breakdown[$code])) {
                    $breakdown[$code] = Money::fromVnd(0, $amount->currency);
                }
                $breakdown[$code] = $breakdown[$code]->add($amount);
            }
        }

        return [
            'total_tax' => $totalTax,
            'breakdown' => $breakdown,
            'items' => $itemTaxes,
        ];
    }
}
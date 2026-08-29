<?php

namespace App\Reports\Payables;

use Illuminate\Support\Collection;

final readonly class SupplierPayablesAgingReport
{
    public function __construct(
        public mixed $asOf,
        public float $totalOutstanding,
        public array $buckets,
        public Collection $suppliers,
        public Collection $invoices,
    ) {
    }

    public function totalOutstanding(): float
    {
        return $this->totalOutstanding;
    }

    public function current(): float
    {
        return $this->buckets['current'] ?? 0.0;
    }

    public function overdue(): float
    {
        return round(
            ($this->buckets['1_30'] ?? 0.0)
            + ($this->buckets['31_60'] ?? 0.0)
            + ($this->buckets['61_90'] ?? 0.0)
            + ($this->buckets['91_plus'] ?? 0.0),
            2,
        );
    }

    public function bucket(string $name): float
    {
        return $this->buckets[$name] ?? 0.0;
    }
}

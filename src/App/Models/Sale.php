<?php
namespace App\Models;

class Sale {
    public function __construct(
        private int $id,
        private float $total,
        private string $status,
        private string $paymentMethod,
        private int $cashierId
    ) {}

    public function getId(): int { return $this->id; }
    public function getTotal(): float { return $this->total; }
    public function getStatus(): string { return $this->status; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function getCashierId(): int { return $this->cashierId; }
}

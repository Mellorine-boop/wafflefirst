<?php
namespace App\Models;

class Payment {
    public function __construct(
        private int $id,
        private int $saleId,
        private string $method,
        private float $amount,
        private string $reference
    ) {}

    public function getId(): int { return $this->id; }
    public function getSaleId(): int { return $this->saleId; }
    public function getMethod(): string { return $this->method; }
    public function getAmount(): float { return $this->amount; }
    public function getReference(): string { return $this->reference; }
}

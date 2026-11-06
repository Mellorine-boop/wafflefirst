<?php
namespace App\Payments;

abstract class AbstractPayment implements PaymentMethod {
    public function __construct(protected float $amount) {}

    public function reference(): string {
        return 'WS-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public function amount(): float { return $this->amount; }

    abstract public function label(): string;
    abstract public function instructions(): string;
}

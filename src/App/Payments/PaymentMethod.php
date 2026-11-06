<?php
namespace App\Payments;

interface PaymentMethod {
    public function label(): string;
    public function instructions(): string;
    public function reference(): string;
    public function amount(): float;
}

<?php
namespace App\Payments;

class CardPayment extends AbstractPayment {
    public function label(): string { return 'Card'; }
    public function instructions(): string { return 'Confirm the card terminal approved the payment.'; }
}

<?php
namespace App\Payments;

class CashPayment extends AbstractPayment {
    public function label(): string { return 'Cash'; }
    public function instructions(): string { return 'Collect cash from the customer and record the transaction.'; }
}

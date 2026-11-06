<?php
namespace App\Payments;

class OnlinePayment extends AbstractPayment {
    public function label(): string { return 'Online Payment/Bank transfer'; }
    
    public function instructions(): string { return 'Confirm the online payment or bank transfer before completing the sale.'; }
}

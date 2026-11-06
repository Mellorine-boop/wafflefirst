<?php

namespace App\Services;

// Import PDO for transaction and database operations.
use PDO;
// Import Throwable so database failures can be caught and converted into safe messages.
use Throwable;
// Import RuntimeException for validation failures.
use RuntimeException;
// Import the product repository used to retrieve real menu products.
use App\Repositories\ProductRepository;
// Import the payment interface used for polymorphic payments.
use App\Payments\PaymentMethod;
// Import the concrete cash payment implementation.
use App\Payments\CashPayment;
// Import the concrete online payment implementation for online payment/bank transfer.
use App\Payments\OnlinePayment;
// Import the concrete card payment implementation.
use App\Payments\CardPayment;

// Define the service that controls checkout and protected void operations.
class OrderService {
    // Store the database connection and product repository.
    public function __construct(private PDO $db, private ProductRepository $products) {}

    // Place one sale and its one payment inside one database transaction.
    public function checkout(array $cartItems, string $method, int $cashierId, array $discount = []): array {
        // Reject checkout when the cart is empty.
        if (!$cartItems) throw new RuntimeException('The cart is empty.');
        // Reject checkout when no cashier is authenticated.
        if ($cashierId <= 0) throw new RuntimeException('No cashier is logged in.');

        // Create the validated sale-line collection.
        $lines = [];
        // Start the server-side subtotal at zero.
        $subtotal = 0.0;

        // Process every product and quantity submitted by the cashier.
        foreach ($cartItems as $productId => $qty) {
            // Retrieve the current product from the repository.
            $product = $this->products->find((int)$productId);
            // Stop checkout if a selected product no longer exists or is unavailable.
            if (!$product) throw new RuntimeException('One selected product is no longer available.');
            // Convert the submitted quantity to an integer.
            $qty = (int)$qty;
            // Ignore products whose quantity is zero so unselected products are not added to the sale.
            if ($qty <= 0) continue;
            // Keep every selected product quantity within the POS limit.
            $qty = min(20, $qty);
            // Calculate the line subtotal from the actual product price.
            $lineSubtotal = $product->getPrice() * $qty;
            // Add the line subtotal to the server-side subtotal.
            $subtotal += $lineSubtotal;
            // Store the validated product line for the database transaction.
            $lines[] = ['product' => $product, 'quantity' => $qty, 'subtotal' => $lineSubtotal];
        }

        // Apply exactly one allowed discount.
        $discountType = $discount['type'] ?? 'None';
        // Start the discount amount at zero.
        $discountAmount = 0.0;
        // Start the optional discount reference as null.
        $discountReference = null;

        // Apply the five-percent Senior Citizen/PWD discount to the subtotal.
        if ($discountType === 'Senior/PWD') {
            // Calculate the discount and round it to two decimal places.
            $discountAmount = round($subtotal * 0.05, 2);
        // Apply the membership discount when Membership is selected.
        } elseif ($discountType === 'Membership') {
            // Read and trim the membership card number.
            $discountReference = trim($discount['reference'] ?? '');
            // Read the one product selected for the membership discount.
            $discountProductId = (int)($discount['product_id'] ?? 0);
            // Require a membership card number.
            if ($discountReference === '') throw new RuntimeException('Enter the membership card number.');
            // Require one item to receive the membership discount.
            if ($discountProductId <= 0) throw new RuntimeException('Select the one item that receives the membership discount.');

            // Check whether this membership card already received its daily discount.
            $used = $this->db->prepare("SELECT COUNT(*) FROM sales WHERE discount_type='Membership' AND discount_reference=? AND status='Completed' AND DATE(sale_date)=CURRENT_DATE()");
            // Bind the membership card number to the lookup.
            $used->execute([$discountReference]);
            // Stop the transaction if the card already used its discount today.
            if ((int)$used->fetchColumn() > 0) throw new RuntimeException('This membership card has already used its discount today.');

            // Find the selected product in the validated cart lines.
            foreach ($lines as $line) {
                // Check whether this is the selected membership-discount item.
                if ($line['product']->getId() === $discountProductId) {
                    // Give fifty percent off exactly one unit of that item.
                    $discountAmount = round($line['product']->getPrice() * 0.50, 2);
                    // Stop after finding the selected item.
                    break;
                }
            }
            // Reject the discount if the selected product is not in the cart.
            if ($discountAmount <= 0) throw new RuntimeException('The selected membership discount item is not in the cart.');
        // Reject any discount value outside the allowed choices.
        } elseif ($discountType !== 'None') {
            // Tell the cashier that the submitted discount type is unsupported.
            throw new RuntimeException('Unsupported discount type.');
        }

        // Calculate the final amount from the server-side subtotal and discount.
        $total = max(0, round($subtotal - $discountAmount, 2));
        // Create the selected polymorphic payment object.
        $payment = $this->makePayment($method, $total);

        // Start a database transaction so the sale and payment are saved together.
        try {
            // Begin the atomic database transaction.
            $this->db->beginTransaction();

            // Create the sale header and store the discount for audit purposes.
            $sale = $this->db->prepare("INSERT INTO sales(total_amount, status, cashier_id, discount_type, discount_amount, discount_reference) VALUES(?, 'Completed', ?, ?, ?, ?)");
            // Save the final total, cashier, discount type, discount amount, and reference.
            $sale->execute([$total, $cashierId, $discountType, $discountAmount, $discountReference]);
            // Capture the newly created sale ID.
            $saleId = (int)$this->db->lastInsertId();
            // Record the original completed event so the master audit ledger keeps the completed order even if it is voided later.
            $this->db->prepare("INSERT INTO sale_audit_log(sale_id,event_status,event_time) VALUES(?, 'Completed', NOW())")->execute([$saleId]);

            // Prepare the sale-item insert statement.
            $item = $this->db->prepare('INSERT INTO sale_items(sale_id, product_id, quantity, unit_price) VALUES(?,?,?,?)');
            // Save every product line using the current price as the historical unit price.
            foreach ($lines as $line) {
                // Insert one sale-item row for the current product line.
                $item->execute([$saleId, $line['product']->getId(), $line['quantity'], $line['product']->getPrice()]);
            }

            // Prepare the one-payment insert statement.
            $pay = $this->db->prepare('INSERT INTO payments(sale_id, method, amount, reference) VALUES(?,?,?,?)');
            // Save exactly one payment record for this sale.
            $pay->execute([$saleId, $payment->label(), $payment->amount(), $payment->reference()]);

            // Commit the completed sale and payment together.
            $this->db->commit();

            // Return the values used by the cashier confirmation modal.
            return [
                // Return the generated receipt number.
                'sale_id' => $saleId,
                // Return the exact server-calculated subtotal.
                'subtotal' => $subtotal,
                // Return the exact server-calculated discount.
                'discount' => $discountAmount,
                // Return the exact server-calculated final total.
                'total' => $total,
                // Return the payment object for its label/reference.
                'payment' => $payment,
                // Return the selected discount type for audit display.
                'discount_type' => $discountType,
            ];
        // Catch all database or application errors raised during the transaction.
        } catch (Throwable $e) {
            // Roll back any partially saved data.
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Re-throw known validation errors without hiding their message.
            if ($e instanceof RuntimeException) throw $e;
            // Convert unexpected database errors into a safe checkout message.
            throw new RuntimeException('Checkout failed. The sale was not saved.');
        }
    }

    // Void a completed sale only after a verified owner/admin authorization.
    public function voidSale(int $saleId, int $cashierId, int $authorizedBy, string $reason): void {
        // Reject invalid sale IDs.
        if ($saleId <= 0) throw new RuntimeException('Invalid sale ID.');
        // Require a privileged owner/admin authorization ID.
        if ($authorizedBy <= 0) throw new RuntimeException('Owner/admin authorization is required to void a sale.');
        // Require an authenticated cashier to initiate the request.
        if ($cashierId <= 0) throw new RuntimeException('No cashier is logged in.');
        // Trim the reason before validation.
        $reason = trim($reason);
        // Reject a missing void reason.
        if ($reason === '') throw new RuntimeException('A void reason is required.');

        // Start the protected void transaction.
        try {
            // Begin the database transaction.
            $this->db->beginTransaction();
            // Lock the selected sale while checking its status.
            $check = $this->db->prepare("SELECT status FROM sales WHERE sale_id=? FOR UPDATE");
            // Execute the locked sale lookup.
            $check->execute([$saleId]);
            // Allow only currently completed sales to be voided.
            if ($check->fetchColumn() !== 'Completed') throw new RuntimeException('Only completed sales can be voided.');
            // Record the privileged user who approved the void and the selected reason on the original sale.
            $this->db->prepare("UPDATE sales SET status='Voided', voided_by=?, voided_at=NOW(), void_reason=? WHERE sale_id=?")->execute([$authorizedBy, $reason, $saleId]);
            // Add a separate void event while preserving the original completed audit event.
            $this->db->prepare("INSERT INTO sale_audit_log(sale_id,event_status,event_time,voided_at,void_reason) VALUES(?, 'Voided', NOW(), NOW(), ?)")->execute([$saleId, $reason]);
            // Commit the protected void.
            $this->db->commit();
        // Catch errors and keep the void atomic.
        } catch (Throwable $e) {
            // Roll back any partial void changes.
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Re-throw known validation errors.
            if ($e instanceof RuntimeException) throw $e;
            // Convert unexpected database errors into a safe void message.
            throw new RuntimeException('Sale could not be voided.');
        }
    }

    // Factory method demonstrates polymorphism through the payment interface.
    private function makePayment(string $method, float $amount): PaymentMethod {
        // Select the correct concrete payment class from the requested method.
        return match (strtolower($method)) {
            // Create a cash payment object when Cash is selected.
            'cash' => new CashPayment($amount),
            // Create a GCash payment object when GCash is selected.
            'online payment/bank transfer' => new OnlinePayment($amount),
            'online' => new OnlinePayment($amount),
            'bank transfer' => new OnlinePayment($amount),
            'gcash' => new OnlinePayment($amount),
            // Create a card payment object when Card is selected.
            'card' => new CardPayment($amount),
            // Reject unsupported payment methods.
            default => throw new RuntimeException('Unsupported payment method.'),
        };
    }
}

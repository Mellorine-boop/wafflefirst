<?php
// Cart.php - Encapsulated shopping cart used by the cashier terminal.
namespace App\Services;

use App\Repositories\ProductRepository;
use RuntimeException;

class Cart {
    private array $items = [];

    public function __construct(array $items = []) {
        foreach ($items as $id => $qty) {
            if ((int)$id > 0 && (int)$qty > 0) {
                $this->items[(int)$id] = min(20, (int)$qty);
            }
        }
    }

    // Adds one unit while keeping the quantity within the POS limit.
    public function add(int $id, int $qty = 1): void {
        if ($id < 1 || $qty < 1) throw new RuntimeException('Invalid product quantity.');
        $this->items[$id] = min(20, ($this->items[$id] ?? 0) + $qty);
    }

    // Updates or removes a cart line.
    public function update(int $id, int $qty): void {
        if ($qty <= 0) unset($this->items[$id]);
        else $this->items[$id] = min(20, $qty);
    }

    public function remove(int $id): void { unset($this->items[$id]); }
    public function clear(): void { $this->items = []; }
    public function items(): array { return $this->items; }
    public function count(): int { return array_sum($this->items); }

    // Converts cart IDs into real MenuItem objects and calculates line totals.
    public function lines(ProductRepository $repo): array {
        $lines = [];
        foreach ($this->items as $id => $qty) {
            $product = $repo->find((int)$id);
            if ($product) {
                $lines[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'subtotal' => $product->getPrice() * $qty,
                ];
            }
        }
        return $lines;
    }

    public function total(ProductRepository $repo): float {
        return array_sum(array_map(fn($line) => $line['subtotal'], $this->lines($repo)));
    }
}

<?php
namespace App\Repositories;

use PDO;
use App\Models\MenuItem;
use App\Models\Waffle;
use App\Models\Drink;
use App\DSA\HashTable;
use App\DSA\Algorithms;

class ProductRepository {
    private HashTable $cache;

    public function __construct(private PDO $db) {
        $this->cache = new HashTable(17);
    }

    public function all(): array {
        $rows = $this->db->query(
            'SELECT product_id, name, category, price, description FROM products WHERE is_available = 1'
        )->fetchAll();

        $items = array_map(fn($row) => $this->make($row), $rows);

        return Algorithms::insertionSort($items, fn(MenuItem $a, MenuItem $b) => strcmp($a->getName(), $b->getName()));
    }

    public function find(int $id): ?MenuItem {
        $cached = $this->cache->get((string)$id);
        if ($cached instanceof MenuItem) return $cached;

        $stmt = $this->db->prepare(
            'SELECT product_id, name, category, price, description FROM products WHERE product_id = ? AND is_available = 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $product = $this->make($row);
        $this->cache->put((string)$id, $product);
        return $product;
    }

    public function findByName(string $name): ?MenuItem {
        $items = $this->all();
        return Algorithms::binarySearch($items, trim($name), fn(MenuItem $p, string $target) => strcmp(strtolower($p->getName()), strtolower($target)));
    }

    public function findByCategory(string $category): array {
        return Algorithms::linearSearchAll($this->all(), fn(MenuItem $p) => strtolower($p->getCategory()) === strtolower($category));
    }

    private function make(array $row): MenuItem {
        if ($row['category'] === 'Waffle') {
            return new Waffle((int)$row['product_id'], $row['name'], (float)$row['price'], $row['category'], $row['description'] ?? '');
        }
        return new Drink((int)$row['product_id'], $row['name'], (float)$row['price'], $row['category'], $row['description'] ?? '');
    }
}

<?php
// data structure DSA 
namespace App\DSA;

class HashTable {
    private array $buckets;
    private int $size;

    public function __construct(int $size = 17) {
        $this->size = max(3, $size);
        $this->buckets = array_fill(0, $this->size, []);
    }

    // DSA Data Structure operation: hash a string key into a bucket index.
    private function hash(string $key): int {
        $sum = 0;
        for ($i = 0, $length = strlen($key); $i < $length; $i++) {
            $sum = ($sum * 31 + ord($key[$i])) % $this->size;
        }
        return $sum;
    }

    // Inserts or replaces a key-value pair using separate chaining.
    public function put(string $key, mixed $value): void {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as &$pair) {
            if ($pair['key'] === $key) {
                $pair['value'] = $value;
                return;
            }
        }
        $this->buckets[$index][] = ['key' => $key, 'value' => $value];
    }

    // Average-case O(1) lookup for cached products.
    public function get(string $key): mixed {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as $pair) {
            if ($pair['key'] === $key) return $pair['value'];
        }
        return null;
    }

    public function remove(string $key): void {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as $i => $pair) {
            if ($pair['key'] === $key) {
                unset($this->buckets[$index][$i]);
                $this->buckets[$index] = array_values($this->buckets[$index]);
                return;
            }
        }
    }
}

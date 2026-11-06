<?php
namespace App\Models;

class Drink extends MenuItem {
    public function __construct(int $id, string $name, float $price, string $category = 'Drink', string $description = '') {
        parent::__construct($id, $name, $price, $category, $description);
    }
    public function getItemClassificationLabel(): string {
        return 'Handcrafted Beverage';
    }
}

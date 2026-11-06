<?php
namespace App\Models;

class Waffle extends MenuItem {
    public function __construct(int $id, string $name, float $price, string $category = 'Waffle', string $description = '') {
        parent::__construct($id, $name, $price, $category, $description);
    }
    public function getItemClassificationLabel(): string {
        return 'Fresh Baked Waffle';
    }
}

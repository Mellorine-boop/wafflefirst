<?php

namespace App\Models;

use App\Core\DatabaseException;

abstract class MenuItem {
    protected int $productId;
    protected string $name;
    protected float $price;
    protected string $category;
    protected string $description;

    public function __construct(int $id, string $name, float $price, string $category = 'Drink', string $description = '') {
        if ($id < 0 || trim($name) === '' || $price <= 0 || trim($category) === '') {
            throw new DatabaseException('A product must have a valid name, ID, and positive price.');
        }
        $this->productId = $id;
        $this->name = trim($name);
        $this->price = $price;
        $this->category = trim($category);
        $this->description = trim($description);
    }

    public function getId(): int { return $this->productId; }
    public function getName(): string { return $this->name; }
    public function getPrice(): float { return $this->price; }
    public function getCategory(): string { return $this->category; }
    public function getDescription(): string { return $this->description; }

    // Setter demonstrates encapsulation and validation.
    public function setPrice(float $price): void {
        if ($price <= 0) {
            throw new DatabaseException('Product price must be greater than zero.');
        }
        $this->price = $price;
        $this->category = trim($category);
        $this->description = trim($description);
    }

    // Polymorphism: every child product supplies its own classification label.
    abstract public function getItemClassificationLabel(): string;

    // Static method demonstrates late static binding.
    public static function getSystemRoleContextIdentifier(): string {
        return static::class;
    }
}

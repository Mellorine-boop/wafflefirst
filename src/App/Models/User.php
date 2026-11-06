<?php
namespace App\Models;

class User {
    public function __construct(
        private int $id,
        private string $username,
        private string $fullName,
        private string $role
    ) {}

    public function getId(): int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getFullName(): string { return $this->fullName; }
    public function getRole(): string { return $this->role; }
}

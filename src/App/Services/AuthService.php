<?php
// AuthService.php - Authentication and role authorization service.
namespace App\Services;

use App\Repositories\UserRepository;
use RuntimeException;

class AuthService {
    public function __construct(private UserRepository $users) {}

    // Verifies the password hash stored in the database.
    public function login(string $username, string $password): bool {
        $row = $this->users->findByUsername($username);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = (int)$row['user_id'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['user_name'] = $row['full_name'];
        return true;
    }

    // Protects pages by role instead of trusting the browser form alone.
    public function requireRole(string $role): void {
        if (($_SESSION['user_role'] ?? '') !== $role) {
            header('Location: login.php');
            exit();
        }
    }

    public function logout(): void {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    public function currentUserId(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

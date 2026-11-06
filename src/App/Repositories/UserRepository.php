<?php
// UserRepository.php - SQL access for staff accounts.
namespace App\Repositories;

use PDO;
use App\Models\User;

class UserRepository {
    public function __construct(private PDO $db) {}

    // Finds an active user by username for authentication.
    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare('SELECT user_id, username, password_hash, full_name, role FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Verifies a privileged owner/admin passcode for protected POS actions.
    public function verifyPrivilegedPasscode(string $role, string $passcode): ?array {
        if (!in_array($role, ['owner', 'admin'], true) || $passcode === '') return null;
        $stmt = $this->db->prepare('SELECT user_id, username, password_hash, full_name, role FROM users WHERE role = ? AND is_active = 1');
        $stmt->execute([$role]);
        foreach ($stmt->fetchAll() as $row) {
            if (password_verify($passcode, $row['password_hash'])) return $row;
        }
        return null;
    }

    // Returns all active and inactive staff accounts so the owner can manage access.
    public function allAccounts(): array {
        $stmt = $this->db->query('SELECT user_id, username, full_name, role, is_active, created_at FROM users ORDER BY role, full_name');
        return $stmt->fetchAll();
    }

    // Creates a new staff account with a securely hashed password.
    public function createAccount(string $username, string $fullName, string $role, string $password): void {
        if (!in_array($role, ['admin', 'cashier'], true)) {
            throw new \RuntimeException('Only admin and cashier accounts can be created from this screen.');
        }
        $stmt = $this->db->prepare('INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([trim($username), password_hash($password, PASSWORD_DEFAULT), trim($fullName), $role]);
    }

    // Deactivates an account instead of deleting it so historical sales remain intact.
    public function deactivateAccount(int $userId, int $currentOwnerId): void {
        if ($userId === $currentOwnerId) {
            throw new \RuntimeException('The current owner account cannot be removed from this screen.');
        }
        $stmt = $this->db->prepare("UPDATE users SET is_active = 0 WHERE user_id = ? AND role IN ('admin', 'cashier')");
        $stmt->execute([$userId]);
    }

    // Restores a previously deactivated admin or cashier account.
    public function activateAccount(int $userId): void {
        $stmt = $this->db->prepare("UPDATE users SET is_active = 1 WHERE user_id = ? AND role IN ('admin', 'cashier')");
        $stmt->execute([$userId]);
    }

    // Transfers an admin/cashier position while keeping the same account credentials.
    public function changeRole(int $userId, string $role): void {
        if (!in_array($role, ['admin', 'cashier'], true)) {
            throw new \RuntimeException('Position must be Admin or Cashier.');
        }
        $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE user_id = ? AND role IN ('admin', 'cashier')");
        $stmt->execute([$role, $userId]);
    }

    // Converts a database row into a User domain object.
    public function makeUser(array $row): User {
        return new User((int)$row['user_id'], $row['username'], $row['full_name'], $row['role']);
    }
}

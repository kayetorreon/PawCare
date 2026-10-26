<?php
class Auth {
    private PDO $conn;
    private string $table = "USERS";

    public function __construct(PDO $db) {
        $this->conn = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Register a new user account (TRANSACTION #1).
     * Atomically creates user credentials, ensures an adopter profile exists,
     * and records an audit log in activity_logs.
     */
    public function register(string $fullName, string $email, string $phone, string $password): bool {
        $stmt = $this->conn->prepare("SELECT user_id FROM " . $this->table . " WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return false; // Email already registered
        }

        // Look up the adopter role id instead of hardcoding it
        $roleStmt = $this->conn->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) = 'adopter' LIMIT 1");
        $roleStmt->execute();
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) {
            throw new RuntimeException("The 'adopter' role is missing from the roles table.");
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            // TRANSACTION BEGIN
            $this->conn->beginTransaction();

            // 1. Insert into USERS table
            $insertStmt = $this->conn->prepare(
                "INSERT INTO " . $this->table . " (full_name, email, phone, password_hash, role_id)
                 VALUES (:full_name, :email, :phone, :hash, :role_id)"
            );
            $insertStmt->execute([
                ':full_name' => $fullName,
                ':email'     => $email,
                ':phone'     => $phone,
                ':hash'      => $hash,
                ':role_id'   => $roleId
            ]);
            $userId = (int)$this->conn->lastInsertId();

            // 2. Ensure matching record exists in ADOPTERS table
            $adopterCheck = $this->conn->prepare("SELECT adopter_id FROM adopters WHERE LOWER(email) = LOWER(:email) LIMIT 1");
            $adopterCheck->execute([':email' => $email]);
            if (!$adopterCheck->fetch()) {
                $insertAdopter = $this->conn->prepare(
                    "INSERT INTO adopters (full_name, email, phone_number, address) 
                     VALUES (:full_name, :email, :phone, 'Registered Online')"
                );
                $insertAdopter->execute([
                    ':full_name' => $fullName,
                    ':email'     => $email,
                    ':phone'     => $phone
                ]);
            }

            // 3. Insert audit log into ACTIVITY_LOGS table
            $logStmt = $this->conn->prepare(
                "INSERT INTO activity_logs (action, user_id) VALUES (:action, :user_id)"
            );
            $logStmt->execute([
                ':action'  => "New user registered: {$fullName} ({$email})",
                ':user_id' => $userId
            ]);

            // TRANSACTION COMMIT
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            // TRANSACTION ROLLBACK
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("register transaction failed: " . $e->getMessage());
            return false;
        }
    }

    public function login(string $email, string $password): bool {
        $stmt = $this->conn->prepare(
            "SELECT u.*, r.role_name
             FROM " . $this->table . " u
             JOIN roles r ON u.role_id = r.role_id
             WHERE u.email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role_name'];
            return true;
        }
        return false;
    }

    // Logout
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // Helper utilities
    public static function isLoggedIn(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    public static function getCurrentUser(): ?array {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }

    public static function getUserRole(): ?string {
        $user = self::getCurrentUser();
        return $user['role'] ?? null;
    }

    public static function isAdmin(): bool {
        $user = self::getCurrentUser();
        if (!$user || empty($user['role'])) {
            return false;
        }
        $role = strtolower(trim($user['role']));
        return in_array($role, ['admin', 'coordinator'], true);
    }

    public static function isVolunteer(): bool {
        $user = self::getCurrentUser();
        if (!$user || empty($user['role'])) {
            return false;
        }
        $role = strtolower(trim($user['role']));
        return $role === 'volunteer';
    }

    public static function isAdopter(): bool {
        $user = self::getCurrentUser();
        if (!$user || empty($user['role'])) {
            return false;
        }
        $role = strtolower(trim($user['role']));
        return in_array($role, ['adopter', 'user'], true);
    }

    public static function requireLogin(string $redirectTo = 'login.php'): void {
        if (!self::isLoggedIn()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }

    public static function requireAdmin(string $redirectTo = 'index.php?error=admin_only'): void {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit;
        }
        if (!self::isAdmin()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }
}

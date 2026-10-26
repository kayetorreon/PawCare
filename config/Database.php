<?php
/**
 * Database.php
 * PDO connection wrapper for the PawCare Pet Adoption and Foster Tracker.
 */

class Database
{
    private string $host = "127.0.0.1";
    private string $db_name = "pawcare_db";
    private string $username = "root";
    private string $password = "";
    private ?PDO $conn = null;

    /**
     * Opens (or reuses) a PDO connection with exception-based error handling.
     */
    public function getConnection(): PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Log the real error, show visitors a generic message.
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }

        return $this->conn;
    }
}
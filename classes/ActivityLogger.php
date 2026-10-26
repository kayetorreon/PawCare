<?php
/**
 * ActivityLogger.php
 * Records and queries system audit events for accountability and administrative oversight.
 */

require_once __DIR__ . '/../config/Database.php';

class ActivityLogger
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Record a system action.
     */
    public function log(string $action, int $userId): bool
    {
        $sql = "INSERT INTO activity_logs (action, user_id, timestamp) VALUES (:action, :uid, NOW())";
        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([':action' => $action, ':uid' => $userId]);
        } catch (PDOException $e) {
            error_log("ActivityLogger log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch recent activity logs with user and role details.
     */
    public function getLogs(int $limit = 100, ?string $keyword = null): array
    {
        $sql = "SELECT al.log_id, al.action, al.timestamp, al.user_id,
                       u.full_name, u.email, r.role_name
                FROM activity_logs al
                JOIN users u ON al.user_id = u.user_id
                JOIN roles r ON u.role_id = r.role_id";

        if (!empty($keyword)) {
            $sql .= " WHERE al.action LIKE :kw OR u.full_name LIKE :kw2 OR u.email LIKE :kw3";
        }

        $sql .= " ORDER BY al.log_id DESC LIMIT :limit";

        try {
            $stmt = $this->conn->prepare($sql);
            if (!empty($keyword)) {
                $kw = "%{$keyword}%";
                $stmt->bindParam(':kw', $kw, PDO::PARAM_STR);
                $stmt->bindParam(':kw2', $kw, PDO::PARAM_STR);
                $stmt->bindParam(':kw3', $kw, PDO::PARAM_STR);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("ActivityLogger getLogs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of activity logs.
     */
    public function getTotalCount(): int
    {
        try {
            return (int)$this->conn->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}


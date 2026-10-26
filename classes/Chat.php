<?php
/**
 * Chat.php
 * Handles messaging between users/volunteers and shelter administrators.
 */

require_once __DIR__ . '/../config/Database.php';

class Chat
{
    private PDO $conn;
    private string $table = "messages";

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Get the primary administrator user.
     */
    public function getAdminUser(): ?array
    {
        $sql = "SELECT u.user_id, u.full_name, u.email, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE LOWER(r.role_name) IN ('admin', 'coordinator')
                ORDER BY CASE WHEN LOWER(r.role_name) = 'admin' THEN 1 ELSE 2 END, u.user_id ASC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ?: null;
    }

    /**
     * Send a new message.
     */
    public function sendMessage(int $senderId, int $receiverId, string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        $sql = "INSERT INTO {$this->table} (sender_id, receiver_id, message, created_at)
                VALUES (:sender_id, :receiver_id, :message, NOW())";

        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':sender_id'   => $senderId,
                ':receiver_id' => $receiverId,
                ':message'     => $message,
            ]);
        } catch (PDOException $e) {
            error_log("sendMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get message history between two users and mark incoming messages as read.
     */
    public function getMessagesBetween(int $userId, int $otherId): array
    {
        // Mark messages sent to $userId from $otherId as read
        try {
            $markStmt = $this->conn->prepare(
                "UPDATE {$this->table} 
                 SET is_read = 1 
                 WHERE receiver_id = :user_id AND sender_id = :other_id AND is_read = 0"
            );
            $markStmt->execute([
                ':user_id'  => $userId,
                ':other_id' => $otherId
            ]);
        } catch (PDOException $e) {
            error_log("markAsRead error: " . $e->getMessage());
        }

        $sql = "SELECT m.message_id, m.sender_id, m.receiver_id, m.message, m.is_read, m.created_at,
                       s.full_name AS sender_name, r.full_name AS receiver_name
                FROM {$this->table} m
                JOIN users s ON m.sender_id = s.user_id
                JOIN users r ON m.receiver_id = r.user_id
                WHERE (m.sender_id = :u1 AND m.receiver_id = :o1)
                   OR (m.sender_id = :o2 AND m.receiver_id = :u2)
                ORDER BY m.created_at ASC, m.message_id ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':u1' => $userId,
                ':o1' => $otherId,
                ':o2' => $otherId,
                ':u2' => $userId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getMessagesBetween error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get conversation list for an admin (all users who sent or received messages with admin).
     */
    public function getConversationsForAdmin(int $adminId): array
    {
        $sql = "SELECT u.user_id, u.full_name, u.email, ro.role_name,
                       latest.latest_message, latest.latest_time,
                       COALESCE(unread.unread_count, 0) AS unread_count
                FROM users u
                JOIN roles ro ON u.role_id = ro.role_id
                JOIN (
                    SELECT 
                        CASE WHEN sender_id = :adminId1 THEN receiver_id ELSE sender_id END AS contact_id,
                        message AS latest_message,
                        created_at AS latest_time
                    FROM messages m1
                    WHERE message_id = (
                        SELECT MAX(message_id)
                        FROM messages m2
                        WHERE (m2.sender_id = :adminId2 AND m2.receiver_id = CASE WHEN m1.sender_id = :adminId3 THEN m1.receiver_id ELSE m1.sender_id END)
                           OR (m2.receiver_id = :adminId4 AND m2.sender_id = CASE WHEN m1.sender_id = :adminId5 THEN m1.receiver_id ELSE m1.sender_id END)
                    )
                ) latest ON u.user_id = latest.contact_id
                LEFT JOIN (
                    SELECT sender_id, COUNT(*) AS unread_count
                    FROM messages
                    WHERE receiver_id = :adminId6 AND is_read = 0
                    GROUP BY sender_id
                ) unread ON u.user_id = unread.sender_id
                WHERE u.user_id != :adminId7
                ORDER BY latest.latest_time DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':adminId1' => $adminId,
                ':adminId2' => $adminId,
                ':adminId3' => $adminId,
                ':adminId4' => $adminId,
                ':adminId5' => $adminId,
                ':adminId6' => $adminId,
                ':adminId7' => $adminId,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Simplified fallback if subquery is complex for MySQL version
            error_log("getConversationsForAdmin error: " . $e->getMessage());
            return $this->getConversationsFallback($adminId);
        }
    }

    /**
     * Resilient fallback to fetch conversations if needed.
     */
    public function getConversationsFallback(int $adminId): array
    {
        try {
            $userStmt = $this->conn->prepare(
                "SELECT DISTINCT 
                    CASE WHEN sender_id = :a1 THEN receiver_id ELSE sender_id END AS contact_id
                 FROM messages 
                 WHERE sender_id = :a2 OR receiver_id = :a3"
            );
            $userStmt->execute([':a1' => $adminId, ':a2' => $adminId, ':a3' => $adminId]);
            $contactIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);

            $conversations = [];
            foreach ($contactIds as $contactId) {
                if ((int)$contactId === $adminId) continue;

                $userQuery = $this->conn->prepare(
                    "SELECT u.user_id, u.full_name, u.email, r.role_name
                     FROM users u JOIN roles r ON u.role_id = r.role_id
                     WHERE u.user_id = :id"
                );
                $userQuery->execute([':id' => $contactId]);
                $user = $userQuery->fetch(PDO::FETCH_ASSOC);
                if (!$user) continue;

                $lastMsgQuery = $this->conn->prepare(
                    "SELECT message, created_at 
                     FROM messages 
                     WHERE (sender_id = :a1 AND receiver_id = :c1) OR (sender_id = :c2 AND receiver_id = :a2)
                     ORDER BY message_id DESC LIMIT 1"
                );
                $lastMsgQuery->execute([':a1' => $adminId, ':c1' => $contactId, ':c2' => $contactId, ':a2' => $adminId]);
                $lastMsg = $lastMsgQuery->fetch(PDO::FETCH_ASSOC);

                $unreadQuery = $this->conn->prepare(
                    "SELECT COUNT(*) FROM messages WHERE sender_id = :c AND receiver_id = :a AND is_read = 0"
                );
                $unreadQuery->execute([':c' => $contactId, ':a' => $adminId]);
                $unreadCount = (int)$unreadQuery->fetchColumn();

                $conversations[] = [
                    'user_id'        => $user['user_id'],
                    'full_name'      => $user['full_name'],
                    'email'          => $user['email'],
                    'role_name'      => $user['role_name'],
                    'latest_message' => $lastMsg['message'] ?? '',
                    'latest_time'    => $lastMsg['created_at'] ?? '',
                    'unread_count'   => $unreadCount
                ];
            }

            usort($conversations, fn($a, $b) => strcmp($b['latest_time'], $a['latest_time']));
            return $conversations;
        } catch (PDOException $e) {
            error_log("getConversationsFallback error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total unread messages count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE receiver_id = :user_id AND is_read = 0"
            );
            $stmt->execute([':user_id' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }
}


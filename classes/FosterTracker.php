<?php
/**
 * FosterTracker.php
 * Handles foster home management, assignments, and tracking which pet is with which foster parent.
 */

require_once __DIR__ . '/../config/Database.php';

class FosterTracker
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Get all foster assignments (both active and past) with pet and foster home details.
     */
    public function getAllAssignments(?string $filter = null): array
    {
        $sql = "SELECT fa.assignment_id, fa.foster_id, fa.pet_id, fa.start_date, fa.end_date,
                       p.name AS pet_name, p.breed, p.status AS pet_status, p.image_url,
                       s.species_name,
                       fh.address, fh.capacity,
                       u.full_name AS foster_parent, u.email AS foster_email, u.phone AS foster_phone
                FROM foster_assignments fa
                JOIN pets p ON fa.pet_id = p.pet_id
                JOIN species s ON p.species_id = s.species_id
                JOIN foster_homes fh ON fa.foster_id = fh.foster_id
                JOIN users u ON fh.user_id = u.user_id";

        if ($filter === 'active') {
            $sql .= " WHERE fa.end_date IS NULL";
        } elseif ($filter === 'past') {
            $sql .= " WHERE fa.end_date IS NOT NULL";
        }

        $sql .= " ORDER BY fa.start_date DESC, fa.assignment_id DESC";

        try {
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("getAllAssignments error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all registered foster homes with their current occupancy and capacity.
     */
    public function getFosterHomes(): array
    {
        $sql = "SELECT fh.foster_id, fh.address, fh.capacity, fh.user_id,
                       u.full_name, u.email, u.phone,
                       (SELECT COUNT(*) FROM foster_assignments fa 
                        WHERE fa.foster_id = fh.foster_id AND fa.end_date IS NULL) AS current_occupancy
                FROM foster_homes fh
                JOIN users u ON fh.user_id = u.user_id
                ORDER BY u.full_name ASC";

        try {
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("getFosterHomes error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Assign a pet to a foster home (TRANSACTION).
     * Atomically creates the assignment, updates pet status to 'Fostered', and writes an activity log.
     */
    public function assignPet(int $petId, int $fosterId, string $startDate, ?int $adminUserId = null): bool
    {
        try {
            $this->conn->beginTransaction();

            // 1. Verify pet exists
            $pStmt = $this->conn->prepare("SELECT name FROM pets WHERE pet_id = :pid LIMIT 1");
            $pStmt->execute([':pid' => $petId]);
            $petName = $pStmt->fetchColumn();
            if (!$petName) {
                $this->conn->rollBack();
                return false;
            }

            // 2. Insert into foster_assignments
            $assignStmt = $this->conn->prepare(
                "INSERT INTO foster_assignments (foster_id, pet_id, start_date) 
                 VALUES (:fid, :pid, :start_date)"
            );
            $assignStmt->execute([
                ':fid'        => $fosterId,
                ':pid'        => $petId,
                ':start_date' => $startDate ?: date('Y-m-d')
            ]);
            $assignId = (int)$this->conn->lastInsertId();

            // 3. Update pet status to 'Fostered'
            $upPet = $this->conn->prepare("UPDATE pets SET status = 'Fostered' WHERE pet_id = :pid");
            $upPet->execute([':pid' => $petId]);

            // 4. Log action
            if ($adminUserId !== null && $adminUserId > 0) {
                $logStmt = $this->conn->prepare("INSERT INTO activity_logs (action, user_id) VALUES (:action, :uid)");
                $logStmt->execute([
                    ':action' => "Assigned pet #{$petId} ({$petName}) to Foster Home #{$fosterId}",
                    ':uid'    => $adminUserId
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("assignPet transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * End a foster assignment (TRANSACTION).
     * Atomically sets the end_date, updates pet status back to 'Available', and writes an activity log.
     */
    public function endAssignment(int $assignmentId, string $endDate, string $newPetStatus = 'Available', ?int $adminUserId = null): bool
    {
        try {
            $this->conn->beginTransaction();

            // 1. Fetch assignment details
            $stmt = $this->conn->prepare(
                "SELECT fa.pet_id, p.name AS pet_name 
                 FROM foster_assignments fa 
                 JOIN pets p ON fa.pet_id = p.pet_id 
                 WHERE fa.assignment_id = :aid LIMIT 1"
            );
            $stmt->execute([':aid' => $assignmentId]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$info) {
                $this->conn->rollBack();
                return false;
            }
            $petId = (int)$info['pet_id'];
            $petName = $info['pet_name'];

            // 2. Set end_date on assignment
            $upAssign = $this->conn->prepare("UPDATE foster_assignments SET end_date = :end_date WHERE assignment_id = :aid");
            $upAssign->execute([
                ':end_date' => $endDate ?: date('Y-m-d'),
                ':aid'      => $assignmentId
            ]);

            // 3. Update pet status
            $upPet = $this->conn->prepare("UPDATE pets SET status = :status WHERE pet_id = :pid");
            $upPet->execute([
                ':status' => $newPetStatus,
                ':pid'    => $petId
            ]);

            // 4. Log action
            if ($adminUserId !== null && $adminUserId > 0) {
                $logStmt = $this->conn->prepare("INSERT INTO activity_logs (action, user_id) VALUES (:action, :uid)");
                $logStmt->execute([
                    ':action' => "Ended foster assignment #{$assignmentId} for pet {$petName} (Status: {$newPetStatus})",
                    ':uid'    => $adminUserId
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("endAssignment transaction failed: " . $e->getMessage());
            return false;
        }
    }
}


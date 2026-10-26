<?php
/**
 * ReportGenerator.php
 * Generates adoption and foster statistics, capacity analytics, and tracks generated reports.
 */

require_once __DIR__ . '/../config/Database.php';

class ReportGenerator
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Compute comprehensive aggregate statistics across pets, adoptions, foster homes, and medical.
     */
    public function getAnalytics(): array
    {
        try {
            // Pet counts by status
            $totalPets = (int)$this->conn->query("SELECT COUNT(*) FROM pets")->fetchColumn();
            $available = (int)$this->conn->query("SELECT COUNT(*) FROM pets WHERE LOWER(status) = 'available'")->fetchColumn();
            $fostered = (int)$this->conn->query("SELECT COUNT(*) FROM pets WHERE LOWER(status) = 'fostered'")->fetchColumn();
            $adopted = (int)$this->conn->query("SELECT COUNT(*) FROM pets WHERE LOWER(status) = 'adopted'")->fetchColumn();

            // Species breakdown
            $speciesStmt = $this->conn->query(
                "SELECT s.species_name, COUNT(p.pet_id) AS total_count,
                        SUM(CASE WHEN LOWER(p.status) = 'adopted' THEN 1 ELSE 0 END) AS adopted_count,
                        SUM(CASE WHEN LOWER(p.status) = 'fostered' THEN 1 ELSE 0 END) AS fostered_count,
                        SUM(CASE WHEN LOWER(p.status) = 'available' THEN 1 ELSE 0 END) AS available_count
                 FROM species s
                 LEFT JOIN pets p ON s.species_id = p.species_id
                 GROUP BY s.species_id, s.species_name"
            );
            $speciesBreakdown = $speciesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Adoption application statistics
            $appTotal = (int)$this->conn->query("SELECT COUNT(*) FROM adoption_applications")->fetchColumn();
            $appApproved = (int)$this->conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status = 'Approved'")->fetchColumn();
            $appPending = (int)$this->conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status IN ('Pending', 'In Review')")->fetchColumn();
            $appCancelled = (int)$this->conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status IN ('Rejected', 'Cancelled')")->fetchColumn();

            // Foster statistics
            $fosterHomesCount = (int)$this->conn->query("SELECT COUNT(*) FROM foster_homes")->fetchColumn();
            $totalCapacity = (int)$this->conn->query("SELECT COALESCE(SUM(capacity), 0) FROM foster_homes")->fetchColumn();
            $activeAssignments = (int)$this->conn->query("SELECT COUNT(*) FROM foster_assignments WHERE end_date IS NULL")->fetchColumn();
            $completedAssignments = (int)$this->conn->query("SELECT COUNT(*) FROM foster_assignments WHERE end_date IS NOT NULL")->fetchColumn();
            $occupancyRate = $totalCapacity > 0 ? round(($activeAssignments / $totalCapacity) * 100, 1) : 0;

            // Medical records statistics
            $medicalTotal = (int)$this->conn->query("SELECT COUNT(*) FROM medical_records")->fetchColumn();
            $vaccineCount = (int)$this->conn->query("SELECT COUNT(*) FROM medical_records WHERE LOWER(treatment_type) = 'vaccination'")->fetchColumn();
            $dewormCount = (int)$this->conn->query("SELECT COUNT(*) FROM medical_records WHERE LOWER(treatment_type) = 'deworming'")->fetchColumn();
            $checkupCount = (int)$this->conn->query("SELECT COUNT(*) FROM medical_records WHERE LOWER(treatment_type) = 'checkup'")->fetchColumn();

            return [
                'pets' => [
                    'total'     => $totalPets,
                    'available' => $available,
                    'fostered'  => $fostered,
                    'adopted'   => $adopted,
                ],
                'species' => $speciesBreakdown,
                'applications' => [
                    'total'     => $appTotal,
                    'approved'  => $appApproved,
                    'pending'   => $appPending,
                    'cancelled' => $appCancelled,
                    'success_rate' => $appTotal > 0 ? round(($appApproved / $appTotal) * 100, 1) : 0
                ],
                'foster' => [
                    'homes_count' => $fosterHomesCount,
                    'total_capacity' => $totalCapacity,
                    'active_placements' => $activeAssignments,
                    'completed_placements' => $completedAssignments,
                    'occupancy_rate' => $occupancyRate
                ],
                'medical' => [
                    'total'       => $medicalTotal,
                    'vaccines'    => $vaccineCount,
                    'deworming'   => $dewormCount,
                    'checkups'    => $checkupCount
                ]
            ];
        } catch (PDOException $e) {
            error_log("getAnalytics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save/log a generated report into the reports table (TRANSACTION).
     */
    public function recordReport(string $reportType, int $userId): bool
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "INSERT INTO reports (generated_by, report_type, date_created) 
                 VALUES (:uid, :type, NOW())"
            );
            $stmt->execute([':uid' => $userId, ':type' => $reportType]);
            $reportId = (int)$this->conn->lastInsertId();

            $logStmt = $this->conn->prepare("INSERT INTO activity_logs (action, user_id) VALUES (:action, :uid)");
            $logStmt->execute([
                ':action' => "Generated and archived '{$reportType}' Report #{$reportId}",
                ':uid'    => $userId
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("recordReport failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch all past generated reports.
     */
    public function getReportHistory(): array
    {
        $sql = "SELECT r.report_id, r.report_type, r.date_created,
                       u.full_name AS generated_by_name, u.email AS generated_by_email
                FROM reports r
                JOIN users u ON r.generated_by = u.user_id
                ORDER BY r.report_id DESC";

        try {
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("getReportHistory error: " . $e->getMessage());
            return [];
        }
    }
}


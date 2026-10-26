<?php
/**
 * AdoptionQueue.php
 * Handles waiting queues and application review for PawCare pet adoptions.
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Pet.php';

class AdoptionQueue
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Get active queue count for each pet (key: pet_id => count).
     */
    public function getQueueCounts(): array
    {
        $sql = "SELECT pet_id, COUNT(*) AS in_queue 
                FROM adoption_applications 
                WHERE status IN ('Pending', 'In Review')
                GROUP BY pet_id";
        try {
            $stmt = $this->conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return $rows ?: [];
        } catch (PDOException $e) {
            error_log("getQueueCounts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all applications for a specific user by their email address,
     * including their real-time waiting queue position for each pet.
     */
    public function getUserApplications(string $userEmail): array
    {
        $sql = "SELECT a.application_id, a.pet_id, a.application_date, a.status, a.notes,
                       p.name AS pet_name, p.breed, p.status AS pet_status, p.image_url,
                       s.species_name, ad.adopter_id, ad.full_name, ad.email, ad.phone_number
                FROM adoption_applications a
                JOIN adopters ad ON a.adopter_id = ad.adopter_id
                JOIN pets p ON a.pet_id = p.pet_id
                JOIN species s ON p.species_id = s.species_id
                WHERE LOWER(ad.email) = LOWER(:email)
                ORDER BY a.application_date DESC, a.application_id DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':email' => $userEmail]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute queue position for each application
            foreach ($applications as &$app) {
                $petId = (int)$app['pet_id'];
                $appId = (int)$app['application_id'];

                if (in_array($app['status'], ['Pending', 'In Review'])) {
                    // Count how many applicants ahead of this one
                    $posStmt = $this->conn->prepare(
                        "SELECT COUNT(*) 
                         FROM adoption_applications 
                         WHERE pet_id = :pet_id 
                           AND status IN ('Pending', 'In Review')
                           AND (application_date < :app_date OR (application_date = :app_date2 AND application_id <= :app_id))"
                    );
                    $posStmt->execute([
                        ':pet_id'    => $petId,
                        ':app_date'  => $app['application_date'],
                        ':app_date2' => $app['application_date'],
                        ':app_id'    => $appId
                    ]);
                    $app['queue_position'] = (int)$posStmt->fetchColumn();

                    $totalStmt = $this->conn->prepare(
                        "SELECT COUNT(*) FROM adoption_applications WHERE pet_id = :pet_id AND status IN ('Pending', 'In Review')"
                    );
                    $totalStmt->execute([':pet_id' => $petId]);
                    $app['total_in_queue'] = (int)$totalStmt->fetchColumn();
                } else {
                    $app['queue_position'] = null;
                    $app['total_in_queue'] = null;
                }
            }
            unset($app);

            return $applications;
        } catch (PDOException $e) {
            error_log("getUserApplications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the full waiting queue for a specific pet.
     */
    public function getQueueForPet(int $petId): array
    {
        $sql = "SELECT a.application_id, a.adopter_id, a.pet_id, a.application_date, a.status, a.notes,
                       ad.full_name AS adopter_name, ad.email, ad.phone_number, ad.address
                FROM adoption_applications a
                JOIN adopters ad ON a.adopter_id = ad.adopter_id
                WHERE a.pet_id = :pet_id AND a.status IN ('Pending', 'In Review')
                ORDER BY a.application_date ASC, a.application_id ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':pet_id' => $petId]);
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Assign position index
            $pos = 1;
            foreach ($list as &$item) {
                $item['position'] = $pos++;
            }
            unset($item);

            return $list;
        } catch (PDOException $e) {
            error_log("getQueueForPet error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active queue entries grouped by pet for administrator review.
     */
    public function getAllQueuesGroupedByPet(): array
    {
        $sql = "SELECT p.pet_id, p.name AS pet_name, p.breed, p.status AS pet_status, p.image_url,
                       s.species_name,
                       a.application_id, a.application_date, a.status AS app_status, a.notes,
                       ad.adopter_id, ad.full_name AS adopter_name, ad.email, ad.phone_number, ad.address
                FROM adoption_applications a
                JOIN pets p ON a.pet_id = p.pet_id
                JOIN species s ON p.species_id = s.species_id
                JOIN adopters ad ON a.adopter_id = ad.adopter_id
                WHERE a.status IN ('Pending', 'In Review')
                ORDER BY p.pet_id ASC, a.application_date ASC, a.application_id ASC";

        try {
            $stmt = $this->conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $row) {
                $pid = (int)$row['pet_id'];
                if (!isset($grouped[$pid])) {
                    $grouped[$pid] = [
                        'pet_id'       => $pid,
                        'pet_name'     => $row['pet_name'],
                        'breed'        => $row['breed'],
                        'species_name' => $row['species_name'],
                        'pet_status'   => $row['pet_status'],
                        'image_url'    => $row['image_url'],
                        'applicants'   => []
                    ];
                }
                $pos = count($grouped[$pid]['applicants']) + 1;
                $row['position'] = $pos;
                $grouped[$pid]['applicants'][] = $row;
            }

            return array_values($grouped);
        } catch (PDOException $e) {
            error_log("getAllQueuesGroupedByPet error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a user (by email) has already applied for a specific pet.
     */
    public function hasUserApplied(string $userEmail, int $petId): ?array
    {
        $sql = "SELECT a.application_id, a.status, a.application_date
                FROM adoption_applications a
                JOIN adopters ad ON a.adopter_id = ad.adopter_id
                WHERE LOWER(ad.email) = LOWER(:email) 
                  AND a.pet_id = :pet_id 
                  AND a.status IN ('Pending', 'In Review')
                LIMIT 1";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':email' => $userEmail, ':pet_id' => $petId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (PDOException $e) {
            error_log("hasUserApplied error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Submit an adoption application and place user in the waiting queue (TRANSACTION #2).
     * Atomically ensures the adopter profile exists, registers the application,
     * and logs the event in activity_logs.
     */
    public function submitApplication(
        string $fullName,
        string $email,
        string $phone,
        string $address,
        int $petId,
        string $notes,
        ?int $userId = null
    ): bool {
        try {
            // TRANSACTION BEGIN
            $this->conn->beginTransaction();

            // 1. Verify pet is available
            $petCheck = $this->conn->prepare("SELECT status FROM pets WHERE pet_id = :pet_id LIMIT 1");
            $petCheck->execute([':pet_id' => $petId]);
            $petStatus = $petCheck->fetchColumn();
            if ($petStatus !== 'Available') {
                $this->conn->rollBack();
                return false;
            }

            // 2. Find or create adopter record
            $stmt = $this->conn->prepare("SELECT adopter_id FROM adopters WHERE LOWER(email) = LOWER(:email) LIMIT 1");
            $stmt->execute([':email' => $email]);
            $adopterId = $stmt->fetchColumn();

            if (!$adopterId) {
                $insertAdopter = $this->conn->prepare(
                    "INSERT INTO adopters (full_name, email, phone_number, address) 
                     VALUES (:name, :email, :phone, :address)"
                );
                $insertAdopter->execute([
                    ':name'    => $fullName,
                    ':email'   => $email,
                    ':phone'   => $phone ?: '0900-000-0000',
                    ':address' => $address ?: 'Philippines'
                ]);
                $adopterId = (int)$this->conn->lastInsertId();
            }

            // 3. Insert into adoption_applications
            $insertApp = $this->conn->prepare(
                "INSERT INTO adoption_applications (adopter_id, pet_id, application_date, status, notes)
                 VALUES (:adopter_id, :pet_id, CURDATE(), 'Pending', :notes)"
            );
            $insertApp->execute([
                ':adopter_id' => $adopterId,
                ':pet_id'     => $petId,
                ':notes'      => $notes ?: 'Adoption interest submitted via PawCare online portal.'
            ]);
            $appId = (int)$this->conn->lastInsertId();

            // 4. Log to activity_logs if a valid user_id is provided
            if ($userId !== null && $userId > 0) {
                $logStmt = $this->conn->prepare(
                    "INSERT INTO activity_logs (action, user_id) VALUES (:action, :user_id)"
                );
                $logStmt->execute([
                    ':action'  => "Submitted adoption queue application #{$appId} for pet #{$petId}",
                    ':user_id' => $userId
                ]);
            }

            // TRANSACTION COMMIT
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            // TRANSACTION ROLLBACK
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("submitApplication transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin update status of an application (TRANSACTION #3).
     * When an application is 'Approved':
     *  - Sets application status to 'Approved'
     *  - Sets pet status to 'Adopted'
     *  - Automatically resolves remaining queue applicants for this pet
     *  - Records the administrative action in activity_logs
     */
    public function updateStatus(int $applicationId, string $newStatus, ?string $adminNotes = null, ?int $adminUserId = null): bool
    {
        $allowed = ['Pending', 'In Review', 'Approved', 'Rejected', 'Cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            return false;
        }

        try {
            // TRANSACTION BEGIN
            $this->conn->beginTransaction();

            // 1. Fetch current application & pet_id
            $fetchStmt = $this->conn->prepare("SELECT pet_id, adopter_id FROM adoption_applications WHERE application_id = :id LIMIT 1");
            $fetchStmt->execute([':id' => $applicationId]);
            $appRow = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            if (!$appRow) {
                $this->conn->rollBack();
                return false;
            }
            $petId = (int)$appRow['pet_id'];

            // 2. Update the target application
            $sql = "UPDATE adoption_applications SET status = :status";
            $params = [':status' => $newStatus, ':id' => $applicationId];

            if ($adminNotes !== null) {
                $sql .= ", notes = :notes";
                $params[':notes'] = $adminNotes;
            }
            $sql .= " WHERE application_id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            // 3. If approved, update pet to 'Adopted' and pass remaining queue applicants
            if ($newStatus === 'Approved') {
                $petStmt = $this->conn->prepare(
                    "UPDATE pets SET status = 'Adopted' WHERE pet_id = :pet_id"
                );
                $petStmt->execute([':pet_id' => $petId]);

                // Auto-pass/cancel remaining pending applicants for this pet
                $passOthers = $this->conn->prepare(
                    "UPDATE adoption_applications 
                     SET status = 'Cancelled', notes = CONCAT(COALESCE(notes, ''), ' [Auto-closed: Pet adopted by earlier applicant in queue]')
                     WHERE pet_id = :pet_id AND application_id != :aid AND status IN ('Pending', 'In Review')"
                );
                $passOthers->execute([':pet_id' => $petId, ':aid' => $applicationId]);
            }

            // 4. Log admin action in activity_logs if adminUserId provided
            if ($adminUserId !== null && $adminUserId > 0) {
                $logStmt = $this->conn->prepare(
                    "INSERT INTO activity_logs (action, user_id) VALUES (:action, :user_id)"
                );
                $logStmt->execute([
                    ':action'  => "Updated application #{$applicationId} to '{$newStatus}' for pet #{$petId}",
                    ':user_id' => $adminUserId
                ]);
            }

            // TRANSACTION COMMIT
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            // TRANSACTION ROLLBACK
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("updateStatus transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Allow an applicant to cancel their waiting queue application.
     */
    public function cancelApplication(int $applicationId, string $userEmail): bool
    {
        $sql = "UPDATE adoption_applications a
                JOIN adopters ad ON a.adopter_id = ad.adopter_id
                SET a.status = 'Cancelled'
                WHERE a.application_id = :aid AND LOWER(ad.email) = LOWER(:email)";
        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([':aid' => $applicationId, ':email' => $userEmail]);
        } catch (PDOException $e) {
            error_log("cancelApplication error: " . $e->getMessage());
            return false;
        }
    }
}

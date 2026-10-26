<?php
/**
 * MedicalLog.php
 * Handles pet medical history (vaccinations, treatments, surgeries, checkups)
 * and generates health reminders/notifications for volunteers and coordinators.
 */

require_once __DIR__ . '/../config/Database.php';

class MedicalLog
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Fetch all medical records, optionally filtered by pet.
     */
    public function getAllRecords(?int $petId = null): array
    {
        $sql = "SELECT mr.record_id, mr.pet_id, mr.treatment_type, mr.description, mr.date_administered,
                       p.name AS pet_name, p.breed, p.status AS pet_status, p.image_url,
                       s.species_name
                FROM medical_records mr
                JOIN pets p ON mr.pet_id = p.pet_id
                JOIN species s ON p.species_id = s.species_id";

        if ($petId !== null && $petId > 0) {
            $sql .= " WHERE mr.pet_id = :pid";
        }

        $sql .= " ORDER BY mr.date_administered DESC, mr.record_id DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            if ($petId !== null && $petId > 0) {
                $stmt->execute([':pid' => $petId]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("getAllRecords error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add a new medical treatment / vaccination (TRANSACTION).
     * Atomically inserts medical record and logs action in activity_logs.
     */
    public function addRecord(int $petId, string $treatmentType, string $description, string $dateAdministered, ?int $userId = null): bool
    {
        try {
            $this->conn->beginTransaction();

            // 1. Verify pet
            $pStmt = $this->conn->prepare("SELECT name FROM pets WHERE pet_id = :pid LIMIT 1");
            $pStmt->execute([':pid' => $petId]);
            $petName = $pStmt->fetchColumn();
            if (!$petName) {
                $this->conn->rollBack();
                return false;
            }

            // 2. Insert medical record
            $stmt = $this->conn->prepare(
                "INSERT INTO medical_records (pet_id, treatment_type, description, date_administered)
                 VALUES (:pid, :treatment, :description, :date_administered)"
            );
            $stmt->execute([
                ':pid'               => $petId,
                ':treatment'         => $treatmentType,
                ':description'       => $description,
                ':date_administered' => $dateAdministered ?: date('Y-m-d')
            ]);
            $recordId = (int)$this->conn->lastInsertId();

            // 3. Log to activity_logs
            if ($userId !== null && $userId > 0) {
                $logStmt = $this->conn->prepare("INSERT INTO activity_logs (action, user_id) VALUES (:action, :uid)");
                $logStmt->execute([
                    ':action' => "Logged {$treatmentType} for pet #{$petId} ({$petName}): {$description}",
                    ':uid'    => $userId
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("addRecord transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate notifications/reminders for volunteers and coordinators regarding
     * upcoming vaccinations, overdue checkups, and routine preventative care.
     */
    public function getVolunteerReminders(): array
    {
        $sql = "SELECT p.pet_id, p.name AS pet_name, p.breed, p.status, p.intake_date, p.image_url,
                       s.species_name,
                       (SELECT MAX(date_administered) FROM medical_records WHERE pet_id = p.pet_id AND LOWER(treatment_type) = 'vaccination') AS last_vaccine_date,
                       (SELECT MAX(date_administered) FROM medical_records WHERE pet_id = p.pet_id AND LOWER(treatment_type) = 'deworming') AS last_deworming_date,
                       (SELECT MAX(date_administered) FROM medical_records WHERE pet_id = p.pet_id AND LOWER(treatment_type) = 'checkup') AS last_checkup_date,
                       (SELECT COUNT(*) FROM medical_records WHERE pet_id = p.pet_id) AS total_records
                FROM pets p
                JOIN species s ON p.species_id = s.species_id
                WHERE p.status IN ('Available', 'Fostered')
                ORDER BY p.pet_id ASC";

        try {
            $stmt = $this->conn->query($sql);
            $pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $reminders = [];
            $now = new DateTime();

            foreach ($pets as $p) {
                // Rule 1: Pet has NO medical records at all -> Urgent Initial Health Screening
                if ((int)$p['total_records'] === 0) {
                    $reminders[] = [
                        'pet_id'       => (int)$p['pet_id'],
                        'pet_name'     => $p['pet_name'],
                        'species_name' => $p['species_name'],
                        'breed'        => $p['breed'],
                        'image_url'    => $p['image_url'],
                        'pet_status'   => $p['status'],
                        'type'         => 'Intake Health Exam',
                        'urgency'      => 'High',
                        'badge_color'  => 'bg-rose-100 text-rose-700 border-rose-200',
                        'title'        => "Initial Health Screening Required",
                        'message'      => "{$p['pet_name']} has no recorded medical history since shelter intake ({$p['intake_date']}). Schedule wellness exam and baseline vaccines.",
                        'last_date'    => 'Never',
                        'due_date'     => date('Y-m-d')
                    ];
                } else {
                    // Rule 2: Vaccination Check (annual or 6-month booster)
                    if (empty($p['last_vaccine_date'])) {
                        $reminders[] = [
                            'pet_id'       => (int)$p['pet_id'],
                            'pet_name'     => $p['pet_name'],
                            'species_name' => $p['species_name'],
                            'breed'        => $p['breed'],
                            'image_url'    => $p['image_url'],
                            'pet_status'   => $p['status'],
                            'type'         => 'Vaccination',
                            'urgency'      => 'High',
                            'badge_color'  => 'bg-rose-100 text-rose-700 border-rose-200',
                            'title'        => "Vaccination Due",
                            'message'      => "Core vaccination (e.g. Anti-rabies / 5-in-1) not recorded for {$p['pet_name']}.",
                            'last_date'    => 'Never',
                            'due_date'     => date('Y-m-d')
                        ];
                    } else {
                        $vDate = new DateTime($p['last_vaccine_date']);
                        $monthsSince = $now->diff($vDate)->m + ($now->diff($vDate)->y * 12);
                        if ($monthsSince >= 6) {
                            $reminders[] = [
                                'pet_id'       => (int)$p['pet_id'],
                                'pet_name'     => $p['pet_name'],
                                'species_name' => $p['species_name'],
                                'breed'        => $p['breed'],
                                'image_url'    => $p['image_url'],
                                'pet_status'   => $p['status'],
                                'type'         => 'Vaccination',
                                'urgency'      => 'Medium',
                                'badge_color'  => 'bg-amber-100 text-amber-700 border-amber-200',
                                'title'        => "Vaccine Booster Due Soon",
                                'message'      => "Last vaccine was on {$p['last_vaccine_date']} ({$monthsSince} months ago). Booster recommended.",
                                'last_date'    => $p['last_vaccine_date'],
                                'due_date'     => date('Y-m-d', strtotime('+7 days'))
                            ];
                        }
                    }

                    // Rule 3: Routine Deworming / Vet Checkup (every 3-6 months)
                    if (!empty($p['last_deworming_date'])) {
                        $dDate = new DateTime($p['last_deworming_date']);
                        $monthsDeworm = $now->diff($dDate)->m + ($now->diff($dDate)->y * 12);
                        if ($monthsDeworm >= 3) {
                            $reminders[] = [
                                'pet_id'       => (int)$p['pet_id'],
                                'pet_name'     => $p['pet_name'],
                                'species_name' => $p['species_name'],
                                'breed'        => $p['breed'],
                                'image_url'    => $p['image_url'],
                                'pet_status'   => $p['status'],
                                'type'         => 'Deworming',
                                'urgency'      => 'Medium',
                                'badge_color'  => 'bg-purple-100 text-purple-700 border-purple-200',
                                'title'        => "Quarterly Deworming Due",
                                'message'      => "{$p['pet_name']}'s last deworming was on {$p['last_deworming_date']}.",
                                'last_date'    => $p['last_deworming_date'],
                                'due_date'     => date('Y-m-d', strtotime('+14 days'))
                            ];
                        }
                    }
                }
            }

            return $reminders;
        } catch (PDOException $e) {
            error_log("getVolunteerReminders error: " . $e->getMessage());
            return [];
        }
    }
}


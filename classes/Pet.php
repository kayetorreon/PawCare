<?php
/**
 * Pet.php
 * Main entity class for the PawCare Pet Adoption and Foster Tracker.
 * Implements OOP + PDO prepared-statement CRUD and search for the PETS table.
 */

require_once __DIR__ . '/../config/Database.php';

class Pet
{
    private PDO $conn;
    private string $table = "PETS";

    // Entity properties (mirror the PETS table / ERD)
    public ?int $pet_id = null;
    public string $name = "";
    public string $breed = "";
    public int $age = 0;
    public string $gender = "";
    public string $intake_date = "";
    public string $status = "";
    public int $species_id = 0;
    public ?string $image_url = null;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Get a suitable fallback photo based on species if none provided.
     */
    public static function getDefaultImage(string $speciesName = ''): string
    {
        $species = strtolower(trim($speciesName));
        if (str_contains($species, 'cat')) {
            return 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=600&q=80';
        } elseif (str_contains($species, 'rabbit')) {
            return 'https://images.unsplash.com/photo-1585110396000-c9ffd4e4b308?auto=format&fit=crop&w=600&q=80';
        } elseif (str_contains($species, 'bird') || str_contains($species, 'parrot')) {
            return 'https://images.unsplash.com/photo-1552728089-57bdde30beb3?auto=format&fit=crop&w=600&q=80';
        } elseif (str_contains($species, 'hamster') || str_contains($species, 'guinea')) {
            return 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?auto=format&fit=crop&w=600&q=80';
        } elseif (str_contains($species, 'turtle') || str_contains($species, 'tortoise') || str_contains($species, 'reptile')) {
            return 'https://images.unsplash.com/photo-1437622368342-7a3d73a34c8f?auto=format&fit=crop&w=600&q=80';
        }
        // Default dog / companion
        return 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=600&q=80';
    }

    /**
     * Insert a new pet record.
     */
    public function addPet(
        string $name,
        string $breed,
        int $age,
        string $gender,
        string $intake_date,
        string $status,
        int $species_id,
        ?string $image_url = null
    ): bool {
        $sql = "INSERT INTO {$this->table} (name, breed, age, gender, intake_date, status, species_id, image_url)
                VALUES (:name, :breed, :age, :gender, :intake_date, :status, :species_id, :image_url)";

        try {
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':breed', $breed, PDO::PARAM_STR);
            $stmt->bindParam(':age', $age, PDO::PARAM_INT);
            $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
            $stmt->bindParam(':intake_date', $intake_date, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':species_id', $species_id, PDO::PARAM_INT);
            $stmt->bindParam(':image_url', $image_url, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("addPet failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch all pet records, joined with SPECIES for display.
     */
    public function getPets(): array
    {
        $sql = "SELECT p.pet_id, p.name, p.breed, p.age, p.gender, p.intake_date, p.status,
                       p.species_id, p.image_url, s.species_name
                FROM {$this->table} p
                INNER JOIN SPECIES s ON p.species_id = s.species_id
                ORDER BY p.pet_id DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPets failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch a single pet record by id (used to pre-populate the edit form).
     */
    public function getPetById(int $id): array|false
    {
        $sql = "SELECT * FROM {$this->table} WHERE pet_id = :id LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPetById failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing pet record by pet_id.
     */
    public function updatePet(
        int $id,
        string $name,
        string $breed,
        int $age,
        string $gender,
        string $intake_date,
        string $status,
        int $species_id,
        ?string $image_url = null
    ): bool {
        $sql = "UPDATE {$this->table}
                SET name = :name, breed = :breed, age = :age, gender = :gender,
                    intake_date = :intake_date, status = :status, species_id = :species_id,
                    image_url = :image_url
                WHERE pet_id = :id";

        try {
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':breed', $breed, PDO::PARAM_STR);
            $stmt->bindParam(':age', $age, PDO::PARAM_INT);
            $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
            $stmt->bindParam(':intake_date', $intake_date, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':species_id', $species_id, PDO::PARAM_INT);
            $stmt->bindParam(':image_url', $image_url, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("updatePet failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a pet record by pet_id (TRANSACTION #4).
     * Atomically deletes associated waiting queue applications and pet record,
     * recording an audit trail in activity_logs.
     */
    public function deletePet(int $id, ?int $adminUserId = null): bool
    {
        try {
            // TRANSACTION BEGIN
            $this->conn->beginTransaction();

            // 1. Delete associated adoption applications for this pet
            $appStmt = $this->conn->prepare("DELETE FROM adoption_applications WHERE pet_id = :id");
            $appStmt->execute([':id' => $id]);

            // 2. Delete pet
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE pet_id = :id");
            $stmt->execute([':id' => $id]);

            // 3. Log deletion in activity_logs if adminUserId provided
            if ($adminUserId !== null && $adminUserId > 0) {
                $logStmt = $this->conn->prepare(
                    "INSERT INTO activity_logs (action, user_id) VALUES (:action, :user_id)"
                );
                $logStmt->execute([
                    ':action'  => "Deleted pet record #{$id}",
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
            error_log("deletePet transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search pets across name, breed, gender, status, and species_name using LIKE.
     */
    public function searchPets(string $keyword): array
    {
        $sql = "SELECT p.pet_id, p.name, p.breed, p.age, p.gender, p.intake_date, p.status,
                       p.species_id, p.image_url, s.species_name
                FROM {$this->table} p
                INNER JOIN SPECIES s ON p.species_id = s.species_id
                WHERE p.name LIKE :keyword
                   OR p.breed LIKE :keyword
                   OR p.gender LIKE :keyword
                   OR p.status LIKE :keyword
                   OR s.species_name LIKE :keyword
                ORDER BY p.pet_id DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $like = "%{$keyword}%";
            $stmt->bindParam(':keyword', $like, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("searchPets failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get aggregate statistics for dashboard display.
     */
    public function getStats(): array
    {
        try {
            $total = (int)$this->conn->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
            $available = (int)$this->conn->query("SELECT COUNT(*) FROM {$this->table} WHERE LOWER(status) = 'available'")->fetchColumn();
            $fostered = (int)$this->conn->query("SELECT COUNT(*) FROM {$this->table} WHERE LOWER(status) = 'fostered'")->fetchColumn();
            $adopted = (int)$this->conn->query("SELECT COUNT(*) FROM {$this->table} WHERE LOWER(status) = 'adopted'")->fetchColumn();

            return [
                'total'     => $total,
                'available' => $available,
                'fostered'  => $fostered,
                'adopted'   => $adopted,
            ];
        } catch (PDOException $e) {
            error_log("getStats failed: " . $e->getMessage());
            return [
                'total'     => 0,
                'available' => 0,
                'fostered'  => 0,
                'adopted'   => 0,
            ];
        }
    }
}

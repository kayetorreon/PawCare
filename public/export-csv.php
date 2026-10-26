<?php
/**
 * export-csv.php
 * Handles instant CSV data export for Pet Roster, Medical History, and Foster Assignments.
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::requireLogin('login.php');

$type = strtolower(trim($_GET['type'] ?? 'pets'));
$database = new Database();
$db = $database->getConnection();

$today = date('Y-m-d');

switch ($type) {
    case 'medical':
        $filename = "pawcare_medical_records_{$today}.csv";
        $headers = ['Record ID', 'Pet ID', 'Pet Name', 'Species', 'Breed', 'Treatment Type', 'Clinical Description', 'Date Administered', 'Current Pet Status'];

        $sql = "SELECT mr.record_id, mr.pet_id, p.name AS pet_name, s.species_name, p.breed, 
                       mr.treatment_type, mr.description, mr.date_administered, p.status AS pet_status
                FROM medical_records mr
                JOIN pets p ON mr.pet_id = p.pet_id
                JOIN species s ON p.species_id = s.species_id
                ORDER BY mr.date_administered DESC, mr.record_id DESC";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'foster':
        $filename = "pawcare_foster_assignments_{$today}.csv";
        $headers = ['Assignment ID', 'Pet ID', 'Pet Name', 'Species', 'Breed', 'Foster Parent', 'Contact Phone', 'Foster Address', 'Start Date', 'End Date', 'Placement Status', 'Pet Status'];

        $sql = "SELECT fa.assignment_id, fa.pet_id, p.name AS pet_name, s.species_name, p.breed,
                       u.full_name AS foster_parent, u.phone AS foster_phone, fh.address,
                       fa.start_date, COALESCE(fa.end_date, 'Active') AS end_date,
                       CASE WHEN fa.end_date IS NULL THEN 'Active Placement' ELSE 'Completed' END AS placement_status,
                       p.status AS pet_status
                FROM foster_assignments fa
                JOIN pets p ON fa.pet_id = p.pet_id
                JOIN species s ON p.species_id = s.species_id
                JOIN foster_homes fh ON fa.foster_id = fh.foster_id
                JOIN users u ON fh.user_id = u.user_id
                ORDER BY fa.start_date DESC";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'pets':
    default:
        $filename = "pawcare_pets_roster_{$today}.csv";
        $headers = ['Pet ID', 'Name', 'Species', 'Breed', 'Age (Years)', 'Gender', 'Intake Date', 'Adoption Status'];

        $sql = "SELECT p.pet_id, p.name, s.species_name, p.breed, p.age, p.gender, p.intake_date, p.status
                FROM pets p
                JOIN species s ON p.species_id = s.species_id
                ORDER BY p.pet_id DESC";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Send download headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
fputcsv($output, $headers);

// Write data rows
foreach ($rows as $row) {
    fputcsv($output, array_values($row));
}

fclose($output);
exit;


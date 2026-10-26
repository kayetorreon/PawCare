<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/AdoptionQueue.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();

$database = new Database();
$db = $database->getConnection();
$queue = new AdoptionQueue($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$applicationId = (int)($_POST['application_id'] ?? $_GET['application_id'] ?? 0);

if ($applicationId <= 0) {
    header('Location: index.php');
    exit;
}

if ($action === 'cancel') {
    // User cancels their own queue application
    $cancelled = $queue->cancelApplication($applicationId, $currentUser['email']);
    header('Location: index.php?queue_cancelled=1');
    exit;
}

// All other actions require admin
if (!$isAdmin) {
    header('Location: index.php?error=admin_only');
    exit;
}

switch ($action) {
    case 'approve':
        $notes = trim($_POST['notes'] ?? 'Adoption application approved by administrator.');
        // Transaction #3: Approve application, update pet status to Adopted, auto-close remaining queue applicants
        $queue->updateStatus($applicationId, 'Approved', $notes, (int)$currentUser['id']);
        header('Location: index.php?queue_updated=approved&cert_id=' . $applicationId . '#queue-section');
        exit;

    case 'in_review':
        $notes = trim($_POST['notes'] ?? 'Application under review / background check.');
        $queue->updateStatus($applicationId, 'In Review', $notes, (int)$currentUser['id']);
        header('Location: index.php?queue_updated=review#queue-section');
        exit;

    case 'reject':
        $notes = trim($_POST['notes'] ?? 'Application rejected / passed to next applicant in line.');
        $queue->updateStatus($applicationId, 'Rejected', $notes, (int)$currentUser['id']);
        header('Location: index.php?queue_updated=rejected#queue-section');
        exit;

    default:
        header('Location: index.php');
        exit;
}

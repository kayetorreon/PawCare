<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Pet.php';
require_once __DIR__ . '/../classes/Auth.php';

// Only administrators are allowed to delete pets
Auth::requireAdmin('index.php?error=admin_only');
$currentUser = Auth::getCurrentUser();

$database = new Database();
$db = $database->getConnection();
$petEntity = new Pet($db);

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Transaction #4: Delete pet, cascade dependent applications, log activity
    $petEntity->deletePet($id, $currentUser ? (int)$currentUser['id'] : null);
}

header('Location: index.php?deleted=1');
exit;

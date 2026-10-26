<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Pet.php';
require_once __DIR__ . '/../classes/FosterTracker.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isVolunteer = Auth::isVolunteer();

$database = new Database();
$db = $database->getConnection();
$fosterTracker = new FosterTracker($db);
$petEntity = new Pet($db);

$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $petId = (int)($_POST['pet_id'] ?? 0);
        $fosterId = (int)($_POST['foster_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));

        if ($petId > 0 && $fosterId > 0) {
            $ok = $fosterTracker->assignPet($petId, $fosterId, $startDate, (int)$currentUser['id']);
            if ($ok) {
                $msg = 'Pet successfully assigned to foster home!';
            } else {
                $err = 'Failed to assign pet. Please check availability.';
            }
        } else {
            $err = 'Please select a valid pet and foster home.';
        }
    } elseif ($action === 'end') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $endDate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $newStatus = trim($_POST['new_status'] ?? 'Available');

        if ($assignmentId > 0) {
            $ok = $fosterTracker->endAssignment($assignmentId, $endDate, $newStatus, (int)$currentUser['id']);
            if ($ok) {
                $msg = "Foster placement concluded. Pet is now marked as {$newStatus}.";
            } else {
                $err = 'Failed to end foster placement.';
            }
        }
    }
}

$activeAssignments = $fosterTracker->getAllAssignments('active');
$pastAssignments = $fosterTracker->getAllAssignments('past');
$fosterHomes = $fosterTracker->getFosterHomes();
$allPets = $petEntity->getPets();
// Pets eligible for foster (Available)
$eligiblePets = array_filter($allPets, fn($p) => strcasecmp($p['status'], 'Available') === 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — Foster Assignment Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B] min-h-screen flex flex-col">
    <?php include __DIR__ . '/header.php'; ?>

    <main class="max-w-7xl mx-auto px-6 py-10 flex-grow w-full">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="index.php" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1.5 mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-house-chimney-heart"></i> Feature #5 • Foster Care
                </div>
                <h1 class="text-3xl font-extrabold text-[#1E1B4B]">Foster Assignment Tracking</h1>
                <p class="text-xs text-gray-500 mt-1">Track which rescue pets are placed with approved foster families, view home capacities, and manage placement lifecycles.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 self-start md:self-auto">
                <a href="export-csv.php?type=foster" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
                <?php if ($isAdmin || $isVolunteer): ?>
                    <button onclick="document.getElementById('assignModal').classList.remove('hidden')" 
                            class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> New Foster Assignment
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-red-600 text-base"></i> <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <!-- Foster Homes Capacity Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
            <?php foreach ($fosterHomes as $home): ?>
                <?php 
                    $occ = (int)$home['current_occupancy'];
                    $cap = (int)$home['capacity'];
                    $percent = $cap > 0 ? min(100, round(($occ / $cap) * 100)) : 0;
                    $isFull = $occ >= $cap;
                ?>
                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB] relative overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg flex-shrink-0">
                                <i class="fa-solid fa-house-user"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-[#1E1B4B] leading-tight"><?= htmlspecialchars($home['full_name']) ?></h3>
                                <p class="text-[11px] text-gray-500 mt-0.5"><?= htmlspecialchars($home['email']) ?></p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $isFull ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' ?>">
                            <?= $isFull ? 'At Capacity' : ($cap - $occ) . ' Available' ?>
                        </span>
                    </div>

                    <p class="text-xs text-gray-600 mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-location-dot text-gray-400 text-xs"></i>
                        <span class="truncate"><?= htmlspecialchars($home['address']) ?></span>
                    </p>

                    <div class="space-y-1.5">
                        <div class="flex justify-between text-[11px] font-semibold">
                            <span class="text-gray-500">Current Occupancy</span>
                            <span class="text-[#1E1B4B]"><?= $occ ?> / <?= $cap ?> Pets</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            <div class="h-full <?= $isFull ? 'bg-rose-500' : 'bg-amber-500' ?> rounded-full transition-all" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Active Foster Placements Table -->
        <div class="bg-white rounded-3xl shadow-xl border border-[#F2EEFB] overflow-hidden mb-10">
            <div class="p-6 border-b border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-[#1E1B4B] flex items-center gap-2">
                        <i class="fa-solid fa-heart-pulse text-amber-500"></i> Active Foster Placements
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Currently fostered pets living with registered foster parents.</p>
                </div>
                <span class="px-3 py-1 bg-amber-50 text-amber-800 text-xs font-bold rounded-xl border border-amber-200">
                    <?= count($activeAssignments) ?> Active Placement<?= count($activeAssignments) === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($activeAssignments)): ?>
                <div class="p-12 text-center text-gray-400">
                    <i class="fa-solid fa-paw text-3xl mb-2 text-gray-300 block"></i>
                    <p class="text-sm font-semibold">No pets currently in foster care.</p>
                    <p class="text-xs text-gray-400 mt-1">Click "New Foster Assignment" above to place an available rescue into a foster home.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#FAF9FD] text-gray-500 uppercase tracking-wider text-[11px] font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Fostered Pet</th>
                                <th class="px-6 py-4">Foster Parent / Caregiver</th>
                                <th class="px-6 py-4">Foster Location</th>
                                <th class="px-6 py-4">Placement Date</th>
                                <th class="px-6 py-4">Time in Foster</th>
                                <th class="px-6 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            <?php foreach ($activeAssignments as $row): ?>
                                <?php 
                                    $sDate = new DateTime($row['start_date']);
                                    $days = (new DateTime())->diff($sDate)->days;
                                ?>
                                <tr class="hover:bg-purple-50/40 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= htmlspecialchars(!empty($row['image_url']) ? $row['image_url'] : Pet::getDefaultImage($row['species_name'])) ?>" 
                                                 alt="<?= htmlspecialchars($row['pet_name']) ?>"
                                                 class="w-10 h-10 rounded-xl object-cover shadow-sm border border-purple-100">
                                            <div>
                                                <span class="font-bold text-sm text-[#1E1B4B] block"><?= htmlspecialchars($row['pet_name']) ?></span>
                                                <span class="text-[11px] text-gray-500"><?= htmlspecialchars($row['breed']) ?> (<?= htmlspecialchars($row['species_name']) ?>)</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-[#1E1B4B]"><?= htmlspecialchars($row['foster_parent']) ?></div>
                                        <div class="text-[11px] text-gray-500 flex items-center gap-1.5 mt-0.5">
                                            <i class="fa-solid fa-phone text-gray-400"></i> <?= htmlspecialchars($row['foster_phone'] ?: 'No phone') ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 max-w-xs truncate">
                                        <?= htmlspecialchars($row['address']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 font-semibold">
                                        <?= htmlspecialchars($row['start_date']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-50 text-[#6D28D9] border border-purple-200 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-calendar-days text-[9px]"></i> <?= $days ?> day<?= $days === 1 ? '' : 's' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($isAdmin || $isVolunteer): ?>
                                            <form method="POST" action="foster-tracking.php" onsubmit="return confirm('Conclude foster placement for <?= htmlspecialchars(addslashes($row['pet_name'])) ?>?');" class="inline-flex gap-1.5">
                                                <input type="hidden" name="action" value="end">
                                                <input type="hidden" name="assignment_id" value="<?= (int)$row['assignment_id'] ?>">
                                                <input type="hidden" name="end_date" value="<?= date('Y-m-d') ?>">
                                                <select name="new_status" class="text-[11px] border border-gray-200 rounded-lg px-2 py-1 bg-white font-medium focus:outline-none">
                                                    <option value="Available">Return to Shelter (Available)</option>
                                                    <option value="Adopted">Foster Adopted (Adopted)</option>
                                                </select>
                                                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] px-3 py-1 rounded-lg transition-colors shadow-sm">
                                                    End Placement
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Foster History Table -->
        <?php if (!empty($pastAssignments)): ?>
            <div class="bg-white rounded-3xl shadow-lg border border-[#F2EEFB] overflow-hidden">
                <div class="p-5 border-b border-[#F2EEFB]">
                    <h2 class="text-base font-bold text-gray-700 flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-gray-400"></i> Foster History & Completed Placements
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#FAF9FD] text-gray-400 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th class="px-6 py-3">Pet</th>
                                <th class="px-6 py-3">Caregiver</th>
                                <th class="px-6 py-3">Start Date</th>
                                <th class="px-6 py-3">End Date</th>
                                <th class="px-6 py-3">Current Pet Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-600">
                            <?php foreach ($pastAssignments as $pRow): ?>
                                <tr>
                                    <td class="px-6 py-3.5 font-bold text-[#1E1B4B]"><?= htmlspecialchars($pRow['pet_name']) ?></td>
                                    <td class="px-6 py-3.5"><?= htmlspecialchars($pRow['foster_parent']) ?></td>
                                    <td class="px-6 py-3.5"><?= htmlspecialchars($pRow['start_date']) ?></td>
                                    <td class="px-6 py-3.5 font-medium text-gray-500"><?= htmlspecialchars($pRow['end_date']) ?></td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700">
                                            <?= htmlspecialchars($pRow['pet_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- NEW FOSTER ASSIGNMENT MODAL -->
    <div id="assignModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-purple-100 relative">
            <button onclick="document.getElementById('assignModal').classList.add('hidden')" class="absolute top-4 right-4 bg-gray-100 hover:bg-gray-200 text-gray-600 w-8 h-8 rounded-full flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <h3 class="text-xl font-extrabold text-[#1E1B4B] mb-1">New Foster Placement</h3>
            <p class="text-xs text-gray-500 mb-5">Assign an available shelter pet to a registered foster home.</p>

            <form method="POST" action="foster-tracking.php" class="space-y-4">
                <input type="hidden" name="action" value="assign">

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Select Pet to Foster</label>
                    <select name="pet_id" required class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        <option value="">-- Choose an Available Pet --</option>
                        <?php foreach ($eligiblePets as $elPet): ?>
                            <option value="<?= (int)$elPet['pet_id'] ?>">
                                <?= htmlspecialchars($elPet['name']) ?> (<?= htmlspecialchars($elPet['breed']) ?> - <?= (int)$elPet['age'] ?> yrs)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Select Foster Home / Parent</label>
                    <select name="foster_id" required class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        <option value="">-- Choose Foster Home --</option>
                        <?php foreach ($fosterHomes as $fh): ?>
                            <option value="<?= (int)$fh['foster_id'] ?>">
                                <?= htmlspecialchars($fh['full_name']) ?> — <?= htmlspecialchars($fh['address']) ?> (<?= $fh['current_occupancy'] ?>/<?= $fh['capacity'] ?> capacity)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Placement Start Date</label>
                    <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required
                           class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 font-bold text-xs hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2 rounded-xl font-bold text-xs shadow-md transition-all">
                        Confirm Placement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>


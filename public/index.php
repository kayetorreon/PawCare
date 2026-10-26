<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Pet.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/AdoptionQueue.php';
require_once __DIR__ . '/../classes/MedicalLog.php';

$database = new Database();
$db = $database->getConnection();
$petEntity = new Pet($db);
$queueEntity = new AdoptionQueue($db);
$medLog = new MedicalLog($db);

$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isVolunteer = Auth::isVolunteer();
$isAdopter = Auth::isAdopter();

// Fetch summary stats for dashboards
$stats = $petEntity->getStats();
$queueCounts = $queueEntity->getQueueCounts();
$remindersCount = ($isVolunteer || $isAdmin) ? count($medLog->getVolunteerReminders()) : 0;

$allAvailablePets = array_values(array_filter($petEntity->getPets(), fn($p) => strcasecmp($p['status'], 'Available') === 0));
$featuredPet = !empty($allAvailablePets) ? $allAvailablePets[0] : null;

$myQueueApplications = [];
$userQueueByPet = [];
if ($currentUser) {
    $myQueueApplications = $queueEntity->getUserApplications($currentUser['email']);
    foreach ($myQueueApplications as $mqa) {
        if (in_array($mqa['status'], ['Pending', 'In Review'])) {
            $userQueueByPet[(int)$mqa['pet_id']] = $mqa;
        }
    }
}

$adminQueues = [];
$totalInAllQueues = 0;
if ($isAdmin) {
    $adminQueues = $queueEntity->getAllQueuesGroupedByPet();
    $totalInAllQueues = array_sum($queueCounts);
}

// Handle search (GET ?q=keyword), status filter (GET ?status=Available), and species filter (GET ?species=Dog)
$keyword       = trim($_GET['q'] ?? '');
$statusFilter  = trim($_GET['status'] ?? '');
$speciesFilter = trim($_GET['species'] ?? '');

// Fetch all species with pet count for filter tabs
$allSpeciesList = $db->query("SELECT s.species_id, s.species_name, COUNT(p.pet_id) AS pet_count 
                              FROM species s 
                              LEFT JOIN pets p ON s.species_id = p.species_id 
                              GROUP BY s.species_id 
                              ORDER BY pet_count DESC, s.species_name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($keyword !== '') {
    $pets = $petEntity->searchPets($keyword);
} else {
    $pets = $petEntity->getPets();
}

if ($statusFilter !== '') {
    $pets = array_values(array_filter($pets, fn($p) => strcasecmp($p['status'], $statusFilter) === 0));
}

if ($speciesFilter !== '') {
    $pets = array_values(array_filter($pets, fn($p) => strcasecmp($p['species_name'], $speciesFilter) === 0));
}

$statusBadge = [
    'Available' => 'bg-emerald-100 text-emerald-700 border border-emerald-300',
    'Fostered'  => 'bg-amber-100 text-amber-700 border border-amber-300',
    'Adopted'   => 'bg-indigo-100 text-indigo-700 border border-indigo-300',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — <?= $isAdmin ? 'Admin Dashboard' : ($isVolunteer ? 'Volunteer Portal' : ($currentUser ? 'Adopter Dashboard' : 'Pet Adoption & Foster Tracker')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B]">

    <!-- TOP NAVBAR WITH AUTH CONTROLS -->
    <header class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-[#F2EEFB]">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#6D28D9] rounded-xl flex items-center justify-center text-white shadow-md shadow-[#6D28D9]/20">
                    <i class="fa-solid fa-paw text-xl"></i>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-[#1E1B4B] block leading-none">PawCare</span>
                    <span class="text-[10px] font-semibold text-[#6D28D9] uppercase tracking-wider">Pet Tracker</span>
                </div>
            </a>

            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                <a href="index.php" class="text-[#6D28D9] font-bold">Dashboard</a>
                <?php if (!$currentUser): ?>
                    <a href="#services" class="hover:text-[#6D28D9] transition-colors">Services</a>
                <?php endif; ?>
                <a href="#pets-table" class="hover:text-[#6D28D9] transition-colors">Pet Directory</a>
                <button type="button" onclick="togglePawcareChat()" class="hover:text-[#6D28D9] font-bold transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-comments text-[#6D28D9]"></i> Chat
                </button>
                <?php if ($isAdmin): ?>
                    <span class="px-2.5 py-1 rounded-md bg-purple-50 text-[#6D28D9] text-xs font-bold border border-purple-200">
                        <i class="fa-solid fa-lock-open mr-1"></i> Admin Access
                    </span>
                <?php elseif ($isVolunteer): ?>
                    <span class="px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 text-xs font-bold border border-blue-200">
                        <i class="fa-solid fa-handshake-angle mr-1"></i> Volunteer View
                    </span>
                <?php endif; ?>
            </nav>

            <div class="flex items-center gap-3">
                <?php if ($currentUser): ?>
                    <div class="flex items-center gap-3 bg-[#FAF9FD] px-3.5 py-1.5 rounded-full border border-[#DDD6F3]">
                        <span class="text-xs font-bold text-[#1E1B4B] flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-user text-[#6D28D9] text-base"></i>
                            <?= htmlspecialchars($currentUser['name']) ?>
                            <?php if ($isAdmin): ?>
                                <span class="bg-purple-100 text-[#6D28D9] border border-purple-200 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-crown text-[9px] mr-1"></i> Admin
                                </span>
                            <?php elseif ($isVolunteer): ?>
                                <span class="bg-blue-100 text-blue-700 border border-blue-200 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-hand-holding-heart text-[9px] mr-1"></i> Volunteer
                                </span>
                            <?php else: ?>
                                <span class="bg-emerald-100 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-user text-[9px] mr-1"></i> Adopter
                                </span>
                            <?php endif; ?>
                        </span>
                        <a href="logout.php" class="text-xs font-bold text-red-600 hover:bg-red-50 px-2.5 py-1 rounded-md transition-colors">
                            Logout
                        </a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-xs font-bold text-[#6D28D9] hover:bg-[#EDE9FE] px-4 py-2 rounded-xl transition-colors">Log In</a>
                    <a href="register.php" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs px-4 py-2 rounded-xl shadow-md transition-all">Register to Adopt</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- SYSTEM ALERTS & NOTIFICATIONS -->
    <div class="max-w-7xl mx-auto px-6 pt-6">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'admin_only'): ?>
            <div class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 p-4 flex items-center gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 text-lg flex-shrink-0">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm">Administrative Permission Required</h4>
                    <p class="text-xs text-amber-700 mt-0.5">
                        Only shelter administrators are authorized to add, edit, or delete pet records. Volunteers and users have view and inquiry access.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="mb-6 rounded-2xl bg-green-50 border border-green-200 text-green-700 p-4 flex items-center gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center text-green-600 text-lg flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <p class="text-sm font-semibold">Pet record deleted successfully.</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['applied'])): ?>
            <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 flex items-center gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 text-lg flex-shrink-0">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm">Adoption Application Submitted!</h4>
                    <p class="text-xs text-emerald-700 mt-0.5">
                        Thank you for applying to adopt <strong><?= htmlspecialchars($_GET['applied']) ?></strong>! You have been placed into the adoption waiting queue. Track your position below.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['queue_cancelled'])): ?>
            <div class="mb-6 rounded-2xl bg-blue-50 border border-blue-200 text-blue-800 p-4 flex items-center gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 text-lg flex-shrink-0">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <p class="text-sm font-semibold">Your waiting queue spot has been cancelled. Applicants behind you have moved up.</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['queue_updated'])): ?>
            <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 flex items-center gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 text-lg flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm">Waiting Queue Updated</h4>
                    <p class="text-xs text-emerald-700 mt-0.5">
                        <?php if ($_GET['queue_updated'] === 'approved'): ?>
                            Application approved! The pet's status has been updated to Adopted.
                            <?php if (!empty($_GET['cert_id'])): ?>
                                <a href="adoption-certificate.php?id=<?= (int)$_GET['cert_id'] ?>" target="_blank" class="inline-flex items-center gap-1.5 ml-2 font-black underline text-emerald-900 hover:text-black">
                                    <i class="fa-solid fa-award"></i> View & Print Official Certificate &rarr;
                                </a>
                            <?php endif; ?>
                        <?php elseif ($_GET['queue_updated'] === 'review'): ?>
                            Application set to Under Review.
                        <?php elseif ($_GET['queue_updated'] === 'rejected'): ?>
                            Application rejected / passed. The next applicant in line has moved up.
                        <?php else: ?>
                            Queue record updated successfully.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ROLE-SPECIFIC DASHBOARD SEPARATION -->

    <?php if ($isAdmin): ?>
        <!-- ============================================================ -->
        <!-- 1. ADMIN DASHBOARD VIEW                                      -->
        <!-- ============================================================ -->
        <section class="max-w-7xl mx-auto px-6 pt-6 pb-10">
            <div class="bg-gradient-to-r from-[#2E1065] via-[#4C1D95] to-[#6D28D9] rounded-3xl p-8 sm:p-10 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10 max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-purple-200 text-xs font-bold uppercase tracking-wider border border-white/10">
                            <i class="fa-solid fa-crown text-amber-300"></i> Administrator Workspace
                        </div>
                        <?php if ($totalInAllQueues > 0): ?>
                            <a href="#queue-section" class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-400/25 border border-amber-300/40 text-amber-200 text-xs font-black backdrop-blur-sm animate-pulse hover:bg-amber-400/40 transition-colors">
                                <i class="fa-solid fa-bell text-amber-300"></i> <?= $totalInAllQueues ?> applicant<?= $totalInAllQueues === 1 ? '' : 's' ?> waiting in Queue &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-3">
                        Admin Management Dashboard
                    </h1>
                    <p class="text-purple-100 text-sm leading-relaxed mb-6">
                        Full administrative control: add new animals, manage shelter records, monitor fosters, and review adoption readiness.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <!-- ONLY ADMIN CAN ADD PET -->
                        <a href="add-pet.php" class="bg-white text-[#6D28D9] hover:bg-purple-50 px-6 py-3 rounded-xl font-bold text-sm shadow-lg transition-all flex items-center gap-2">
                            <i class="fa-solid fa-plus text-[#6D28D9]"></i> Add New Pet
                        </a>
                        <a href="#pets-table" class="bg-white/10 hover:bg-white/20 text-white px-5 py-3 rounded-xl font-bold text-sm backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-table-list"></i> Manage Records
                        </a>
                        <button type="button" onclick="togglePawcareChat()" class="bg-white/10 hover:bg-white/20 text-white px-5 py-3 rounded-xl font-bold text-sm backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-comments"></i> User Chat Desk
                        </button>
                        <a href="#queue-section" class="bg-white/10 hover:bg-white/20 text-white px-5 py-3 rounded-xl font-bold text-sm backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-list-ol"></i> Waiting Queue (<?= $totalInAllQueues ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Admin KPI Stat Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Rescues</span>
                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-sm">
                            <i class="fa-solid fa-paw"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-[#1E1B4B]"><?= $stats['total'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">All animals recorded in database</p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Available</span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-heart-circle-check"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-emerald-600"><?= $stats['available'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">Ready for adoption matching</p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">In Foster Care</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-house-chimney-window"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-amber-600"><?= $stats['fostered'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">Placed with temporary foster homes</p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Adopted</span>
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-shield-heart"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-indigo-600"><?= $stats['adopted'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">Happily placed with forever families</p>
                </div>
            </div>

            <!-- ADMIN MODULES & QUICK ACTIONS -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mt-8">
                <a href="foster-tracking.php" class="p-4 bg-white rounded-2xl border border-[#F2EEFB] shadow-sm hover:shadow-md hover:border-purple-200 transition-all flex flex-col items-center text-center group">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-house-chimney-heart"></i>
                    </div>
                    <span class="text-xs font-bold text-[#1E1B4B]">Foster Care</span>
                    <span class="text-[10px] text-gray-400 mt-0.5">Assignments & Homes</span>
                </a>

                <a href="medical-records.php" class="p-4 bg-white rounded-2xl border border-[#F2EEFB] shadow-sm hover:shadow-md hover:border-purple-200 transition-all flex flex-col items-center text-center group">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-notes-medical"></i>
                    </div>
                    <span class="text-xs font-bold text-[#1E1B4B]">Medical Logs</span>
                    <span class="text-[10px] text-gray-400 mt-0.5">Vaccines & Vet Visits</span>
                </a>

                <a href="notifications.php" class="p-4 bg-white rounded-2xl border border-[#F2EEFB] shadow-sm hover:shadow-md hover:border-purple-200 transition-all flex flex-col items-center text-center group">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <span class="text-xs font-bold text-[#1E1B4B]">Health Reminders</span>
                    <span class="text-[10px] text-gray-400 mt-0.5">Due Checkups</span>
                </a>

                <a href="activity-logs.php" class="p-4 bg-white rounded-2xl border border-[#F2EEFB] shadow-sm hover:shadow-md hover:border-purple-200 transition-all flex flex-col items-center text-center group">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <span class="text-xs font-bold text-[#1E1B4B]">Activity Logs</span>
                    <span class="text-[10px] text-gray-400 mt-0.5">Audit Trail</span>
                </a>

                <a href="reports.php" class="p-4 bg-white rounded-2xl border border-[#F2EEFB] shadow-sm hover:shadow-md hover:border-purple-200 transition-all flex flex-col items-center text-center group">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <span class="text-xs font-bold text-[#1E1B4B]">Reports</span>
                    <span class="text-[10px] text-gray-400 mt-0.5">Statistics & Export</span>
                </a>
            </div>

            <!-- ADMIN WAITING QUEUE MANAGER -->
            <div id="queue-section" class="mt-8 bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-[#F2EEFB]">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-100 text-[#6D28D9] text-xs font-bold uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-list-ol"></i> Adoption Application Queue
                        </div>
                        <h2 class="text-2xl font-extrabold text-[#1E1B4B]">Waiting Queue Management</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Review adoption applicants in queue. Approve adoptions or pass to the next applicant in line.</p>
                    </div>
                    <span class="px-4 py-2 bg-purple-50 text-[#6D28D9] font-bold text-xs rounded-xl border border-purple-200 self-start sm:self-auto">
                        Total Waiting: <?= $totalInAllQueues ?> applicant<?= $totalInAllQueues === 1 ? '' : 's' ?>
                    </span>
                </div>

                <?php if (empty($adminQueues)): ?>
                    <div class="p-8 text-center bg-[#FAF9FD] rounded-2xl border border-dashed border-[#DDD6F3] text-gray-400">
                        <i class="fa-solid fa-inbox text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-xs font-medium">No active applicants currently in the waiting queue.</p>
                        <p class="text-[11px] text-gray-400 mt-1">When users submit adoption applications, they will appear here in queue order (#1, #2, etc.).</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($adminQueues as $qPet): ?>
                            <div class="p-5 bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3]">
                                <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars(!empty($qPet['image_url']) ? $qPet['image_url'] : Pet::getDefaultImage($qPet['species_name'])) ?>" 
                                             alt="<?= htmlspecialchars($qPet['pet_name']) ?>"
                                             class="w-12 h-12 rounded-xl object-cover shadow-sm border border-purple-100">
                                        <div>
                                            <h3 class="font-bold text-sm text-[#1E1B4B]"><?= htmlspecialchars($qPet['pet_name']) ?></h3>
                                            <span class="text-xs text-gray-500"><?= htmlspecialchars($qPet['species_name']) ?> • <?= htmlspecialchars($qPet['breed']) ?></span>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-[#6D28D9] border border-purple-200">
                                        <?= count($qPet['applicants']) ?> in queue
                                    </span>
                                </div>

                                <div class="mt-4 divide-y divide-gray-100 space-y-3">
                                    <?php foreach ($qPet['applicants'] as $applicant): ?>
                                        <div class="pt-3 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full <?= $applicant['position'] === 1 ? 'bg-amber-100 text-amber-700 border border-amber-300' : 'bg-gray-100 text-gray-600' ?> flex items-center justify-center text-xs font-extrabold flex-shrink-0 mt-0.5">
                                                    #<?= $applicant['position'] ?>
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-bold text-xs text-[#1E1B4B]"><?= htmlspecialchars($applicant['adopter_name']) ?></span>
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $applicant['app_status'] === 'In Review' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' ?>">
                                                            <?= htmlspecialchars($applicant['app_status']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-500 mt-0.5">
                                                        <i class="fa-solid fa-envelope mr-1 text-gray-400"></i><?= htmlspecialchars($applicant['email']) ?>
                                                        <span class="mx-1">•</span>
                                                        <i class="fa-solid fa-phone mr-1 text-gray-400"></i><?= htmlspecialchars($applicant['phone_number'] ?: 'N/A') ?>
                                                        <span class="mx-1">•</span>
                                                        Applied: <?= htmlspecialchars($applicant['application_date']) ?>
                                                    </p>
                                                    <?php if (!empty($applicant['notes'])): ?>
                                                        <p class="text-[11px] text-gray-600 italic bg-white p-2 rounded-lg border border-gray-100 mt-1 max-w-lg">
                                                            "<?= htmlspecialchars($applicant['notes']) ?>"
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 self-end md:self-center">
                                                <!-- Approve button -->
                                                <form method="POST" action="queue-action.php" onsubmit="return confirm('Approve adoption for <?= htmlspecialchars(addslashes($applicant['adopter_name'])) ?>? This marks the pet as Adopted.');">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="application_id" value="<?= $applicant['application_id'] ?>">
                                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-1.5 rounded-lg shadow-sm transition-colors flex items-center gap-1">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>

                                                <!-- In Review button -->
                                                <?php if ($applicant['app_status'] !== 'In Review'): ?>
                                                    <form method="POST" action="queue-action.php">
                                                        <input type="hidden" name="action" value="in_review">
                                                        <input type="hidden" name="application_id" value="<?= $applicant['application_id'] ?>">
                                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow-sm transition-colors flex items-center gap-1">
                                                            <i class="fa-solid fa-hourglass-half"></i> Review
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Reject / Pass to Next button -->
                                                <form method="POST" action="queue-action.php" onsubmit="return confirm('Pass / reject this application? The next applicant in line will automatically move up.');">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="application_id" value="<?= $applicant['application_id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:bg-red-50 border border-red-200 font-bold text-xs px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                                                        <i class="fa-solid fa-xmark"></i> Pass
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    <?php elseif ($isVolunteer): ?>
        <!-- ============================================================ -->
        <!-- 2. VOLUNTEER DASHBOARD VIEW                                  -->
        <!-- ============================================================ -->
        <section class="max-w-7xl mx-auto px-6 pt-6 pb-10">
            <div class="bg-gradient-to-r from-[#1E293B] via-[#0F766E] to-[#0D9488] rounded-3xl p-8 sm:p-10 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10 max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-teal-100 text-xs font-bold uppercase tracking-wider border border-white/10">
                            <i class="fa-solid fa-hand-holding-heart text-teal-300"></i> Volunteer Care Portal
                        </div>
                        <?php if ($remindersCount > 0): ?>
                            <a href="notifications.php" class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-400/25 border border-amber-300/40 text-amber-200 text-xs font-black backdrop-blur-sm animate-pulse hover:bg-amber-400/40 transition-colors">
                                <i class="fa-solid fa-bell text-amber-300"></i> <?= $remindersCount ?> Health Reminder<?= $remindersCount === 1 ? '' : 's' ?> Due &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-3">
                        Welcome back, <?= htmlspecialchars($currentUser['name']) ?>!
                    </h1>
                    <p class="text-teal-100 text-sm leading-relaxed mb-6">
                        Track animal intake dates, foster care statuses, and assist adopters. Pet additions and deletions are restricted to shelter administrators.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="#pets-table" class="bg-white text-teal-800 hover:bg-teal-50 px-5 py-2.5 rounded-xl font-bold text-xs shadow-lg transition-all flex items-center gap-2">
                            <i class="fa-solid fa-paw text-teal-700"></i> Browse Animal Roster
                        </a>
                        <a href="foster-tracking.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-xl font-bold text-xs backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-house-chimney-heart text-amber-300"></i> Foster Tracking
                        </a>
                        <a href="medical-records.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-xl font-bold text-xs backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-notes-medical text-cyan-300"></i> Medical Logs
                        </a>
                        <a href="notifications.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-xl font-bold text-xs backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-bell text-rose-300"></i> Health Reminders
                        </a>
                        <button type="button" onclick="togglePawcareChat()" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2.5 rounded-xl font-bold text-xs backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-comments"></i> Chat with Admin
                        </button>
                    </div>
                </div>
            </div>

            <!-- Volunteer Stat Cards & Guidelines -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-8">
                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Animals In Shelter Care</span>
                        <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-paw"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-teal-700"><?= $stats['total'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">Track health logs and daily care</p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Available for Adoption</span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-heart-circle-check"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-emerald-600"><?= $stats['available'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">Ready for adopters to meet</p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">In Foster Placement</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-house-chimney-window"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-amber-600"><?= $stats['fostered'] ?></div>
                    <p class="text-[11px] text-gray-500 mt-1">Currently staying with foster families</p>
                </div>
            </div>

            <!-- Volunteer Notice -->
            <div class="mt-6 bg-teal-50/70 border border-teal-200 rounded-2xl p-4 flex items-center gap-3">
                <i class="fa-solid fa-circle-info text-teal-600 text-lg"></i>
                <p class="text-xs text-teal-800">
                    <strong>Volunteer Reminder:</strong> To register a newly rescued pet or modify an intake date, please coordinate with the Shelter Administrator.
                </p>
            </div>
        </section>

    <?php elseif ($currentUser): ?>
        <!-- ============================================================ -->
        <!-- 3. ADOPTER / REGULAR USER DASHBOARD VIEW                     -->
        <!-- ============================================================ -->
        <section class="max-w-7xl mx-auto px-6 pt-6 pb-10">
            <div class="bg-gradient-to-r from-[#1E1B4B] via-[#4338CA] to-[#6366F1] rounded-3xl p-8 sm:p-10 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10 max-w-3xl">
                    <?php $activeUserApp = !empty($myQueueApplications) ? $myQueueApplications[0] : null; ?>
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-indigo-100 text-xs font-bold uppercase tracking-wider border border-white/10">
                            <i class="fa-solid fa-heart text-pink-300"></i> Adopter & Supporter Portal
                        </div>
                        <?php if ($activeUserApp && in_array($activeUserApp['status'], ['Pending', 'In Review'])): ?>
                            <a href="#queue-section" class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-400/25 border border-emerald-300/40 text-emerald-200 text-xs font-black backdrop-blur-sm hover:bg-emerald-400/40 transition-colors">
                                <i class="fa-solid fa-award text-yellow-300"></i> Application for <?= htmlspecialchars($activeUserApp['pet_name']) ?>: <?= $activeUserApp['queue_position'] === 1 ? '🏆 #1 in Line' : '#' . $activeUserApp['queue_position'] . ' in line' ?> &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-3">
                        Welcome, <?= htmlspecialchars($currentUser['name']) ?>!
                    </h1>
                    <p class="text-indigo-100 text-sm leading-relaxed mb-6">
                        Find your future companion. Browse all rescued dogs and cats waiting for a loving home and submit your adoption inquiry directly.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="#pets-table" class="bg-white text-indigo-900 hover:bg-indigo-50 px-6 py-3 rounded-xl font-bold text-sm shadow-lg transition-all flex items-center gap-2">
                            <i class="fa-solid fa-magnifying-glass"></i> Explore Adoptable Pets
                        </a>
                        <button type="button" onclick="togglePawcareChat()" class="bg-white/10 hover:bg-white/20 text-white px-5 py-3 rounded-xl font-bold text-sm backdrop-blur-sm transition-all flex items-center gap-2">
                            <i class="fa-solid fa-comments"></i> Inquire via Chat
                        </button>
                    </div>
                </div>
            </div>

            <!-- Adopter Highlight Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-8">
                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-lg mb-3">
                        <i class="fa-solid fa-shield-cat"></i>
                    </div>
                    <h3 class="font-bold text-base text-[#1E1B4B] mb-1">Adopt a Pet</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        <?= $stats['available'] ?> pets are currently ready for adoption. Click "Adopt Me" on any available pet to apply.
                    </p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg mb-3">
                        <i class="fa-solid fa-house-chimney-heart"></i>
                    </div>
                    <h3 class="font-bold text-base text-[#1E1B4B] mb-1">Foster Support</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Learn about fostering animals in transition and providing safe temporary shelter before adoption.
                    </p>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg mb-3">
                        <i class="fa-solid fa-notes-medical"></i>
                    </div>
                    <h3 class="font-bold text-base text-[#1E1B4B] mb-1">Health & Intake</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        All our rescue animals receive health tracking, vaccinations, and proper shelter checkups.
                    </p>
                </div>
            </div>

            <!-- ADOPTER WAITING QUEUE TRACKER -->
            <div id="queue-section" class="mt-8 bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-[#F2EEFB]">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-100 text-[#6D28D9] text-xs font-bold uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-clock-rotate-left"></i> My Applications & Queue
                        </div>
                        <h2 class="text-2xl font-extrabold text-[#1E1B4B]">Adoption Waiting Queue</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Live tracking of your adoption application status and place in line.</p>
                    </div>
                    <?php if (!empty($myQueueApplications)): ?>
                        <span class="px-3.5 py-1.5 bg-purple-50 text-[#6D28D9] font-bold text-xs rounded-xl border border-purple-200 self-start sm:self-auto">
                            <?= count($myQueueApplications) ?> Active Submission<?= count($myQueueApplications) === 1 ? '' : 's' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($myQueueApplications)): ?>
                    <div class="p-8 text-center bg-[#FAF9FD] rounded-2xl border border-dashed border-[#DDD6F3]">
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-xl mx-auto mb-3">
                            <i class="fa-solid fa-heart-circle-plus"></i>
                        </div>
                        <h3 class="font-bold text-sm text-[#1E1B4B]">You have no active waiting queue entries</h3>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            Browse the available animals in our directory below. Click <strong>Adopt Me</strong> to join the adoption queue for your favorite pet!
                        </p>
                        <a href="#pets-table" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-xl bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs shadow-md transition-all">
                            <i class="fa-solid fa-paw"></i> Find Pets to Adopt
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($myQueueApplications as $myApp): ?>
                            <div class="p-5 bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3] flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= htmlspecialchars(!empty($myApp['image_url']) ? $myApp['image_url'] : Pet::getDefaultImage($myApp['species_name'])) ?>" 
                                                 alt="<?= htmlspecialchars($myApp['pet_name']) ?>"
                                                 class="w-14 h-14 rounded-2xl object-cover shadow-sm border border-purple-100 flex-shrink-0">
                                            <div>
                                                <h4 class="font-bold text-base text-[#1E1B4B] leading-snug"><?= htmlspecialchars($myApp['pet_name']) ?></h4>
                                                <span class="text-xs text-gray-500"><?= htmlspecialchars($myApp['species_name']) ?> • <?= htmlspecialchars($myApp['breed']) ?></span>
                                            </div>
                                        </div>

                                        <?php if ($myApp['status'] === 'Approved'): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                                                <i class="fa-solid fa-circle-check"></i> Approved!
                                            </span>
                                        <?php elseif ($myApp['status'] === 'In Review'): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-blue-100 text-blue-800 border border-blue-300 flex items-center gap-1">
                                                <i class="fa-solid fa-hourglass-half"></i> Under Review
                                            </span>
                                        <?php elseif ($myApp['status'] === 'Cancelled'): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Cancelled</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                                <i class="fa-solid fa-clock"></i> In Queue
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Queue Position Highlight -->
                                    <?php if ($myApp['queue_position'] !== null): ?>
                                        <div class="p-3.5 bg-white rounded-xl border border-purple-100 mb-3 flex items-center justify-between">
                                            <div>
                                                <span class="text-[10px] font-bold uppercase text-gray-400 tracking-wider block">Your Queue Position</span>
                                                <span class="text-lg font-black <?= $myApp['queue_position'] === 1 ? 'text-emerald-600' : 'text-[#6D28D9]' ?>">
                                                    <?= $myApp['queue_position'] === 1 ? '🏆 #1 in Line' : '#' . $myApp['queue_position'] . ' in Line' ?>
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-[10px] text-gray-400 block">Queue Length</span>
                                                <span class="text-xs font-bold text-gray-700"><?= $myApp['total_in_queue'] ?> applicant<?= $myApp['total_in_queue'] === 1 ? '' : 's' ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-[11px] text-gray-500 space-y-1 mb-3">
                                        <p><i class="fa-regular fa-calendar mr-1.5 text-gray-400"></i>Submitted on <?= htmlspecialchars($myApp['application_date']) ?></p>
                                        <?php if (!empty($myApp['notes'])): ?>
                                            <p class="italic text-gray-600 bg-white p-2 rounded-lg border border-gray-100">"<?= htmlspecialchars($myApp['notes']) ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-gray-200/60 flex items-center justify-between">
                                    <span class="text-[10px] text-gray-400">
                                        <?= $myApp['status'] === 'Approved' ? '🎉 Adoption completed & approved!' : ($myApp['queue_position'] === 1 ? '✨ You are next for review!' : 'Waiting for prior reviews') ?>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <?php if ($myApp['status'] === 'Approved'): ?>
                                            <a href="adoption-certificate.php?id=<?= (int)$myApp['application_id'] ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-sm transition-all hover:scale-105">
                                                <i class="fa-solid fa-award"></i> Print Certificate
                                            </a>
                                        <?php elseif (in_array($myApp['status'], ['Pending', 'In Review'])): ?>
                                            <a href="queue-action.php?action=cancel&application_id=<?= $myApp['application_id'] ?>" 
                                               onclick="return confirm('Cancel your waiting queue application for <?= htmlspecialchars(addslashes($myApp['pet_name'])) ?>?');"
                                               class="text-xs font-bold text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg border border-red-200 transition-colors">
                                                Cancel Queue Spot
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>
        <!-- ============================================================ -->
        <!-- 4. GUEST / VISITOR HERO SECTION                              -->
        <!-- ============================================================ -->
        <!-- QUICK DEMO ACCOUNTS BAR FOR EVALUATORS / TESTERS -->
        <div class="max-w-7xl mx-auto px-6 pt-6">
            <div class="bg-white/90 backdrop-blur-md border border-purple-200/80 rounded-2xl p-3.5 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-md">
                <div class="flex items-center gap-2.5 text-xs font-bold text-gray-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <span class="text-[#6D28D9] font-black uppercase tracking-wider text-[11px] flex items-center gap-1">
                        <i class="fa-solid fa-bolt text-amber-500"></i> Quick Demo Logins:
                    </span>
                    <span class="hidden md:inline text-gray-400 font-normal">Click to 1-click test any role</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="login.php?demo=admin" class="px-3.5 py-1.5 rounded-xl bg-purple-100 hover:bg-purple-200 text-[#6D28D9] font-extrabold text-xs transition-colors flex items-center gap-1.5 shadow-xs">
                        👑 Admin (Kristine)
                    </a>
                    <a href="login.php?demo=volunteer" class="px-3.5 py-1.5 rounded-xl bg-teal-100 hover:bg-teal-200 text-teal-800 font-extrabold text-xs transition-colors flex items-center gap-1.5 shadow-xs">
                        🤝 Volunteer (Ana)
                    </a>
                    <a href="login.php?demo=adopter" class="px-3.5 py-1.5 rounded-xl bg-blue-100 hover:bg-blue-200 text-blue-800 font-extrabold text-xs transition-colors flex items-center gap-1.5 shadow-xs">
                        🐾 Adopter (Kaye)
                    </a>
                </div>
            </div>
        </div>

        <section class="relative max-w-7xl mx-auto px-6 pt-10 pb-16 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#EDE9FE] text-[#6D28D9] text-xs font-extrabold uppercase tracking-wider mb-4 border border-purple-200">
                    <i class="fa-solid fa-heart text-pink-500"></i> Rescue, Care & Foster Network
                </span>
                <h1 class="text-4xl sm:text-5xl font-black text-[#1E1B4B] leading-tight tracking-tight mb-4">
                    Find Your Forever Pet & Track Foster Care
                </h1>
                <p class="text-gray-600 text-base leading-relaxed mb-6 max-w-lg">
                    Browse lovingly rescued animals, track real-time waiting queue positions, review clinical medical history, and support foster homes.
                </p>

                <!-- Dynamic Live Shelter Pulse Counters -->
                <div class="grid grid-cols-3 gap-3 p-4 bg-white rounded-2xl border border-purple-100 shadow-md mb-8 max-w-lg">
                    <div class="text-center">
                        <div class="text-2xl font-black text-[#6D28D9]"><?= $stats['available'] ?></div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase">Ready to Adopt</div>
                    </div>
                    <div class="text-center border-x border-gray-100">
                        <div class="text-2xl font-black text-amber-500"><?= $stats['fostered'] ?></div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase">In Foster Care</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-black text-emerald-600"><?= $stats['adopted'] ?></div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase">Forever Homes</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <a href="#pets-table" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-7 py-3.5 rounded-2xl font-bold text-sm shadow-lg shadow-[#6D28D9]/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-paw"></i> Explore Pet Directory
                    </a>
                    <a href="register.php" class="bg-white border-2 border-[#DDD6F3] text-[#6D28D9] hover:bg-[#EDE9FE] px-7 py-3.5 rounded-2xl font-bold text-sm transition-all flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-user-plus"></i> Register to Adopt
                    </a>
                </div>
            </div>

            <!-- RIGHT: FEATURED PET SHOWCASE CARD -->
            <div class="relative flex justify-center">
                <div class="absolute w-80 h-80 sm:w-96 sm:h-96 bg-purple-200/50 rounded-full blur-3xl -z-10 top-1/2 -translate-y-1/2"></div>
                
                <?php if ($featuredPet): ?>
                    <div class="relative bg-white rounded-3xl p-5 shadow-2xl border border-purple-100 max-w-sm w-full hover:-translate-y-1 transition-transform duration-300">
                        <div class="relative w-full h-56 rounded-2xl overflow-hidden mb-4 bg-purple-50 shadow-inner group">
                            <img src="<?= htmlspecialchars(!empty($featuredPet['image_url']) ? $featuredPet['image_url'] : Pet::getDefaultImage($featuredPet['species_name'])) ?>" 
                                 alt="<?= htmlspecialchars($featuredPet['name']) ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            
                            <!-- Floating Badges -->
                            <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-black text-[#6D28D9] shadow-md flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                Featured Rescue
                            </div>

                            <div class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded-xl text-white text-[10px] font-bold">
                                <?= (int)$featuredPet['age'] ?> yrs old
                            </div>
                        </div>

                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div>
                                <h3 class="text-xl font-extrabold text-[#1E1B4B]"><?= htmlspecialchars($featuredPet['name']) ?></h3>
                                <p class="text-xs text-gray-500 font-medium"><?= htmlspecialchars($featuredPet['breed']) ?> • <?= htmlspecialchars($featuredPet['species_name']) ?></p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Available
                            </span>
                        </div>

                        <p class="text-xs text-gray-600 mb-4 leading-relaxed line-clamp-2">
                            Healthy, friendly, and medically vetted rescue looking for a safe, loving companion or foster family.
                        </p>

                        <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
                            <button type="button" 
                                    onclick='openPetModal(<?= json_encode($featuredPet) ?>)'
                                    class="flex-1 bg-purple-50 hover:bg-purple-100 text-[#6D28D9] py-2.5 rounded-xl font-bold text-xs transition-colors border border-purple-200 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-eye"></i> View Profile
                            </button>
                            <a href="login.php" class="flex-1 bg-[#6D28D9] hover:bg-[#5B21B6] text-white py-2.5 rounded-xl font-bold text-xs transition-colors shadow-md flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-heart"></i> Adopt Me
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="relative bg-white p-6 rounded-3xl shadow-xl border border-[#F2EEFB] max-w-sm text-center">
                        <div class="w-full h-48 bg-[#F3E8FF] rounded-2xl flex items-center justify-center mb-4 overflow-hidden relative">
                            <i class="fa-solid fa-shield-cat text-7xl text-[#6D28D9]/40"></i>
                        </div>
                        <h3 class="text-lg font-bold text-[#1E1B4B]">Adoption & Foster Hub</h3>
                        <p class="text-xs text-gray-500 mt-1">Register today to start an adoption request or view shelter updates.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 10-FEATURE TRUST / CAPABILITY RIBBON -->
        <section class="max-w-7xl mx-auto px-6 pb-12">
            <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-[#1E1B4B] text-white rounded-3xl p-6 sm:p-8 shadow-xl">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <div>
                        <span class="text-[11px] font-black uppercase tracking-widest text-purple-300">System Capabilities</span>
                        <h3 class="text-xl sm:text-2xl font-black">All-In-One Rescue & Shelter Management Platform</h3>
                    </div>
                    <span class="px-3.5 py-1.5 rounded-full bg-white/10 text-xs font-bold border border-white/20 self-start md:self-auto">
                        ✓ 10 Complete Modules Verified
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-xs font-semibold">
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center gap-2.5">
                        <i class="fa-solid fa-list-ol text-purple-300 text-sm"></i>
                        <span>Waiting Queue</span>
                    </div>
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center gap-2.5">
                        <i class="fa-solid fa-notes-medical text-cyan-300 text-sm"></i>
                        <span>Medical Logs</span>
                    </div>
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center gap-2.5">
                        <i class="fa-solid fa-house-chimney-heart text-amber-300 text-sm"></i>
                        <span>Foster Tracking</span>
                    </div>
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center gap-2.5">
                        <i class="fa-solid fa-bell text-rose-300 text-sm"></i>
                        <span>Health Reminders</span>
                    </div>
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center gap-2.5">
                        <i class="fa-solid fa-comments text-emerald-300 text-sm"></i>
                        <span>Live Admin Chat</span>
                    </div>
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center gap-2.5">
                        <i class="fa-solid fa-chart-line text-blue-300 text-sm"></i>
                        <span>Reports Export</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- BENTO CARDS SECTION FOR GUESTS -->
        <section id="services" class="bg-[#5B21B6] text-white py-16 px-6 relative overflow-hidden">
            <div class="max-w-7xl mx-auto text-center mb-12">
                <h2 class="text-3xl font-extrabold tracking-tight mb-3">Our Core Services</h2>
                <p class="text-purple-200 text-sm max-w-xl mx-auto">
                    Connecting lovingly rescued animals with dedicated adopters and structured foster programs.
                </p>
            </div>

            <div class="max-w-7xl mx-auto grid md:grid-cols-3 gap-6">
                <div class="bg-white text-[#1E1B4B] p-6 rounded-2xl shadow-lg border border-purple-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-[#EDE9FE] text-[#6D28D9] rounded-xl flex items-center justify-center mb-4 text-xl">
                            <i class="fa-solid fa-paw"></i>
                        </div>
                        <h3 class="font-bold text-lg mb-2">Pet Adoption</h3>
                        <p class="text-xs text-gray-500 leading-relaxed mb-4">
                            Register an account to review available pets and submit digital adoption requests.
                        </p>
                    </div>
                    <a href="#pets-table" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1">
                        Browse Pets <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>

                <div class="bg-white text-[#1E1B4B] p-6 rounded-2xl shadow-lg border border-purple-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-[#EDE9FE] text-[#6D28D9] rounded-xl flex items-center justify-center mb-4 text-xl">
                            <i class="fa-solid fa-house-chimney-window"></i>
                        </div>
                        <h3 class="font-bold text-lg mb-2">Foster Care</h3>
                        <p class="text-xs text-gray-500 leading-relaxed mb-4">
                            Temporary homes help animals transition smoothly into permanent homes.
                        </p>
                    </div>
                    <a href="#pets-table" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1">
                        Track Fosters <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>

                <div class="bg-white text-[#1E1B4B] p-6 rounded-2xl shadow-lg border border-purple-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-[#EDE9FE] text-[#6D28D9] rounded-xl flex items-center justify-center mb-4 text-xl">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>
                        <h3 class="font-bold text-lg mb-2">Health Logs</h3>
                        <p class="text-xs text-gray-500 leading-relaxed mb-4">
                            Every pet profile tracks essential information like age, intake dates, and current availability.
                        </p>
                    </div>
                    <a href="#pets-table" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1">
                        View Records <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- INTERACTIVE ADOPTION FAQ & SHELTER VISITING GUIDELINES -->
        <section id="faq" class="py-16 px-6 bg-white border-y border-[#F2EEFB]">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-10">
                    <span class="text-xs font-black uppercase tracking-widest text-[#6D28D9] bg-purple-100 px-3.5 py-1 rounded-full">
                        Visitor Knowledge Base
                    </span>
                    <h2 class="text-3xl font-extrabold text-[#1E1B4B] mt-3">Frequently Asked Questions & Visiting Guidelines</h2>
                    <p class="text-xs text-gray-500 max-w-xl mx-auto mt-2">
                        Everything you need to know about meeting our rescue animals, adoption eligibility, and visiting the shelter sanctuary.
                    </p>
                </div>

                <!-- Visiting Hours & Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="p-5 bg-[#FAF9FD] rounded-2xl border border-purple-100 shadow-sm flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-[#6D28D9] flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-[#1E1B4B]">Visiting Hours</h4>
                            <p class="text-[11px] text-gray-500 mt-0.5">Mon – Sat: 9:00 AM – 5:00 PM</p>
                            <span class="text-[10px] text-emerald-600 font-bold">Open for walk-in meetups</span>
                        </div>
                    </div>

                    <div class="p-5 bg-[#FAF9FD] rounded-2xl border border-purple-100 shadow-sm flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-[#6D28D9] flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-[#1E1B4B]">Shelter Sanctuary</h4>
                            <p class="text-[11px] text-gray-500 mt-0.5">88 Haven Way, Cebu City</p>
                            <span class="text-[10px] text-[#6D28D9] font-bold">Free visitor parking on-site</span>
                        </div>
                    </div>

                    <div class="p-5 bg-[#FAF9FD] rounded-2xl border border-purple-100 shadow-sm flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-[#6D28D9] flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-[#1E1B4B]">Health Guarantee</h4>
                            <p class="text-[11px] text-gray-500 mt-0.5">Fully vaccinated & vetted</p>
                            <span class="text-[10px] text-emerald-600 font-bold">Includes Health Passport</span>
                        </div>
                    </div>
                </div>

                <!-- Interactive Accordion List -->
                <div class="space-y-3" id="faqAccordion">
                    <!-- FAQ 1 -->
                    <div class="bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3] overflow-hidden shadow-xs">
                        <button type="button" onclick="toggleFaq(this)" class="w-full p-4 text-left flex items-center justify-between font-bold text-xs sm:text-sm text-[#1E1B4B] hover:text-[#6D28D9] transition-colors focus:outline-none">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-circle-question text-[#6D28D9]"></i>
                                What are the requirements to adopt a companion animal?
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 pb-4 pt-1 text-xs text-gray-600 leading-relaxed border-t border-purple-100/60">
                            To adopt from PawCare, you need an account, a valid government ID, and completion of our brief online screening questionnaire. We review your household environment to ensure the animal's needs match your living arrangement.
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3] overflow-hidden shadow-xs">
                        <button type="button" onclick="toggleFaq(this)" class="w-full p-4 text-left flex items-center justify-between font-bold text-xs sm:text-sm text-[#1E1B4B] hover:text-[#6D28D9] transition-colors focus:outline-none">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-hand-holding-dollar text-emerald-600"></i>
                                Is there an adoption fee?
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 pb-4 pt-1 text-xs text-gray-600 leading-relaxed border-t border-purple-100/60">
                            PawCare is a compassionate rescue shelter. We do not charge profit-making fees. Voluntary donations are welcomed to help subsidize initial vaccinations, veterinary anti-parasitic treatments, and dietary care for future rescues.
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3] overflow-hidden shadow-xs">
                        <button type="button" onclick="toggleFaq(this)" class="w-full p-4 text-left flex items-center justify-between font-bold text-xs sm:text-sm text-[#1E1B4B] hover:text-[#6D28D9] transition-colors focus:outline-none">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-users text-blue-600"></i>
                                How does the Waiting Queue system work?
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 pb-4 pt-1 text-xs text-gray-600 leading-relaxed border-t border-purple-100/60">
                            PawCare operates a transparent, fair First-In First-Out (FIFO) queue. When you click "Adopt Me", you are given a real-time queue position (e.g. #1 in line). If applicant #1 changes their mind or isn't approved, applicant #2 moves up automatically without any favoritism.
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3] overflow-hidden shadow-xs">
                        <button type="button" onclick="toggleFaq(this)" class="w-full p-4 text-left flex items-center justify-between font-bold text-xs sm:text-sm text-[#1E1B4B] hover:text-[#6D28D9] transition-colors focus:outline-none">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-house-chimney-heart text-amber-600"></i>
                                Can I foster a pet before committing to permanent adoption?
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 pb-4 pt-1 text-xs text-gray-600 leading-relaxed border-t border-purple-100/60">
                            Absolutely! Our foster-to-adopt program allows approved foster families to provide temporary shelter, helping timid or recuperating animals adjust to family living. You can reach out directly through our live chat to discuss foster placement.
                        </div>
                    </div>

                    <!-- FAQ 5 -->
                    <div class="bg-[#FAF9FD] rounded-2xl border border-[#DDD6F3] overflow-hidden shadow-xs">
                        <button type="button" onclick="toggleFaq(this)" class="w-full p-4 text-left flex items-center justify-between font-bold text-xs sm:text-sm text-[#1E1B4B] hover:text-[#6D28D9] transition-colors focus:outline-none">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-award text-purple-600"></i>
                                What documents do I receive upon adoption approval?
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 pb-4 pt-1 text-xs text-gray-600 leading-relaxed border-t border-purple-100/60">
                            Once your adoption is approved by our shelter coordinator, you receive an ornate, official <strong>Adoption Approval Certificate</strong> with unique registration code, as well as an official <strong>Veterinary Health & Vaccination Passport</strong> to present to your personal veterinarian.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- PET DIRECTORY TABLE SECTION -->
    <main id="pets-table" class="max-w-7xl mx-auto px-6 py-12">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-xs font-bold text-[#6D28D9] uppercase tracking-wider">
                    <?= $isAdmin ? 'Admin Pet Management' : ($isVolunteer ? 'Volunteer Animal Directory' : 'Rescue Directory') ?>
                </span>
                <h2 class="text-3xl font-extrabold text-[#1E1B4B]">
                    <?= $isAdmin ? 'Shelter Animal Records' : 'Available Rescues & Animals' ?>
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    <?= $isAdmin 
                        ? 'Add, edit, and manage all shelter animals and adoption availability.' 
                        : ($isVolunteer 
                            ? 'Volunteer view: Monitor animal care statuses and foster assignments.' 
                            : 'Explore all animals currently in our care.') ?>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 self-start md:self-auto">
                <a href="export-csv.php?type=pets" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
                <!-- ONLY THE ADMIN CAN ADD PET -->
                <?php if ($isAdmin): ?>
                    <a href="add-pet.php" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Add New Pet
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="bg-white rounded-2xl shadow-md p-4 mb-6 border border-[#F2EEFB]">
            <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-3">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
                           placeholder="Search by name, breed, gender, status, or species..."
                           class="w-full pl-11 pr-4 py-3 border border-[#DDD6F3] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#6D28D9] bg-[#FAF9FD]/50">
                </div>

                <div class="flex flex-wrap gap-2 items-center">
                    <select name="status" class="border border-[#DDD6F3] rounded-xl px-3 py-3 text-sm bg-[#FAF9FD]/50 focus:outline-none focus:ring-2 focus:ring-[#6D28D9]">
                        <option value="">All Statuses</option>
                        <option value="Available" <?= $statusFilter === 'Available' ? 'selected' : '' ?>>Available</option>
                        <option value="Fostered" <?= $statusFilter === 'Fostered' ? 'selected' : '' ?>>Fostered</option>
                        <option value="Adopted" <?= $statusFilter === 'Adopted' ? 'selected' : '' ?>>Adopted</option>
                    </select>

                    <button type="submit" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-6 py-3 rounded-xl font-bold text-sm shadow-md transition-all">
                        Search
                    </button>

                    <?php if ($keyword !== '' || $statusFilter !== ''): ?>
                        <a href="index.php" class="px-4 py-3 rounded-xl border border-[#6D28D9] text-[#6D28D9] hover:bg-[#EDE9FE] font-bold text-sm transition-all flex items-center justify-center">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Quick Status Filter Pills -->
            <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-gray-100 text-xs">
                <span class="text-gray-400 font-semibold mr-1">Status:</span>
                <a href="index.php<?= $speciesFilter ? '?species=' . urlencode($speciesFilter) : '' ?><?= $keyword ? ($speciesFilter ? '&' : '?') . 'q=' . urlencode($keyword) : '' ?>#pets-table" class="px-3 py-1 rounded-full font-bold transition-colors <?= $statusFilter === '' ? 'bg-[#6D28D9] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    All (<?= $stats['total'] ?>)
                </a>
                <a href="index.php?status=Available<?= $speciesFilter ? '&species=' . urlencode($speciesFilter) : '' ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?>#pets-table" class="px-3 py-1 rounded-full font-bold transition-colors <?= $statusFilter === 'Available' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                    Available (<?= $stats['available'] ?>)
                </a>
                <a href="index.php?status=Fostered<?= $speciesFilter ? '&species=' . urlencode($speciesFilter) : '' ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?>#pets-table" class="px-3 py-1 rounded-full font-bold transition-colors <?= $statusFilter === 'Fostered' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' ?>">
                    Fostered (<?= $stats['fostered'] ?>)
                </a>
                <a href="index.php?status=Adopted<?= $speciesFilter ? '&species=' . urlencode($speciesFilter) : '' ?><?= $keyword ? '&q=' . urlencode($keyword) : '' ?>#pets-table" class="px-3 py-1 rounded-full font-bold transition-colors <?= $statusFilter === 'Adopted' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' ?>">
                    Adopted (<?= $stats['adopted'] ?>)
                </a>
            </div>

            <!-- Species Filter Tabs (1-Click) -->
            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100 text-xs">
                <span class="text-gray-400 font-semibold mr-1 flex items-center gap-1">
                    <i class="fa-solid fa-paw text-[#6D28D9]"></i> Species:
                </span>
                <?php
                    $buildSpUrl = function($sp) use ($keyword, $statusFilter) {
                        $p = [];
                        if ($sp !== '') $p['species'] = $sp;
                        if ($statusFilter !== '') $p['status'] = $statusFilter;
                        if ($keyword !== '') $p['q'] = $keyword;
                        return 'index.php' . (!empty($p) ? '?' . http_build_query($p) : '') . '#pets-table';
                    };
                ?>
                <a href="<?= $buildSpUrl('') ?>" 
                   class="px-3 py-1.5 rounded-full font-bold transition-all <?= $speciesFilter === '' ? 'bg-[#6D28D9] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    All Species
                </a>
                <?php foreach ($allSpeciesList as $sp): ?>
                    <?php 
                        $spName = $sp['species_name'];
                        $isActive = strcasecmp($speciesFilter, $spName) === 0;
                        $icon = 'fa-solid fa-paw';
                        if (strcasecmp($spName, 'Dog') === 0) $icon = 'fa-solid fa-dog';
                        elseif (strcasecmp($spName, 'Cat') === 0) $icon = 'fa-solid fa-cat';
                        elseif (strcasecmp($spName, 'Rabbit') === 0) $icon = 'fa-solid fa-carrot';
                        elseif (strcasecmp($spName, 'Bird') === 0) $icon = 'fa-solid fa-dove';
                    ?>
                    <a href="<?= $buildSpUrl($spName) ?>" 
                       class="px-3 py-1.5 rounded-full font-bold transition-all flex items-center gap-1.5 <?= $isActive ? 'bg-[#6D28D9] text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-700 hover:bg-purple-50' ?>">
                        <i class="<?= $icon ?> text-xs"></i>
                        <span><?= htmlspecialchars($spName) ?></span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $isActive ? 'bg-white/20 text-white' : 'bg-purple-100 text-[#6D28D9]' ?>"><?= $sp['pet_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Table View -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-[#F2EEFB]">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-[#F3E8FF] text-[#1E1B4B] uppercase text-[11px] tracking-wider font-bold">
                        <tr>
                            <th class="px-5 py-4">Name</th>
                            <th class="px-5 py-4">Species</th>
                            <th class="px-5 py-4">Breed</th>
                            <th class="px-5 py-4">Age</th>
                            <th class="px-5 py-4">Gender</th>
                            <th class="px-5 py-4">Intake Date</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F2EEFB]">
                        <?php if (empty($pets)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                    <i class="fa-solid fa-folder-open text-3xl mb-2 block"></i>
                                    No pet records found matching your query.
                                </td>
                            </tr>
                        <?php else: foreach ($pets as $p): ?>
                            <tr class="hover:bg-[#FAF9FD] transition-colors">
                                <td class="px-5 py-4 font-bold text-[#1E1B4B]">
                                    <div class="flex items-center gap-3">
                                        <button type="button" 
                                                onclick='openPetModal(<?= json_encode($p) ?>)'
                                                title="Click to view photo"
                                                class="relative group flex-shrink-0 focus:outline-none">
                                            <img src="<?= htmlspecialchars(!empty($p['image_url']) ? $p['image_url'] : Pet::getDefaultImage($p['species_name'])) ?>" 
                                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                                 class="w-12 h-12 rounded-xl object-cover shadow-sm border border-purple-100 group-hover:scale-105 group-hover:ring-2 group-hover:ring-[#6D28D9] transition-all">
                                            <span class="absolute inset-0 bg-black/25 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs transition-opacity">
                                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            </span>
                                        </button>
                                        <div>
                                            <button type="button" 
                                                    onclick='openPetModal(<?= json_encode($p) ?>)'
                                                    class="text-left font-bold text-sm text-[#1E1B4B] hover:text-[#6D28D9] transition-colors block leading-tight">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </button>
                                            <span class="text-[11px] font-normal text-gray-500 block mt-0.5"><?= htmlspecialchars($p['breed']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-600"><?= htmlspecialchars($p['species_name']) ?></td>
                                <td class="px-5 py-4 text-gray-600"><?= htmlspecialchars($p['breed']) ?></td>
                                <td class="px-5 py-4 text-gray-600"><?= (int)$p['age'] ?> yrs</td>
                                <td class="px-5 py-4 text-gray-600"><?= htmlspecialchars($p['gender']) ?></td>
                                <td class="px-5 py-4 text-gray-600"><?= htmlspecialchars($p['intake_date']) ?></td>
                                <td class="px-5 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold inline-block <?= $statusBadge[$p['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                    <?php $petQCount = $queueCounts[$p['pet_id']] ?? 0; ?>
                                    <?php if ($petQCount > 0): ?>
                                        <div class="mt-1.5">
                                            <a href="#queue-section" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-[#6D28D9] border border-purple-200 inline-flex items-center gap-1 hover:bg-purple-100 transition-colors">
                                                <i class="fa-solid fa-users text-[9px]"></i> <?= $petQCount ?> in queue
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <?php if ($isAdmin): ?>
                                            <!-- ADMIN ACTIONS: EDIT, DELETE & ADOPTION CERTIFICATE -->
                                            <?php if ($p['status'] === 'Adopted'): ?>
                                                <a href="adoption-certificate.php?pet_id=<?= (int)$p['pet_id'] ?>" target="_blank"
                                                   class="text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2.5 py-1.5 rounded-lg font-bold text-xs transition-colors flex items-center gap-1"
                                                   title="View & Print Official Adoption Certificate">
                                                    <i class="fa-solid fa-award"></i> Certificate
                                                </a>
                                            <?php endif; ?>
                                            <a href="edit-pet.php?id=<?= (int)$p['pet_id'] ?>" 
                                               class="text-[#6D28D9] hover:bg-[#EDE9FE] px-2.5 py-1.5 rounded-lg font-bold text-xs transition-colors flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </a>
                                            <a href="delete-pet.php?id=<?= (int)$p['pet_id'] ?>"
                                               onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($p['name'])) ?> from the system?');"
                                               class="text-red-600 hover:bg-red-50 px-2.5 py-1.5 rounded-lg font-bold text-xs transition-colors flex items-center gap-1">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </a>

                                        <?php elseif ($isVolunteer): ?>
                                            <!-- VOLUNTEER ACTIONS: QUICK DETAILS VIEW -->
                                            <button type="button" 
                                                    onclick='openPetModal(<?= json_encode($p) ?>)'
                                                    class="text-teal-700 bg-teal-50 hover:bg-teal-100 border border-teal-200 px-3 py-1.5 rounded-lg font-bold text-xs transition-colors flex items-center gap-1.5">
                                                <i class="fa-solid fa-camera"></i> View Photo & Info
                                            </button>

                                        <?php else: ?>
                                            <!-- USER / ADOPTER / GUEST ACTIONS -->
                                            <button type="button" 
                                                    onclick='openPetModal(<?= json_encode($p) ?>)'
                                                    class="text-gray-600 hover:text-[#6D28D9] hover:bg-[#EDE9FE] px-2.5 py-1.5 rounded-lg font-bold text-xs transition-colors flex items-center gap-1"
                                                    title="View Pet Profile">
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>
                                            <?php if (isset($userQueueByPet[$p['pet_id']])): ?>
                                                <?php $myPos = $userQueueByPet[$p['pet_id']]['queue_position'] ?? 1; ?>
                                                <a href="#queue-section" 
                                                   class="bg-purple-100 hover:bg-purple-200 text-[#6D28D9] border border-purple-300 px-3 py-1.5 rounded-lg font-bold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                                                    <i class="fa-solid fa-clock-rotate-left"></i> In Queue (#<?= $myPos ?>)
                                                </a>
                                            <?php elseif ($p['status'] === 'Available'): ?>
                                                <a href="<?= $currentUser ? 'apply-adoption.php?pet_id=' . (int)$p['pet_id'] : 'login.php' ?>" 
                                                   class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-3.5 py-1.5 rounded-lg font-bold text-xs transition-all shadow-sm flex items-center gap-1.5">
                                                    <i class="fa-solid fa-heart"></i> <?= ($queueCounts[$p['pet_id']] ?? 0) > 0 ? 'Join Queue' : 'Adopt Me' ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 font-semibold italic">Not available</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- PET DETAILS MODAL FOR VOLUNTEERS / USERS -->
    <div id="petDetailsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-purple-100 relative max-h-[90vh] overflow-y-auto">
            <button onclick="closePetModal()" class="absolute top-4 right-4 z-10 bg-white/80 hover:bg-white text-gray-600 hover:text-gray-900 w-8 h-8 rounded-full flex items-center justify-center shadow-md transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <!-- Large Pet Photo Banner -->
            <div class="relative w-full h-52 rounded-2xl overflow-hidden mb-4 bg-purple-50 border border-purple-100 shadow-sm">
                <img id="modalPetPhoto" src="" alt="Pet Photo" class="w-full h-full object-cover">
                <div id="modalPetStatusBadge" class="absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-extrabold shadow-md"></div>
            </div>

            <div class="flex items-center gap-3 mb-4">
                <div>
                    <h3 id="modalPetName" class="text-2xl font-extrabold text-[#1E1B4B]">Pet Details</h3>
                    <p id="modalPetBreed" class="text-xs text-gray-500 font-medium">Breed Info</p>
                </div>
            </div>

            <div class="space-y-3 bg-[#FAF9FD] p-4 rounded-2xl border border-[#DDD6F3] text-sm mb-6">
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500 text-xs font-semibold">Species:</span>
                    <span id="modalPetSpecies" class="font-bold text-[#1E1B4B]"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500 text-xs font-semibold">Age:</span>
                    <span id="modalPetAge" class="font-bold text-[#1E1B4B]"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500 text-xs font-semibold">Gender:</span>
                    <span id="modalPetGender" class="font-bold text-[#1E1B4B]"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500 text-xs font-semibold">Intake Date:</span>
                    <span id="modalPetIntake" class="font-bold text-[#1E1B4B]"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500 text-xs font-semibold">Current Status:</span>
                    <span id="modalPetStatus" class="font-bold"></span>
                </div>
                <div class="flex justify-between py-1" id="modalPetQueueRow">
                    <span class="text-gray-500 text-xs font-semibold">Waiting Queue:</span>
                    <span id="modalPetQueue" class="font-bold text-[#6D28D9]"></span>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="closePetModal()" class="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs transition-colors">
                        Close
                    </button>
                    <a id="modalPassportBtn" href="medical-passport.php" target="_blank" class="px-3 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs transition-colors flex items-center gap-1.5 border border-blue-200" title="Print Official Health & Vaccination Passport">
                        <i class="fa-solid fa-passport text-blue-600"></i> Health Passport
                    </a>
                    <a id="modalMedBtn" href="medical-records.php" class="px-3 py-2.5 rounded-xl bg-purple-50 hover:bg-purple-100 text-[#6D28D9] font-bold text-xs transition-colors flex items-center gap-1.5 border border-purple-200">
                        <i class="fa-solid fa-notes-medical"></i> Medical Logs
                    </a>
                </div>
                <a id="modalAdoptBtn" href="#" class="hidden bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-heart"></i> Apply to Adopt
                </a>
            </div>
        </div>
    </div>

    <script>
    function openPetModal(pet) {
        let photoUrl = pet.image_url;
        if (!photoUrl) {
            const species = (pet.species_name || '').toLowerCase();
            if (species.includes('cat')) {
                photoUrl = 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=600&q=80';
            } else if (species.includes('rabbit')) {
                photoUrl = 'https://images.unsplash.com/photo-1585110396000-c9ffd4e4b308?auto=format&fit=crop&w=600&q=80';
            } else {
                photoUrl = 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=600&q=80';
            }
        }

        document.getElementById('modalPetPhoto').src = photoUrl;
        document.getElementById('modalPetName').textContent = pet.name;
        document.getElementById('modalPetBreed').textContent = pet.breed;
        document.getElementById('modalPetSpecies').textContent = pet.species_name;
        document.getElementById('modalPetAge').textContent = pet.age + ' years old';
        document.getElementById('modalPetGender').textContent = pet.gender;
        document.getElementById('modalPetIntake').textContent = pet.intake_date;
        document.getElementById('modalPetStatus').textContent = pet.status;

        const badge = document.getElementById('modalPetStatusBadge');
        badge.textContent = pet.status;
        badge.className = 'absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-extrabold shadow-md ' + 
            (pet.status === 'Available' ? 'bg-emerald-500 text-white' : (pet.status === 'Fostered' ? 'bg-amber-500 text-white' : 'bg-indigo-500 text-white'));

        const queueData = <?= json_encode($queueCounts) ?>;
        const myQueues = <?= json_encode(array_keys($userQueueByPet)) ?>;
        const petQCount = queueData[pet.pet_id] || 0;
        const isAlreadyInQueue = myQueues.includes(parseInt(pet.pet_id));

        const queueVal = document.getElementById('modalPetQueue');
        if (queueVal) {
            queueVal.textContent = petQCount > 0 ? (petQCount + ' in queue') : 'No queue (first in line)';
        }

        const medBtn = document.getElementById('modalMedBtn');
        if (medBtn) {
            medBtn.href = 'medical-records.php?pet_id=' + pet.pet_id;
        }

        const passportBtn = document.getElementById('modalPassportBtn');
        if (passportBtn) {
            passportBtn.href = 'medical-passport.php?pet_id=' + pet.pet_id;
        }

        const adoptBtn = document.getElementById('modalAdoptBtn');
        if (adoptBtn) {
            if (pet.status === 'Available') {
                if (isAlreadyInQueue) {
                    adoptBtn.href = '#queue-section';
                    adoptBtn.innerHTML = '<i class="fa-solid fa-clock-rotate-left"></i> In Queue';
                    adoptBtn.onclick = () => closePetModal();
                } else {
                    adoptBtn.href = isChatLoggedIn ? ('apply-adoption.php?pet_id=' + pet.pet_id) : 'login.php';
                    adoptBtn.onclick = null;
                    adoptBtn.innerHTML = petQCount > 0 ? '<i class="fa-solid fa-users"></i> Join Queue' : '<i class="fa-solid fa-heart"></i> Apply to Adopt';
                }
                adoptBtn.classList.remove('hidden');
            } else {
                adoptBtn.classList.add('hidden');
            }
        }

        document.getElementById('petDetailsModal').classList.remove('hidden');
    }

    function closePetModal() {
        document.getElementById('petDetailsModal').classList.add('hidden');
    }

    function toggleFaq(btn) {
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('.fa-chevron-down');
        const isHidden = content.classList.contains('hidden');
        
        // Close all other items in accordion
        document.querySelectorAll('#faqAccordion .faq-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('#faqAccordion .fa-chevron-down').forEach(el => el.style.transform = 'rotate(0deg)');
        
        if (isHidden) {
            content.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
    }
    </script>

    <!-- FOOTER -->
    <footer id="contact" class="bg-[#1E1B4B] text-white pt-16 pb-8 border-t border-purple-900 mt-16">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-[#6D28D9] rounded-xl flex items-center justify-center text-white">
                        <i class="fa-solid fa-paw text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold tracking-tight">PawCare</span>
                </div>
                <p class="text-xs text-purple-200 leading-relaxed max-w-sm">
                    Connecting rescues with loving foster homes and permanent adopters. Role-based access ensures safe record management and community involvement.
                </p>
            </div>

            <div>
                <h4 class="font-bold text-sm mb-4 text-purple-300 uppercase tracking-wider">Dashboard Navigation</h4>
                <ul class="space-y-2 text-xs text-purple-200">
                    <?php if ($currentUser): ?>
                        <li><a href="logout.php" class="hover:text-white transition-colors">Log Out</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="hover:text-white transition-colors">Sign In</a></li>
                        <li><a href="register.php" class="hover:text-white transition-colors">Create Account</a></li>
                    <?php endif; ?>
                    <li><a href="#pets-table" class="hover:text-white transition-colors">Pet Directory</a></li>
                    <?php if ($isAdmin): ?>
                        <li><a href="add-pet.php" class="hover:text-white transition-colors">+ Add New Pet</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-sm mb-4 text-purple-300 uppercase tracking-wider">Contact Info</h4>
                <ul class="space-y-2 text-xs text-purple-200">
                    <li><i class="fa-solid fa-location-dot mr-1 text-[#6D28D9]"></i> Philippines</li>
                    <li><i class="fa-solid fa-envelope mr-1 text-[#6D28D9]"></i> support@pawcare.org</li>
                </ul>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 pt-6 border-t border-purple-900/50 text-center text-xs text-purple-300">
            &copy; <?= date('Y') ?> PawCare Pet Adoption and Foster Tracker. All rights reserved.
        </div>
    </footer>

    <?php include __DIR__ . '/chat-widget.php'; ?>

</body>
</html>
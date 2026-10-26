<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Pet.php';
require_once __DIR__ . '/../classes/MedicalLog.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isVolunteer = Auth::isVolunteer();

$database = new Database();
$db = $database->getConnection();
$medLog = new MedicalLog($db);
$petEntity = new Pet($db);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $petId = (int)($_POST['pet_id'] ?? 0);
        $treatmentType = trim($_POST['treatment_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dateAdministered = trim($_POST['date_administered'] ?? date('Y-m-d'));

        if ($petId > 0 && !empty($treatmentType)) {
            $ok = $medLog->addRecord($petId, $treatmentType, $description, $dateAdministered, (int)$currentUser['id']);
            if ($ok) {
                $msg = 'Medical record logged successfully!';
            } else {
                $err = 'Failed to save medical record.';
            }
        } else {
            $err = 'Please select a pet and specify the treatment type.';
        }
    }
}

$selectedPetId = (int)($_GET['pet_id'] ?? 0);
$records = $medLog->getAllRecords($selectedPetId > 0 ? $selectedPetId : null);
$allPets = $petEntity->getPets();

$badgeStyles = [
    'vaccination' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'deworming'   => 'bg-purple-100 text-purple-800 border-purple-200',
    'checkup'     => 'bg-blue-100 text-blue-800 border-blue-200',
    'surgery'     => 'bg-rose-100 text-rose-800 border-rose-200',
    'dental'      => 'bg-cyan-100 text-cyan-800 border-cyan-200',
    'medication'  => 'bg-amber-100 text-amber-800 border-amber-200',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — Pet Medical Records Log</title>
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
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-notes-medical"></i> Feature #7 • Health Tracking
                </div>
                <h1 class="text-3xl font-extrabold text-[#1E1B4B]">Medical Records Log</h1>
                <p class="text-xs text-gray-500 mt-1">Track vaccinations, deworming, clinical checkups, surgeries, and veterinary treatments across all rescue pets.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="export-csv.php?type=medical<?= $selectedPetId ? '&pet_id=' . $selectedPetId : '' ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
                <a href="notifications.php" class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-4 py-2.5 rounded-xl font-bold text-xs transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-bell"></i> Health Reminders
                </a>
                <?php if ($isAdmin || $isVolunteer): ?>
                    <button onclick="document.getElementById('medModal').classList.remove('hidden')" 
                            class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Log Medical Treatment
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

        <!-- Filter By Pet Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-md border border-[#F2EEFB] mb-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <i class="fa-solid fa-filter text-gray-400"></i>
                <span class="text-xs font-bold text-gray-700">Filter Records by Pet:</span>
                <form method="GET" action="medical-records.php" class="flex-grow sm:flex-grow-0">
                    <select name="pet_id" onchange="this.form.submit()" class="border border-[#DDD6F3] rounded-xl px-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-[#6D28D9] focus:outline-none bg-white">
                        <option value="0">All Rescue Pets (<?= count($allPets) ?>)</option>
                        <?php foreach ($allPets as $p): ?>
                            <option value="<?= (int)$p['pet_id'] ?>" <?= $selectedPetId === (int)$p['pet_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['breed']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedPetId > 0): ?>
                    <a href="medical-passport.php?pet_id=<?= $selectedPetId ?>" target="_blank"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-passport"></i> Print Pet Health Passport
                    </a>
                <?php endif; ?>
            </div>

            <div class="text-xs font-bold text-gray-500">
                Showing <?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?>
            </div>
        </div>

        <!-- Medical Records Table -->
        <div class="bg-white rounded-3xl shadow-xl border border-[#F2EEFB] overflow-hidden">
            <div class="p-6 border-b border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-[#1E1B4B] flex items-center gap-2">
                        <i class="fa-solid fa-syringe text-[#6D28D9]"></i> Clinical Treatment History
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Chronological record of vaccines, wellness exams, and clinical notes.</p>
                </div>
            </div>

            <?php if (empty($records)): ?>
                <div class="p-12 text-center text-gray-400">
                    <i class="fa-solid fa-notes-medical text-3xl mb-2 text-gray-300 block"></i>
                    <p class="text-sm font-semibold">No medical records found.</p>
                    <p class="text-xs text-gray-400 mt-1">Click "Log Medical Treatment" to register a pet's vaccination or vet visit.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#FAF9FD] text-gray-500 uppercase tracking-wider text-[11px] font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Pet Details</th>
                                <th class="px-6 py-4">Treatment Type</th>
                                <th class="px-6 py-4">Description / Vet Notes</th>
                                <th class="px-6 py-4">Date Administered</th>
                                <th class="px-6 py-4">Pet Status</th>
                                <th class="px-6 py-4 text-center">Passport</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            <?php foreach ($records as $r): ?>
                                <?php 
                                    $tKey = strtolower($r['treatment_type']);
                                    $badge = $badgeStyles[$tKey] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                ?>
                                <tr class="hover:bg-purple-50/40 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= htmlspecialchars(!empty($r['image_url']) ? $r['image_url'] : Pet::getDefaultImage($r['species_name'])) ?>" 
                                                 alt="<?= htmlspecialchars($r['pet_name']) ?>"
                                                 class="w-10 h-10 rounded-xl object-cover shadow-sm border border-purple-100">
                                            <div>
                                                <span class="font-bold text-sm text-[#1E1B4B] block"><?= htmlspecialchars($r['pet_name']) ?></span>
                                                <span class="text-[11px] text-gray-500"><?= htmlspecialchars($r['breed']) ?> • <?= htmlspecialchars($r['species_name']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?= $badge ?>">
                                            <?= htmlspecialchars($r['treatment_type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 max-w-md">
                                        <?= nl2br(htmlspecialchars($r['description'] ?: 'Routine administration.')) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 font-semibold">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fa-solid fa-calendar text-gray-400"></i>
                                            <?= htmlspecialchars($r['date_administered']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $r['pet_status'] === 'Available' ? 'bg-emerald-100 text-emerald-700' : ($r['pet_status'] === 'Fostered' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700') ?>">
                                            <?= htmlspecialchars($r['pet_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <a href="medical-passport.php?pet_id=<?= (int)$r['pet_id'] ?>" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 font-bold text-xs transition-colors shadow-xs"
                                           title="Print Official Medical Passport">
                                            <i class="fa-solid fa-passport text-blue-600"></i> Health Passport
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- NEW MEDICAL RECORD MODAL -->
    <div id="medModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-purple-100 relative">
            <button onclick="document.getElementById('medModal').classList.add('hidden')" class="absolute top-4 right-4 bg-gray-100 hover:bg-gray-200 text-gray-600 w-8 h-8 rounded-full flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <h3 class="text-xl font-extrabold text-[#1E1B4B] mb-1">Log Medical Treatment</h3>
            <p class="text-xs text-gray-500 mb-5">Record a vaccination, checkup, or treatment administered to a pet.</p>

            <form method="POST" action="medical-records.php" class="space-y-4">
                <input type="hidden" name="action" value="add">

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Select Pet</label>
                    <select name="pet_id" required class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        <option value="">-- Choose a Pet --</option>
                        <?php foreach ($allPets as $p): ?>
                            <option value="<?= (int)$p['pet_id'] ?>" <?= $selectedPetId === (int)$p['pet_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['breed']) ?> - Status: <?= $p['status'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Treatment Type</label>
                    <select name="treatment_type" required class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        <option value="Vaccination">Vaccination (Anti-rabies, 5-in-1, DHPP)</option>
                        <option value="Deworming">Deworming</option>
                        <option value="Checkup">General Vet Checkup</option>
                        <option value="Surgery">Surgery / Spay & Neuter</option>
                        <option value="Dental">Dental Care & Cleaning</option>
                        <option value="Medication">Oral / Topical Medication</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Date Administered</label>
                    <input type="date" name="date_administered" value="<?= date('Y-m-d') ?>" required
                           class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Clinical Description / Notes</label>
                    <textarea name="description" rows="3" placeholder="e.g. Administered rabies vaccine batch #RB-2026. Pet in healthy condition."
                              class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('medModal').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 font-bold text-xs hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2 rounded-xl font-bold text-xs shadow-md transition-all">
                        Save Medical Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>


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

$reminders = $medLog->getVolunteerReminders();
$urgentCount = count(array_filter($reminders, fn($r) => $r['urgency'] === 'High'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — Volunteer Health & Vaccination Reminders</title>
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
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-100 text-rose-800 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-bell"></i> Feature #8 • Health Alerts
                </div>
                <h1 class="text-3xl font-extrabold text-[#1E1B4B]">Volunteer Health & Care Reminders</h1>
                <p class="text-xs text-gray-500 mt-1">Automated system notifications alerting coordinators and volunteers of upcoming vaccinations, overdue deworming, and health check-ups.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="medical-records.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 px-4 py-2.5 rounded-xl font-bold text-xs transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-notes-medical text-[#6D28D9]"></i> View All Medical Logs
                </a>
            </div>
        </div>

        <!-- Alert Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Active Alerts</span>
                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-sm">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-[#6D28D9]"><?= count($reminders) ?></div>
                <p class="text-[11px] text-gray-500 mt-1">Rescue animals requiring medical attention</p>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">High Urgency Tasks</span>
                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-rose-600"><?= $urgentCount ?></div>
                <p class="text-[11px] text-gray-500 mt-1">Vaccines or initial screenings needed immediately</p>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-md border border-[#F2EEFB]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Routine Reminders</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-amber-600"><?= count($reminders) - $urgentCount ?></div>
                <p class="text-[11px] text-gray-500 mt-1">Periodic boosters and deworming due soon</p>
            </div>
        </div>

        <!-- Reminders List -->
        <?php if (empty($reminders)): ?>
            <div class="bg-white rounded-3xl p-12 text-center shadow-md border border-[#F2EEFB]">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-2xl mb-3">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3 class="text-lg font-extrabold text-[#1E1B4B]">All Animals Up to Date!</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-md mx-auto">There are no overdue vaccinations or health checkups needed at this time. Great job team!</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($reminders as $rem): ?>
                    <div class="bg-white rounded-2xl p-6 shadow-md border border-[#F2EEFB] flex flex-col md:flex-row md:items-center justify-between gap-5 hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-4">
                            <img src="<?= htmlspecialchars(!empty($rem['image_url']) ? $rem['image_url'] : Pet::getDefaultImage($rem['species_name'] ?? 'Dog')) ?>" 
                                 alt="<?= htmlspecialchars($rem['pet_name']) ?>"
                                 class="w-14 h-14 rounded-2xl object-cover shadow-sm border border-purple-100 flex-shrink-0">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border <?= $rem['badge_color'] ?>">
                                        <?= htmlspecialchars($rem['urgency']) ?> Priority
                                    </span>
                                    <span class="text-xs text-gray-400 font-semibold">• <?= htmlspecialchars($rem['type']) ?></span>
                                </div>
                                <h3 class="font-extrabold text-base text-[#1E1B4B]">
                                    <?= htmlspecialchars($rem['title']) ?> — <span class="text-[#6D28D9]"><?= htmlspecialchars($rem['pet_name']) ?></span> (<?= htmlspecialchars($rem['breed']) ?>)
                                </h3>
                                <p class="text-xs text-gray-600 mt-1 leading-relaxed max-w-2xl">
                                    <?= htmlspecialchars($rem['message']) ?>
                                </p>
                                <div class="flex flex-wrap items-center gap-4 mt-3 text-[11px] text-gray-500 font-semibold">
                                    <span><i class="fa-solid fa-clock-rotate-left text-gray-400"></i> Last Administered: <?= htmlspecialchars($rem['last_date']) ?></span>
                                    <span><i class="fa-solid fa-calendar-day text-gray-400"></i> Recommended By: <?= htmlspecialchars($rem['due_date']) ?></span>
                                    <span class="px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 font-bold">Status: <?= htmlspecialchars($rem['pet_status']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 self-end md:self-center flex-shrink-0">
                            <a href="medical-records.php?pet_id=<?= (int)$rem['pet_id'] ?>" 
                               class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-4 py-2 rounded-xl font-bold text-xs transition-all shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-syringe"></i> Log Treatment
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>

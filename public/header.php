<?php
require_once __DIR__ . '/../classes/Auth.php';
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isVolunteer = Auth::isVolunteer();
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
?>
<header class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-[#F2EEFB]">
    <div class="max-w-7xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <!-- Logo & Brand -->
        <a href="index.php" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-[#6D28D9] rounded-xl flex items-center justify-center text-white shadow-md shadow-[#6D28D9]/20">
                <i class="fa-solid fa-paw text-xl"></i>
            </div>
            <div>
                <span class="text-xl font-extrabold tracking-tight text-[#1E1B4B] block leading-none">PawCare</span>
                <span class="text-[10px] font-semibold text-[#6D28D9] uppercase tracking-wider">Pet Tracker</span>
            </div>
        </a>

        <!-- Center Navigation Menu for Logged In Users -->
        <?php if ($currentUser): ?>
            <nav class="hidden md:flex items-center gap-1 bg-[#FAF9FD] p-1 rounded-2xl border border-[#DDD6F3] text-xs font-bold">
                <a href="index.php" 
                   class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'index.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                    <i class="fa-solid fa-shield-cat mr-1"></i> Pets
                </a>

                <?php if ($isAdmin || $isVolunteer): ?>
                    <a href="foster-tracking.php" 
                       class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'foster-tracking.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                        <i class="fa-solid fa-house-chimney-heart mr-1"></i> Foster Care
                    </a>

                    <a href="medical-records.php" 
                       class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'medical-records.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                        <i class="fa-solid fa-notes-medical mr-1"></i> Medical Logs
                    </a>

                    <a href="notifications.php" 
                       class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'notifications.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                        <i class="fa-solid fa-bell mr-1"></i> Reminders
                    </a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <a href="activity-logs.php" 
                       class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'activity-logs.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                        <i class="fa-solid fa-clock-rotate-left mr-1"></i> Activity Logs
                    </a>

                    <a href="reports.php" 
                       class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'reports.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                        <i class="fa-solid fa-chart-line mr-1"></i> Reports
                    </a>
                <?php endif; ?>

                <?php if (!$isAdmin && !$isVolunteer): ?>
                    <a href="index.php#queue-section" 
                       class="px-3.5 py-1.5 rounded-xl transition-all text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50">
                        <i class="fa-solid fa-list-ol mr-1"></i> My Waiting Queue
                    </a>
                <?php endif; ?>

                <a href="profile.php" 
                   class="px-3.5 py-1.5 rounded-xl transition-all <?= $currentScript === 'profile.php' ? 'bg-[#6D28D9] text-white shadow-sm' : 'text-gray-600 hover:text-[#6D28D9] hover:bg-purple-50' ?>">
                    <i class="fa-solid fa-id-badge mr-1"></i> Profile
                </a>
            </nav>
        <?php endif; ?>

        <!-- Right User Identity / Auth Buttons -->
        <div class="flex items-center gap-3">
            <?php if ($currentUser): ?>
                <div class="flex items-center gap-2.5 bg-[#FAF9FD] px-3.5 py-1.5 rounded-full border border-[#DDD6F3]">
                    <a href="profile.php" class="text-xs font-bold text-[#1E1B4B] hover:text-[#6D28D9] flex items-center gap-1.5 transition-colors" title="View Profile & Settings">
                        <i class="fa-solid fa-circle-user text-[#6D28D9] text-base"></i>
                        <span class="hidden sm:inline"><?= htmlspecialchars($currentUser['name']) ?></span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full <?= $isAdmin ? 'bg-purple-100 text-purple-700' : ($isVolunteer ? 'bg-teal-100 text-teal-700' : 'bg-blue-100 text-blue-700') ?>">
                            <?= htmlspecialchars($currentUser['role']) ?>
                        </span>
                    </a>
                    <span class="text-gray-300">|</span>
                    <a href="profile.php" class="text-xs font-bold text-gray-600 hover:text-[#6D28D9] transition-colors" title="Account Settings">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                    <a href="logout.php" class="text-xs font-bold text-red-600 hover:bg-red-50 px-2 py-1 rounded-md transition-colors" title="Log out">
                        Logout
                    </a>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-2">
                    <a href="login.php" class="text-xs font-bold text-[#6D28D9] hover:bg-[#EDE9FE] px-4 py-2 rounded-xl transition-colors">Log In</a>
                    <a href="register.php" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs px-4 py-2 rounded-xl shadow-md transition-all">Register to Adopt</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Subnav Bar for Small Screens -->
    <?php if ($currentUser): ?>
        <div class="md:hidden flex items-center gap-1 overflow-x-auto px-6 py-2 border-t border-[#F2EEFB] bg-[#FAF9FD] text-xs font-bold">
            <a href="index.php" class="px-2.5 py-1 rounded-lg <?= $currentScript === 'index.php' ? 'text-[#6D28D9] bg-purple-100' : 'text-gray-600' ?>">Pets</a>
            <?php if ($isAdmin || $isVolunteer): ?>
                <a href="foster-tracking.php" class="px-2.5 py-1 rounded-lg <?= $currentScript === 'foster-tracking.php' ? 'text-[#6D28D9] bg-purple-100' : 'text-gray-600' ?>">Foster</a>
                <a href="medical-records.php" class="px-2.5 py-1 rounded-lg <?= $currentScript === 'medical-records.php' ? 'text-[#6D28D9] bg-purple-100' : 'text-gray-600' ?>">Medical</a>
                <a href="notifications.php" class="px-2.5 py-1 rounded-lg <?= $currentScript === 'notifications.php' ? 'text-[#6D28D9] bg-purple-100' : 'text-gray-600' ?>">Reminders</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <a href="activity-logs.php" class="px-2.5 py-1 rounded-lg <?= $currentScript === 'activity-logs.php' ? 'text-[#6D28D9] bg-purple-100' : 'text-gray-600' ?>">Logs</a>
                <a href="reports.php" class="px-2.5 py-1 rounded-lg <?= $currentScript === 'reports.php' ? 'text-[#6D28D9] bg-purple-100' : 'text-gray-600' ?>">Reports</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>

</header>
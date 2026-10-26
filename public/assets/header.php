<?php
require_once __DIR__ . '/../../classes/Auth.php';
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
?>
<header class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-[#F2EEFB]">
  <div class="max-w-6xl mx-auto flex items-center justify-between px-6 py-4">
    <a href="index.php" class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-[#6D28D9] text-white flex items-center justify-center text-lg shadow-md shadow-[#6D28D9]/20">🐾</span>
      <div>
        <span class="text-xl font-bold tracking-tight text-[#1E1B4B] block leading-none">PawCare</span>
        <span class="text-[10px] font-semibold text-[#6D28D9] uppercase tracking-wider">Pet Tracker</span>
      </div>
    </a>
    <div class="flex items-center gap-4">
      <nav class="flex items-center gap-2 text-sm text-[#1E1B4B]">
        <a href="index.php" class="px-3 py-2 rounded-lg hover:bg-[#EDE9FE] font-medium transition-colors">Directory</a>
        <?php if ($isAdmin): ?>
          <a href="add-pet.php" class="px-4 py-2 rounded-xl bg-[#6D28D9] text-white font-bold text-xs hover:bg-[#5B21B6] transition-all shadow-md flex items-center gap-1.5">
            <span>+</span> Add Pet
          </a>
        <?php endif; ?>
      </nav>

      <?php if ($currentUser): ?>
        <div class="flex items-center gap-3 pl-2 border-l border-gray-200">
          <a href="profile.php" class="text-xs font-bold text-[#1E1B4B] hover:text-[#6D28D9] flex items-center gap-1.5 transition-colors">
            <?= htmlspecialchars($currentUser['name']) ?>
            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full <?= $isAdmin ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
              <?= htmlspecialchars($currentUser['role']) ?>
            </span>
          </a>
          <a href="profile.php" class="text-xs font-bold text-gray-600 hover:text-[#6D28D9] transition-colors" title="Settings">
            <i class="fa-solid fa-gear"></i>
          </a>
          <a href="logout.php" class="text-xs font-bold text-red-600 hover:bg-red-50 px-2.5 py-1.5 rounded-lg transition-colors">
            Logout
          </a>
        </div>
      <?php else: ?>
        <div class="flex items-center gap-2">
          <a href="login.php" class="text-xs font-bold text-[#6D28D9] hover:bg-[#EDE9FE] px-3.5 py-2 rounded-xl transition-colors">Log In</a>
          <a href="register.php" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-md transition-all">Register</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

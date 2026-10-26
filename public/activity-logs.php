<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/ActivityLogger.php';

Auth::requireAdmin('index.php?error=admin_only');
$currentUser = Auth::getCurrentUser();

$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db);

$keyword = trim($_GET['q'] ?? '');
$logs = $logger->getLogs(150, $keyword ?: null);
$totalCount = $logger->getTotalCount();

function getActionIcon(string $action): array {
    $a = strtolower($action);
    if (str_contains($a, 'registered') || str_contains($a, 'login')) {
        return ['icon' => 'fa-user-shield', 'color' => 'bg-blue-50 text-blue-600'];
    } elseif (str_contains($a, 'approved')) {
        return ['icon' => 'fa-circle-check', 'color' => 'bg-emerald-50 text-emerald-600'];
    } elseif (str_contains($a, 'delete')) {
        return ['icon' => 'fa-trash-can', 'color' => 'bg-rose-50 text-rose-600'];
    } elseif (str_contains($a, 'foster')) {
        return ['icon' => 'fa-house-chimney-heart', 'color' => 'bg-amber-50 text-amber-600'];
    } elseif (str_contains($a, 'medical') || str_contains($a, 'vaccin') || str_contains($a, 'deworm')) {
        return ['icon' => 'fa-syringe', 'color' => 'bg-indigo-50 text-indigo-600'];
    } elseif (str_contains($a, 'report')) {
        return ['icon' => 'fa-chart-pie', 'color' => 'bg-purple-50 text-purple-600'];
    }
    return ['icon' => 'fa-clock-rotate-left', 'color' => 'bg-gray-50 text-gray-600'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — System Activity Logs</title>
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
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-clock-rotate-left"></i> Feature #9 • Audit Trail
                </div>
                <h1 class="text-3xl font-extrabold text-[#1E1B4B]">System Activity Logs</h1>
                <p class="text-xs text-gray-500 mt-1">Audit log recording all critical administrator, coordinator, and user actions for full accountability.</p>
            </div>

            <div class="bg-white px-4 py-2.5 rounded-2xl shadow-sm border border-purple-100 flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-bold text-gray-600">Total Recorded Events: <span class="text-[#6D28D9] font-extrabold"><?= $totalCount ?></span></span>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-md border border-[#F2EEFB] mb-8">
            <form method="GET" action="activity-logs.php" class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative flex-grow w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" 
                           placeholder="Search activity by action keyword, user name, or email..."
                           class="w-full pl-10 pr-4 py-2.5 border border-[#DDD6F3] rounded-xl text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>
                <button type="submit" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2.5 rounded-xl font-bold text-xs transition-colors shadow-sm w-full sm:w-auto">
                    Search Logs
                </button>
                <?php if ($keyword): ?>
                    <a href="activity-logs.php" class="text-xs font-bold text-gray-500 hover:text-gray-800 px-3 py-2">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Activity Logs Table -->
        <div class="bg-white rounded-3xl shadow-xl border border-[#F2EEFB] overflow-hidden">
            <div class="p-6 border-b border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-[#1E1B4B] flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-[#6D28D9]"></i> Audit Events
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Chronological record of user accounts, applications, medical updates, and pet CRUD operations.</p>
                </div>
                <span class="text-xs font-semibold text-gray-400">
                    Showing latest <?= count($logs) ?> logs
                </span>
            </div>

            <?php if (empty($logs)): ?>
                <div class="p-12 text-center text-gray-400">
                    <i class="fa-solid fa-inbox text-3xl mb-2 text-gray-300 block"></i>
                    <p class="text-sm font-semibold">No activity logs found matching your query.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#FAF9FD] text-gray-500 uppercase tracking-wider text-[11px] font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Event #</th>
                                <th class="px-6 py-4">Action Performed</th>
                                <th class="px-6 py-4">Initiated By</th>
                                <th class="px-6 py-4">User Role</th>
                                <th class="px-6 py-4">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            <?php foreach ($logs as $log): ?>
                                <?php $meta = getActionIcon($log['action']); ?>
                                <tr class="hover:bg-purple-50/40 transition-colors">
                                    <td class="px-6 py-4 text-gray-400 font-mono text-[11px]">
                                        #<?= (int)$log['log_id'] ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg <?= $meta['color'] ?> flex items-center justify-center text-xs flex-shrink-0">
                                                <i class="fa-solid <?= $meta['icon'] ?>"></i>
                                            </div>
                                            <span class="font-bold text-[#1E1B4B] text-xs">
                                                <?= htmlspecialchars($log['action']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-800"><?= htmlspecialchars($log['full_name']) ?></div>
                                        <div class="text-[11px] text-gray-400"><?= htmlspecialchars($log['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= strtolower($log['role_name']) === 'admin' || strtolower($log['role_name']) === 'coordinator' ? 'bg-purple-100 text-[#6D28D9]' : 'bg-blue-100 text-blue-700' ?>">
                                            <?= htmlspecialchars($log['role_name']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 font-mono text-[11px]">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fa-regular fa-clock text-gray-400"></i>
                                            <?= htmlspecialchars($log['timestamp']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>


<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/ReportGenerator.php';

Auth::requireAdmin('index.php?error=admin_only');
$currentUser = Auth::getCurrentUser();

$database = new Database();
$db = $database->getConnection();
$reportGen = new ReportGenerator($db);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'generate') {
        $reportType = trim($_POST['report_type'] ?? 'Monthly Adoptions');
        if (!empty($reportType)) {
            $ok = $reportGen->recordReport($reportType, (int)$currentUser['id']);
            if ($ok) {
                $msg = "Report '{$reportType}' generated and recorded successfully!";
            } else {
                $err = 'Failed to record generated report.';
            }
        }
    }
}

$analytics = $reportGen->getAnalytics();
$history = $reportGen->getReportHistory();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — Reports & Statistics Generation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            header, button, form, .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-card { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B] min-h-screen flex flex-col">
    <?php include __DIR__ . '/header.php'; ?>

    <main class="max-w-7xl mx-auto px-6 py-10 flex-grow w-full">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="index.php" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1.5 mb-2 no-print">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-chart-line"></i> Feature #10 • Analytics & Reporting
                </div>
                <h1 class="text-3xl font-extrabold text-[#1E1B4B]">Adoption & Foster Statistics Report</h1>
                <p class="text-xs text-gray-500 mt-1">Real-time shelter operational metrics, adoption success rates, foster home capacity utilization, and health services data.</p>
            </div>

            <div class="flex items-center gap-3 no-print">
                <button onclick="window.print()" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 px-4 py-2.5 rounded-xl font-bold text-xs transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-print"></i> Print / Save PDF
                </button>
                <button onclick="document.getElementById('reportModal').classList.remove('hidden')" 
                        class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-circle-plus"></i> Generate & Archive Report
                </button>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2 no-print">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2 no-print">
                <i class="fa-solid fa-triangle-exclamation text-red-600 text-base"></i> <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-md border border-[#F2EEFB] print-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Rescues</span>
                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-sm">
                        <i class="fa-solid fa-paw"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-[#1E1B4B]"><?= $analytics['pets']['total'] ?></div>
                <p class="text-[11px] text-gray-500 mt-1">Shelter intake population</p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-md border border-[#F2EEFB] print-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Adoptions Placed</span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-shield-heart"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-emerald-600"><?= $analytics['pets']['adopted'] ?></div>
                <p class="text-[11px] text-gray-500 mt-1"><?= $analytics['applications']['success_rate'] ?>% application success rate</p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-md border border-[#F2EEFB] print-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Foster Placements</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-house-chimney-heart"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-amber-600"><?= $analytics['foster']['active_placements'] ?></div>
                <p class="text-[11px] text-gray-500 mt-1"><?= $analytics['foster']['occupancy_rate'] ?>% foster capacity utilized</p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-md border border-[#F2EEFB] print-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Medical Services</span>
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-syringe"></i>
                    </div>
                </div>
                <div class="text-3xl font-extrabold text-blue-600"><?= $analytics['medical']['total'] ?></div>
                <p class="text-[11px] text-gray-500 mt-1"><?= $analytics['medical']['vaccines'] ?> vaccines administered</p>
            </div>
        </div>

        <!-- Two Columns Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
            <!-- Species Breakdown Card -->
            <div class="bg-white rounded-3xl p-6 shadow-xl border border-[#F2EEFB] print-card">
                <h3 class="text-base font-extrabold text-[#1E1B4B] mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-shapes text-[#6D28D9]"></i> Population by Species
                </h3>
                <p class="text-xs text-gray-500 mb-6">Distribution and outcome by animal classification.</p>

                <div class="space-y-4">
                    <?php foreach ($analytics['species'] as $sp): ?>
                        <?php 
                            $tot = (int)$sp['total_count'];
                            $pct = $analytics['pets']['total'] > 0 ? round(($tot / $analytics['pets']['total']) * 100) : 0;
                        ?>
                        <div class="p-4 rounded-2xl bg-[#FAF9FD] border border-[#DDD6F3]">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-extrabold text-sm text-[#1E1B4B]"><?= htmlspecialchars($sp['species_name']) ?></span>
                                <span class="text-xs font-bold text-[#6D28D9]"><?= $tot ?> total (<?= $pct ?>%)</span>
                            </div>
                            <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#6D28D9] h-full rounded-full" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="flex justify-between text-[11px] font-semibold text-gray-600">
                                <span><span class="text-emerald-600 font-bold"><?= (int)$sp['adopted_count'] ?></span> Adopted</span>
                                <span><span class="text-amber-600 font-bold"><?= (int)$sp['fostered_count'] ?></span> Fostered</span>
                                <span><span class="text-blue-600 font-bold"><?= (int)$sp['available_count'] ?></span> Available</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Foster Capacity & Placements Card -->
            <div class="bg-white rounded-3xl p-6 shadow-xl border border-[#F2EEFB] print-card">
                <h3 class="text-base font-extrabold text-[#1E1B4B] mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-gauge-high text-amber-500"></i> Foster Care Capacity Utilization
                </h3>
                <p class="text-xs text-gray-500 mb-6">Real-time availability of approved community foster homes.</p>

                <div class="p-6 rounded-2xl bg-[#FAF9FD] border border-[#DDD6F3] mb-6 text-center">
                    <div class="text-4xl font-extrabold text-amber-600 mb-1"><?= $analytics['foster']['occupancy_rate'] ?>%</div>
                    <p class="text-xs font-bold text-gray-600">Foster Network Capacity In Use</p>
                    <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden mt-4 max-w-sm mx-auto">
                        <div class="bg-amber-500 h-full rounded-full transition-all" style="width: <?= $analytics['foster']['occupancy_rate'] ?>%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-xl font-extrabold text-gray-800"><?= $analytics['foster']['homes_count'] ?></div>
                        <span class="text-[11px] text-gray-500 font-semibold">Registered Homes</span>
                    </div>
                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-xl font-extrabold text-gray-800"><?= $analytics['foster']['total_capacity'] ?></div>
                        <span class="text-[11px] text-gray-500 font-semibold">Max Total Capacity</span>
                    </div>
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100">
                        <div class="text-xl font-extrabold text-emerald-700"><?= $analytics['foster']['total_capacity'] - $analytics['foster']['active_placements'] ?></div>
                        <span class="text-[11px] text-emerald-700 font-semibold">Spots Available</span>
                    </div>
                    <div class="p-4 rounded-xl bg-purple-50 border border-purple-100">
                        <div class="text-xl font-extrabold text-[#6D28D9]"><?= $analytics['foster']['completed_placements'] ?></div>
                        <span class="text-[11px] text-[#6D28D9] font-semibold">Completed Stays</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Saved / Archived Reports History Table -->
        <div class="bg-white rounded-3xl shadow-xl border border-[#F2EEFB] overflow-hidden no-print">
            <div class="p-6 border-b border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-[#1E1B4B] flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-[#6D28D9]"></i> Archived Generated Reports
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Audit history of saved operational and executive reports from the database.</p>
                </div>
            </div>

            <?php if (empty($history)): ?>
                <div class="p-10 text-center text-gray-400 text-xs">
                    No reports saved yet. Click "Generate & Archive Report" above to log a new snapshot.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#FAF9FD] text-gray-500 uppercase tracking-wider text-[11px] font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Report ID</th>
                                <th class="px-6 py-4">Report Title / Type</th>
                                <th class="px-6 py-4">Generated By</th>
                                <th class="px-6 py-4">Date Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            <?php foreach ($history as $h): ?>
                                <tr class="hover:bg-purple-50/40 transition-colors">
                                    <td class="px-6 py-4 font-mono text-gray-400">#<?= (int)$h['report_id'] ?></td>
                                    <td class="px-6 py-4 font-bold text-[#1E1B4B]">
                                        <i class="fa-solid fa-file-lines text-[#6D28D9] mr-1.5"></i>
                                        <?= htmlspecialchars($h['report_type']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($h['generated_by_name']) ?></span>
                                        <span class="text-[11px] text-gray-400 block"><?= htmlspecialchars($h['generated_by_email']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 font-mono text-[11px]">
                                        <?= htmlspecialchars($h['date_created']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- GENERATE REPORT MODAL -->
    <div id="reportModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-purple-100 relative">
            <button onclick="document.getElementById('reportModal').classList.add('hidden')" class="absolute top-4 right-4 bg-gray-100 hover:bg-gray-200 text-gray-600 w-8 h-8 rounded-full flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <h3 class="text-xl font-extrabold text-[#1E1B4B] mb-1">Generate & Save Report</h3>
            <p class="text-xs text-gray-500 mb-5">Create a permanent snapshot record of current shelter metrics into the database.</p>

            <form method="POST" action="reports.php" class="space-y-4">
                <input type="hidden" name="action" value="generate">

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Report Type / Category</label>
                    <select name="report_type" required class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        <option value="Monthly Adoptions">Monthly Adoptions & Placement Rate</option>
                        <option value="Foster Capacity">Foster Care Network & Capacity</option>
                        <option value="Shelter Health & Vaccinations">Clinical Health & Preventative Care</option>
                        <option value="Executive Shelter Overview">Comprehensive Executive Shelter Overview</option>
                    </select>
                </div>

                <div class="p-3 bg-purple-50 rounded-xl border border-purple-200 text-[11px] text-[#6D28D9]">
                    <i class="fa-solid fa-circle-info mr-1"></i> This action will archive current live counts and write a permanent audit entry into the activity log.
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('reportModal').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 font-bold text-xs hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2 rounded-xl font-bold text-xs shadow-md transition-all">
                        Archive Snapshot
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>


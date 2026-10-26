<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/AdoptionQueue.php';
require_once __DIR__ . '/../classes/Pet.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isVolunteer = Auth::isVolunteer();

$database = new Database();
$db = $database->getConnection();
$queueEntity = new AdoptionQueue($db);

$successMsg = '';
$errorMsg = '';

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone_number'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        if ($fullName === '') {
            $errorMsg = 'Full name cannot be empty.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Update users table
                $uStmt = $db->prepare("UPDATE users SET full_name = :name, phone_number = :phone, phone = :phone WHERE user_id = :uid");
                $uStmt->execute([
                    ':name'  => $fullName,
                    ':phone' => $phone,
                    ':uid'   => (int)$currentUser['id']
                ]);

                // 2. Update or insert adopters record
                $checkAdopter = $db->prepare("SELECT adopter_id FROM adopters WHERE LOWER(email) = LOWER(:email) LIMIT 1");
                $checkAdopter->execute([':email' => $currentUser['email']]);
                $adopterId = $checkAdopter->fetchColumn();

                if ($adopterId) {
                    $upAdopter = $db->prepare("UPDATE adopters SET full_name = :name, phone_number = :phone, address = :addr WHERE adopter_id = :aid");
                    $upAdopter->execute([
                        ':name'  => $fullName,
                        ':phone' => $phone,
                        ':addr'  => $address,
                        ':aid'   => $adopterId
                    ]);
                } else {
                    $insAdopter = $db->prepare("INSERT INTO adopters (full_name, email, phone_number, address) VALUES (:name, :email, :phone, :addr)");
                    $insAdopter->execute([
                        ':name'  => $fullName,
                        ':email' => $currentUser['email'],
                        ':phone' => $phone,
                        ':addr'  => $address
                    ]);
                }

                $db->commit();
                $_SESSION['user_name'] = $fullName;
                $currentUser['name']   = $fullName;
                $successMsg = 'Your profile contact details were updated successfully!';
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errorMsg = 'Database error while saving profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 6) {
            $errorMsg = 'New password must be at least 6 characters long.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'New password and confirmation do not match.';
        } else {
            // Verify current password
            $pwdStmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = :uid LIMIT 1");
            $pwdStmt->execute([':uid' => (int)$currentUser['id']]);
            $currentHash = $pwdStmt->fetchColumn();

            if (!password_verify($currentPass, $currentHash)) {
                $errorMsg = 'Incorrect current password. Please try again.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $upPwd = $db->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :uid");
                $upPwd->execute([
                    ':hash' => $newHash,
                    ':uid'  => (int)$currentUser['id']
                ]);
                $successMsg = 'Your password has been changed successfully!';
            }
        }
    }
}

// Fetch user profile from DB
$uStmt = $db->prepare("SELECT u.user_id, u.full_name, u.email, u.phone_number, u.created_at, r.role_name,
                             ad.address
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN adopters ad ON LOWER(u.email) = LOWER(ad.email)
                      WHERE u.user_id = :uid LIMIT 1");
$uStmt->execute([':uid' => (int)$currentUser['id']]);
$userProfile = $uStmt->fetch(PDO::FETCH_ASSOC);

// Fetch user applications / adoption history
$myApps = $queueEntity->getUserApplications($currentUser['email']);

// Tally counts
$totalApps = count($myApps);
$approvedCount = 0;
$inQueueCount = 0;
foreach ($myApps as $app) {
    if ($app['status'] === 'Approved') {
        $approvedCount++;
    } elseif (in_array($app['status'], ['Pending', 'In Review'])) {
        $inQueueCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Adoption History — PawCare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B] min-h-screen flex flex-col">
    <?php include __DIR__ . '/header.php'; ?>

    <main class="max-w-7xl mx-auto px-6 py-10 flex-grow w-full">
        <!-- Breadcrumb & Title -->
        <div class="mb-8">
            <a href="index.php" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1.5 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-[#1E1B4B] tracking-tight">Account Profile & History</h1>
                    <p class="text-xs text-gray-500 mt-1">Manage personal contact details, security credentials, and track your adopted companions.</p>
                </div>
                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wider self-start sm:self-auto <?= $isAdmin ? 'bg-purple-100 text-[#6D28D9] border border-purple-200' : ($isVolunteer ? 'bg-teal-100 text-teal-800 border border-teal-200' : 'bg-blue-100 text-blue-800 border border-blue-200') ?>">
                    <i class="fa-solid fa-id-badge"></i> <?= htmlspecialchars($userProfile['role_name']) ?> Account
                </span>
            </div>
        </div>

        <!-- Success & Error Alerts -->
        <?php if ($successMsg): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2.5 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base flex-shrink-0"></i>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2.5 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-red-600 text-base flex-shrink-0"></i>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Total Applications</span>
                    <span class="text-2xl font-black text-[#1E1B4B] mt-0.5 block"><?= $totalApps ?></span>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-lg">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Approved Companions</span>
                    <span class="text-2xl font-black text-emerald-600 mt-0.5 block"><?= $approvedCount ?></span>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-heart-circle-check"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F2EEFB] flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Active Queue Inquiries</span>
                    <span class="text-2xl font-black text-amber-600 mt-0.5 block"><?= $inQueueCount ?></span>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Edit Contact & Change Password Forms -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Personal Contact Info Form -->
                <div class="bg-white rounded-3xl p-6 shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-xl bg-purple-100 text-[#6D28D9] flex items-center justify-center text-sm">
                            <i class="fa-solid fa-user-pen"></i>
                        </div>
                        <h2 class="font-extrabold text-base text-[#1E1B4B]">Contact Details</h2>
                    </div>

                    <form method="POST" action="profile.php" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Legal Name</label>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars($userProfile['full_name'] ?? '') ?>"
                                   class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($userProfile['email'] ?? '') ?>" disabled
                                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-xs text-gray-500 cursor-not-allowed">
                            <span class="text-[10px] text-gray-400 mt-0.5 block">Email address is linked to your login ID.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Phone Number</label>
                            <input type="text" name="phone_number" placeholder="e.g. 0917-333-4444" value="<?= htmlspecialchars($userProfile['phone_number'] ?? '') ?>"
                                   class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Primary Home Address</label>
                            <textarea name="address" rows="2" placeholder="e.g. 88 Colon St, Cebu City"
                                      class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none"><?= htmlspecialchars($userProfile['address'] ?? '') ?></textarea>
                            <span class="text-[10px] text-gray-400 mt-0.5 block">Used to auto-populate adoption agreements.</span>
                        </div>

                        <button type="submit" class="w-full bg-[#6D28D9] hover:bg-[#5B21B6] text-white py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-floppy-disk"></i> Save Contact Info
                        </button>
                    </form>
                </div>

                <!-- Password Change Form -->
                <div class="bg-white rounded-3xl p-6 shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <h2 class="font-extrabold text-base text-[#1E1B4B]">Change Password</h2>
                    </div>

                    <form method="POST" action="profile.php" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Current Password</label>
                            <input type="password" name="current_password" required placeholder="••••••••"
                                   class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">New Password (min. 6 chars)</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="••••••••"
                                   class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6" placeholder="••••••••"
                                   class="w-full border border-[#DDD6F3] rounded-xl px-3.5 py-2 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                        </div>

                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-shield-halved"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: My Pets & Adoption Journey Cards -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-md border border-[#F2EEFB]">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                        <div>
                            <h2 class="text-xl font-black text-[#1E1B4B]">My Pets & Adoption History</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Records of all companion animal applications and approved pet certificates.</p>
                        </div>
                        <a href="index.php#pets-table" class="bg-purple-50 hover:bg-purple-100 text-[#6D28D9] px-4 py-2 rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5 border border-purple-200">
                            <i class="fa-solid fa-paw"></i> Browse Pets
                        </a>
                    </div>

                    <?php if (empty($myApps)): ?>
                        <div class="text-center py-12 px-4 bg-[#FAF9FD] rounded-2xl border border-dashed border-[#DDD6F3]">
                            <div class="w-14 h-14 rounded-2xl bg-purple-50 text-[#6D28D9] flex items-center justify-center text-2xl mx-auto mb-3">
                                <i class="fa-solid fa-heart-crack"></i>
                            </div>
                            <h3 class="font-bold text-sm text-[#1E1B4B]">No adoption applications on file yet</h3>
                            <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                                You haven't submitted any adoption inquiries yet. Find an available rescue pet in our directory to begin!
                            </p>
                            <a href="index.php#pets-table" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-xl bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs shadow-md transition-all">
                                <i class="fa-solid fa-shield-cat"></i> Explore Rescues
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($myApps as $app): ?>
                                <div class="p-5 rounded-2xl bg-[#FAF9FD] border border-[#DDD6F3] flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all hover:border-purple-300">
                                    <div class="flex items-start gap-4">
                                        <img src="<?= htmlspecialchars(!empty($app['image_url']) ? $app['image_url'] : Pet::getDefaultImage($app['species_name'])) ?>" 
                                             alt="<?= htmlspecialchars($app['pet_name']) ?>"
                                             class="w-16 h-16 rounded-2xl object-cover shadow-sm border border-purple-100 flex-shrink-0">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-extrabold text-base text-[#1E1B4B]"><?= htmlspecialchars($app['pet_name']) ?></h3>
                                                <?php if ($app['status'] === 'Approved'): ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                        <i class="fa-solid fa-check"></i> Approved
                                                    </span>
                                                <?php elseif ($app['status'] === 'In Review'): ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-300">
                                                        <i class="fa-solid fa-hourglass-half"></i> Under Review
                                                    </span>
                                                <?php elseif ($app['status'] === 'Cancelled'): ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600">
                                                        Cancelled
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-300">
                                                        <i class="fa-solid fa-clock"></i> In Queue (#<?= $app['queue_position'] ?? '1' ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <?= htmlspecialchars($app['species_name']) ?> • <?= htmlspecialchars($app['breed']) ?>
                                                <span class="mx-1">•</span>
                                                Applied on <?= htmlspecialchars($app['application_date']) ?>
                                            </p>

                                            <?php if (!empty($app['notes'])): ?>
                                                <p class="text-[11px] text-gray-600 italic bg-white p-2 rounded-xl border border-gray-100 mt-2 max-w-lg">
                                                    "<?= htmlspecialchars($app['notes']) ?>"
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-wrap items-center gap-2 self-start md:self-center">
                                        <?php if ($app['status'] === 'Approved'): ?>
                                            <a href="adoption-certificate.php?id=<?= (int)$app['application_id'] ?>" target="_blank"
                                               class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                                                <i class="fa-solid fa-award"></i> Certificate
                                            </a>
                                            <a href="medical-passport.php?pet_id=<?= (int)$app['pet_id'] ?>" target="_blank"
                                               class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                                                <i class="fa-solid fa-passport"></i> Health Passport
                                            </a>
                                        <?php elseif (in_array($app['status'], ['Pending', 'In Review'])): ?>
                                            <a href="queue-action.php?action=cancel&application_id=<?= $app['application_id'] ?>" 
                                               onclick="return confirm('Cancel your waiting queue application for <?= htmlspecialchars(addslashes($app['pet_name'])) ?>?');"
                                               class="text-xs font-bold text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg border border-red-200 transition-colors">
                                                Cancel Spot
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>

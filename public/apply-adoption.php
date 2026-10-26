<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Pet.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/AdoptionQueue.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();

$database = new Database();
$db = $database->getConnection();
$petEntity = new Pet($db);
$queueEntity = new AdoptionQueue($db);

$petId = (int)($_GET['pet_id'] ?? $_POST['pet_id'] ?? 0);
$pet = $petEntity->getPetById($petId);

if (!$pet || $pet['status'] !== 'Available') {
    header('Location: index.php?error=pet_not_available');
    exit;
}

$alreadyApplied = $queueEntity->hasUserApplied($currentUser['email'], $petId);
$currentQueueList = $queueEntity->getQueueForPet($petId);
$queueLength = count($currentQueueList);
$nextPosition = $queueLength + 1;

// Fetch prefill details for the user if available
$userPrefill = $db->prepare("SELECT u.phone_number, ad.phone_number AS ad_phone, ad.address 
                            FROM users u 
                            LEFT JOIN adopters ad ON LOWER(u.email) = LOWER(ad.email) 
                            WHERE u.user_id = :uid LIMIT 1");
$userPrefill->execute([':uid' => (int)$currentUser['id']]);
$prefill = $userPrefill->fetch(PDO::FETCH_ASSOC);
$defaultPhone = $prefill['phone_number'] ?: ($prefill['ad_phone'] ?? '');
$defaultAddress = $prefill['address'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? 'Philippines');
    $phone   = trim($_POST['phone'] ?? '');

    $housingType        = $_POST['housing_type'] ?? 'Own House with Fenced Yard';
    $existingPets       = $_POST['existing_pets'] ?? 'None / First-time pet parent';
    $householdAgreed    = $_POST['household_agreed'] ?? 'Yes, all members agreed';
    $carePlan           = $_POST['care_plan'] ?? 'Someone is home throughout the day';
    $applicantStatement = trim($_POST['statement'] ?? '');

    // Formulate a structured adoption screening profile
    $notes = "🏡 Housing: {$housingType}\n🐾 Existing Pets: {$existingPets}\n👥 Household Consent: {$householdAgreed}\n⏰ Care Plan: {$carePlan}";
    if ($applicantStatement !== '') {
        $notes .= "\n💬 Statement: " . $applicantStatement;
    }

    // Transaction #2: Submit application & place user in waiting queue
    $submitted = $queueEntity->submitApplication(
        $currentUser['name'],
        $currentUser['email'],
        $phone,
        $address,
        $petId,
        $notes,
        (int)$currentUser['id']
    );

    if ($submitted) {
        header('Location: index.php?applied=' . urlencode($pet['name']));
        exit;
    } else {
        $error = 'Failed to submit application. Please check that the pet is available and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawCare — Adopt <?= htmlspecialchars($pet['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B] min-h-screen">
    <?php include __DIR__ . '/header.php'; ?>

    <main class="max-w-2xl mx-auto px-6 py-12">
        <div class="mb-6">
            <a href="index.php" class="text-xs font-bold text-[#6D28D9] hover:underline flex items-center gap-1 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Pet Directory
            </a>
            <h1 class="text-3xl font-extrabold text-[#1E1B4B]">Apply to Adopt <?= htmlspecialchars($pet['name']) ?></h1>
            <p class="text-sm text-gray-500 mt-1">Submit your adoption application. Our shelter team will review and get in touch.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl p-8 border border-[#F2EEFB]">
            <!-- Pet Summary Card -->
            <div class="flex items-center justify-between gap-4 p-4 rounded-xl bg-[#FAF9FD] border border-[#DDD6F3] mb-6">
                <div class="flex items-center gap-4">
                    <img src="<?= htmlspecialchars(!empty($pet['image_url']) ? $pet['image_url'] : Pet::getDefaultImage()) ?>" 
                         alt="<?= htmlspecialchars($pet['name']) ?>"
                         class="w-16 h-16 rounded-xl object-cover shadow-sm border border-purple-100 flex-shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-[#1E1B4B]"><?= htmlspecialchars($pet['name']) ?></h3>
                        <p class="text-xs text-gray-600"><?= htmlspecialchars($pet['breed']) ?> • <?= (int)$pet['age'] ?> yrs • <?= htmlspecialchars($pet['gender']) ?></p>
                        <span class="inline-block mt-1 text-[11px] font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full">Available for Adoption</span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Queue Position</span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-purple-100 text-[#6D28D9] font-extrabold text-xs rounded-full border border-purple-200 mt-1">
                        <i class="fa-solid fa-users"></i> #<?= $nextPosition ?> in line
                    </span>
                    <p class="text-[10px] text-gray-500 mt-1"><?= $queueLength ?> waiting applicant<?= $queueLength === 1 ? '' : 's' ?></p>
                </div>
            </div>

            <?php if ($alreadyApplied): ?>
                <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-clock-rotate-left text-amber-600 text-lg"></i>
                        <div>
                            <p class="text-xs font-bold">You are already in the waiting queue for this pet!</p>
                            <p class="text-[11px] text-amber-700">Application submitted on <?= htmlspecialchars($alreadyApplied['application_date']) ?> (Status: <?= htmlspecialchars($alreadyApplied['status']) ?>).</p>
                        </div>
                    </div>
                    <a href="index.php#queue-section" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold text-xs hover:bg-amber-700 transition-colors">
                        View Queue
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" action="apply-adoption.php?pet_id=<?= $petId ?>" class="space-y-5">
                <input type="hidden" name="pet_id" value="<?= $petId ?>">

                <!-- Applicant Contact Info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Your Full Name</label>
                        <input type="text" value="<?= htmlspecialchars($currentUser['name']) ?>" readonly
                               class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-xs text-gray-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Email Address</label>
                        <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly
                               class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-xs text-gray-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Contact Phone Number</label>
                        <input type="text" name="phone" placeholder="e.g. 0917-123-4567" required value="<?= htmlspecialchars($defaultPhone) ?>"
                               class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Home Address / Location</label>
                        <input type="text" name="address" placeholder="e.g. Cebu City, Philippines" required value="<?= htmlspecialchars($defaultAddress) ?>"
                               class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                    </div>
                </div>

                <!-- Structured Adoption Screening Section -->
                <div class="p-5 bg-[#FAF9FD] rounded-2xl border border-purple-200 space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-purple-100">
                        <div class="w-7 h-7 rounded-lg bg-[#6D28D9] text-white flex items-center justify-center text-xs">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-xs text-[#1E1B4B] uppercase tracking-wider">Adoption Eligibility & Screening Questionnaire</h3>
                            <p class="text-[10px] text-gray-500">Helps our shelter team ensure a great environment for <?= htmlspecialchars($pet['name']) ?>.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <!-- Housing Type -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">1. Housing Environment</label>
                            <select name="housing_type" class="w-full border border-[#DDD6F3] rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                                <option>Own House with Fenced Yard</option>
                                <option>Own House (No Yard / Enclosed)</option>
                                <option>Apartment / Condominium (Pet Friendly)</option>
                                <option>Rented House (Landlord Approved)</option>
                            </select>
                        </div>

                        <!-- Existing Pets -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">2. Other Pets Currently at Home</label>
                            <select name="existing_pets" class="w-full border border-[#DDD6F3] rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                                <option>None (First-time pet parent)</option>
                                <option>Yes, 1-2 vaccinated companion pets</option>
                                <option>Yes, 3 or more pets</option>
                            </select>
                        </div>

                        <!-- Household Agreement -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">3. Household Agreement</label>
                            <select name="household_agreed" class="w-full border border-[#DDD6F3] rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                                <option>Yes, all household members fully agree</option>
                                <option>Pending final discussion / family visit</option>
                            </select>
                        </div>

                        <!-- Daily Care Plan -->
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">4. Daily Companion Routine</label>
                            <select name="care_plan" class="w-full border border-[#DDD6F3] rounded-xl px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                                <option>Someone is home throughout the day</option>
                                <option>Alone 4-8 hours (standard workday)</option>
                                <option>Pet sitter or family backup arranged</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Personal Statement / Why <?= htmlspecialchars($pet['name']) ?>?</label>
                    <textarea name="statement" rows="3" placeholder="Tell us why you fell in love with <?= htmlspecialchars($pet['name']) ?> and what life they'll enjoy with you..."
                              class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <a href="index.php" class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-6 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                        <i class="fa-solid fa-heart"></i> Submit Adoption Application
                    </button>
                </div>
            </form>
        </div>
    </main>
    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>

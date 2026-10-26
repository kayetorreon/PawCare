<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Pet.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();

$database = new Database();
$db = $database->getConnection();

$applicationId = (int)($_GET['id'] ?? 0);
$petId = (int)($_GET['pet_id'] ?? 0);

if ($applicationId <= 0 && $petId <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch application, adopter, and pet details
$baseSql = "SELECT a.application_id, a.application_date, a.status AS app_status, a.notes,
               ad.adopter_id, ad.full_name AS adopter_name, ad.email AS adopter_email, 
               ad.phone_number AS adopter_phone, ad.address AS adopter_address,
               p.pet_id, p.name AS pet_name, p.breed, p.age, p.gender, p.intake_date, p.image_url,
               s.species_name
        FROM adoption_applications a
        JOIN adopters ad ON a.adopter_id = ad.adopter_id
        JOIN pets p ON a.pet_id = p.pet_id
        JOIN species s ON p.species_id = s.species_id ";

if ($applicationId > 0) {
    $stmt = $db->prepare($baseSql . " WHERE a.application_id = :param LIMIT 1");
    $stmt->execute([':param' => $applicationId]);
} else {
    $stmt = $db->prepare($baseSql . " WHERE a.pet_id = :param AND a.status = 'Approved' ORDER BY a.application_id DESC LIMIT 1");
    $stmt->execute([':param' => $petId]);
}
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Adoption certificate record not found. This adoption may still be pending approval.");
}

// Security: Only admins can view any certificate; adopters can only view their own
if (!$isAdmin && strtolower(trim($data['adopter_email'])) !== strtolower(trim($currentUser['email']))) {
    die("Access denied: You do not have permission to view this certificate.");
}

$certNumber = sprintf("PAW-%s-%04d", date('Y', strtotime($data['application_date'])), $data['application_id']);
$dateFormatted = date('F j, Y', strtotime($data['application_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Certificate of Adoption — <?= htmlspecialchars($data['pet_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .certificate-container {
                box-shadow: none !important;
                border: 8px double #6D28D9 !important;
                margin: 0 auto !important;
                page-break-inside: avoid;
            }
        }
        @page {
            size: letter landscape;
            margin: 1cm;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-10 px-4 flex flex-col items-center">

    <!-- Top Action Buttons (Hidden when printed) -->
    <div class="max-w-4xl w-full mb-6 flex items-center justify-between no-print">
        <a href="index.php" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-6 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Print Official Certificate
            </button>
        </div>
    </div>

    <!-- CERTIFICATE CANVAS -->
    <div class="certificate-container bg-[#FFFEFC] max-w-4xl w-full p-10 sm:p-14 rounded-3xl shadow-2xl border-[10px] border-double border-[#6D28D9] relative overflow-hidden">
        
        <!-- Ornate Background Watermark -->
        <div class="absolute inset-0 flex items-center justify-center opacity-[0.03] pointer-events-none -z-0">
            <i class="fa-solid fa-paw text-[32rem] text-[#6D28D9]"></i>
        </div>

        <!-- Corner Accents -->
        <div class="absolute top-4 left-4 text-[#6D28D9]/40 text-2xl"><i class="fa-solid fa-certificate"></i></div>
        <div class="absolute top-4 right-4 text-[#6D28D9]/40 text-2xl"><i class="fa-solid fa-certificate"></i></div>
        <div class="absolute bottom-4 left-4 text-[#6D28D9]/40 text-2xl"><i class="fa-solid fa-certificate"></i></div>
        <div class="absolute bottom-4 right-4 text-[#6D28D9]/40 text-2xl"><i class="fa-solid fa-certificate"></i></div>

        <!-- Header / Organization Branding -->
        <div class="text-center relative z-10 mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#6D28D9] text-white text-2xl shadow-md mb-2">
                <i class="fa-solid fa-paw"></i>
            </div>
            <h2 class="text-xs uppercase font-black tracking-[0.25em] text-[#6D28D9] mb-1">
                PawCare Rescue & Animal Welfare Network
            </h2>
            <h1 class="text-3xl sm:text-4xl font-serif font-extrabold text-[#1E1B4B] tracking-tight">
                Certificate of Official Adoption
            </h1>
            <div class="w-32 h-1 bg-[#6D28D9] mx-auto mt-2 rounded-full"></div>
            <p class="text-[11px] text-gray-400 font-mono mt-1">Registry Ref: <?= $certNumber ?></p>
        </div>

        <!-- Certificate Body -->
        <div class="text-center relative z-10 my-8">
            <p class="text-xs text-gray-500 font-serif italic mb-2 tracking-wide">
                This document solemnly honors and commemorates that on this day,
            </p>
            <p class="text-sm font-serif font-bold text-gray-800 mb-4">
                <?= $dateFormatted ?>
            </p>

            <p class="text-xs text-gray-500 font-serif italic mb-1">
                the beloved rescue companion officially registered as
            </p>
            <h3 class="text-3xl sm:text-4xl font-serif font-extrabold text-[#6D28D9] mb-2 tracking-wide">
                “<?= htmlspecialchars($data['pet_name']) ?>”
            </h3>
            <p class="text-xs font-semibold text-gray-600 mb-6">
                <?= htmlspecialchars($data['breed']) ?> • <?= (int)$data['age'] ?> Year<?= (int)$data['age'] === 1 ? '' : 's' ?> Old • <?= htmlspecialchars($data['gender']) ?> <?= htmlspecialchars($data['species_name']) ?>
            </p>

            <p class="text-xs text-gray-500 font-serif italic mb-2">
                was lovingly and legally adopted into the caring family of
            </p>
            <h4 class="text-2xl sm:text-3xl font-serif font-extrabold text-[#1E1B4B] mb-2 underline decoration-[#6D28D9]/40 underline-offset-8">
                <?= htmlspecialchars($data['adopter_name']) ?>
            </h4>
            <p class="text-xs text-gray-500 max-w-lg mx-auto leading-relaxed mt-4">
                pledging lifetime care, affection, safety, and nourishment to their new forever friend, promising a home filled with joy and companionship.
            </p>
        </div>

        <!-- Pet Photo and Official Seal -->
        <div class="flex items-center justify-between gap-6 my-6 pt-6 border-t border-purple-100 relative z-10">
            <!-- Pet Photo Box -->
            <div class="flex items-center gap-3">
                <img src="<?= htmlspecialchars(!empty($data['image_url']) ? $data['image_url'] : Pet::getDefaultImage($data['species_name'])) ?>" 
                     alt="<?= htmlspecialchars($data['pet_name']) ?>"
                     class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover shadow-md border-2 border-[#6D28D9]">
                <div class="text-left text-xs">
                    <span class="font-bold text-[#1E1B4B] block"><?= htmlspecialchars($data['pet_name']) ?></span>
                    <span class="text-[11px] text-gray-500 block">Status: Official Adoption</span>
                    <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full font-bold inline-block mt-1">
                        Verified Rescue #<?= (int)$data['pet_id'] ?>
                    </span>
                </div>
            </div>

            <!-- Official Gold/Purple Seal -->
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-amber-400 bg-gradient-to-tr from-amber-500 to-yellow-300 text-amber-950 flex flex-col items-center justify-center shadow-lg text-center p-1 flex-shrink-0">
                <i class="fa-solid fa-award text-xl sm:text-2xl"></i>
                <span class="text-[8px] font-black uppercase tracking-wider mt-0.5">OFFICIAL SEAL</span>
                <span class="text-[7px] font-extrabold">PAWCARE</span>
            </div>
        </div>

        <!-- Signature Lines -->
        <div class="grid grid-cols-2 gap-12 pt-8 border-t border-gray-200 relative z-10 text-center">
            <div>
                <div class="h-10 border-b border-gray-400 mb-1 flex items-end justify-center pb-1">
                    <span class="font-serif italic text-sm text-[#6D28D9] font-bold">PawCare Rescue Authority</span>
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 block">Authorized Shelter Coordinator</span>
                <span class="text-[10px] text-gray-400">Kristine Nicole Rios</span>
            </div>

            <div>
                <div class="h-10 border-b border-gray-400 mb-1 flex items-end justify-center pb-1">
                    <span class="font-serif italic text-sm text-gray-800 font-bold"><?= htmlspecialchars($data['adopter_name']) ?></span>
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 block">Adopter Signature</span>
                <span class="text-[10px] text-gray-400">Guardian / Forever Family</span>
            </div>
        </div>

    </div>

</body>
</html>

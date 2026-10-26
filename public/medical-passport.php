<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Pet.php';

Auth::requireLogin('login.php');
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();

$database = new Database();
$db = $database->getConnection();

$petId = (int)($_GET['pet_id'] ?? 0);

if ($petId <= 0) {
    header('Location: medical-records.php');
    exit;
}

// 1. Fetch Pet Details
$pSql = "SELECT p.pet_id, p.name, p.breed, p.age, p.gender, p.intake_date, p.status, p.image_url,
                s.species_name
         FROM pets p
         JOIN species s ON p.species_id = s.species_id
         WHERE p.pet_id = :pid LIMIT 1";
$pStmt = $db->prepare($pSql);
$pStmt->execute([':pid' => $petId]);
$pet = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$pet) {
    die("Error: Pet record not found.");
}

// 2. Fetch Medical Records for this pet
$mSql = "SELECT record_id, treatment_type, description, date_administered
         FROM medical_records
         WHERE pet_id = :pid
         ORDER BY date_administered ASC, record_id ASC";
$mStmt = $db->prepare($mSql);
$mStmt->execute([':pid' => $petId]);
$records = $mStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Adopter Info if pet is adopted
$aSql = "SELECT ad.full_name AS adopter_name, ad.phone_number AS adopter_phone, ad.address AS adopter_address,
                a.application_date, a.application_id
         FROM adoption_applications a
         JOIN adopters ad ON a.adopter_id = ad.adopter_id
         WHERE a.pet_id = :pid AND a.status = 'Approved'
         ORDER BY a.application_id DESC LIMIT 1";
$aStmt = $db->prepare($aSql);
$aStmt->execute([':pid' => $petId]);
$adoptionInfo = $aStmt->fetch(PDO::FETCH_ASSOC);

$passportCode = sprintf("PAW-VET-%04d-%03d", date('Y', strtotime($pet['intake_date'])), $pet['pet_id']);
$issueDate = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinary Health Passport — <?= htmlspecialchars($pet['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; color: black !important; }
            .passport-container {
                box-shadow: none !important;
                border: 2px solid #333 !important;
                margin: 0 !important;
                padding: 1.5rem !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B] min-h-screen py-8 px-4 flex flex-col items-center">

    <!-- Top Action Toolbar (Hidden when printing) -->
    <div class="max-w-4xl w-full mb-6 flex flex-wrap items-center justify-between gap-3 no-print">
        <a href="medical-records.php" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Medical Logs
        </a>
        <div class="flex items-center gap-3">
            <a href="export-csv.php?type=medical&pet_id=<?= $petId ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-csv"></i> Export Pet History (CSV)
            </a>
            <button onclick="window.print()" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-5 py-2 rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Print Official Passport
            </button>
        </div>
    </div>

    <!-- PASSPORT / MEDICAL BOOKLET CONTAINER -->
    <div class="passport-container bg-white max-w-4xl w-full p-8 sm:p-12 rounded-3xl shadow-xl border border-purple-100 relative">
        
        <!-- Header Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b-2 border-purple-800 gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-[#6D28D9] text-white flex items-center justify-center text-2xl shadow-md">
                    <i class="fa-solid fa-notes-medical"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-[#1E1B4B] tracking-tight uppercase">Companion Animal Health Passport</h1>
                    <p class="text-xs text-gray-500 font-medium">PawCare Animal Welfare Shelter • Official Veterinary Medical Record</p>
                </div>
            </div>
            <div class="text-left sm:text-right">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Passport Document ID</span>
                <span class="text-sm font-black text-[#6D28D9] font-mono"><?= $passportCode ?></span>
                <span class="text-[11px] text-gray-500 block">Issued: <?= $issueDate ?></span>
            </div>
        </div>

        <!-- Pet Profile Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 my-6 p-5 rounded-2xl bg-[#FAF9FD] border border-[#DDD6F3]">
            <!-- Pet Photo -->
            <div class="flex flex-col items-center justify-center">
                <img src="<?= htmlspecialchars(!empty($pet['image_url']) ? $pet['image_url'] : Pet::getDefaultImage($pet['species_name'])) ?>" 
                     alt="<?= htmlspecialchars($pet['name']) ?>"
                     class="w-32 h-32 rounded-2xl object-cover shadow-md border-2 border-white">
                <span class="mt-2 text-xs font-black text-[#1E1B4B]"><?= htmlspecialchars($pet['name']) ?></span>
                <span class="text-[10px] text-gray-500 font-semibold"><?= htmlspecialchars($pet['species_name']) ?> • <?= htmlspecialchars($pet['breed']) ?></span>
            </div>

            <!-- Pet Vital Stats -->
            <div class="space-y-2 text-xs">
                <div class="flex justify-between border-b border-gray-200/60 pb-1">
                    <span class="font-bold text-gray-500">Species:</span>
                    <span class="font-black text-gray-900"><?= htmlspecialchars($pet['species_name']) ?></span>
                </div>
                <div class="flex justify-between border-b border-gray-200/60 pb-1">
                    <span class="font-bold text-gray-500">Primary Breed:</span>
                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($pet['breed']) ?></span>
                </div>
                <div class="flex justify-between border-b border-gray-200/60 pb-1">
                    <span class="font-bold text-gray-500">Age & Gender:</span>
                    <span class="font-semibold text-gray-800"><?= (int)$pet['age'] ?> years • <?= htmlspecialchars($pet['gender']) ?></span>
                </div>
                <div class="flex justify-between border-b border-gray-200/60 pb-1">
                    <span class="font-bold text-gray-500">Shelter Intake Date:</span>
                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($pet['intake_date']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold text-gray-500">Adoption Status:</span>
                    <span class="font-black <?= $pet['status'] === 'Adopted' ? 'text-indigo-600' : ($pet['status'] === 'Available' ? 'text-emerald-600' : 'text-amber-600') ?>">
                        <?= htmlspecialchars($pet['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Guardian / Shelter Custody -->
            <div class="p-3 bg-white rounded-xl border border-gray-200 text-xs flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase text-gray-400 block tracking-wider mb-1">
                        <?= $adoptionInfo ? 'Registered Adopter / Owner' : 'Shelter Custody Guardian' ?>
                    </span>
                    <?php if ($adoptionInfo): ?>
                        <p class="font-extrabold text-[#1E1B4B] text-sm"><?= htmlspecialchars($adoptionInfo['adopter_name']) ?></p>
                        <p class="text-[11px] text-gray-600 mt-1"><i class="fa-solid fa-phone mr-1 text-gray-400"></i><?= htmlspecialchars($adoptionInfo['adopter_phone']) ?></p>
                        <p class="text-[11px] text-gray-600 mt-0.5"><i class="fa-solid fa-house mr-1 text-gray-400"></i><?= htmlspecialchars($adoptionInfo['adopter_address']) ?></p>
                    <?php else: ?>
                        <p class="font-bold text-[#1E1B4B]">PawCare Shelter Unit</p>
                        <p class="text-[11px] text-gray-500 mt-1">Available for adoption matching under PawCare shelter supervision.</p>
                    <?php endif; ?>
                </div>
                <div class="mt-3 pt-2 border-t border-gray-100 flex items-center justify-between text-[11px]">
                    <span class="text-gray-400">Microchip Tag:</span>
                    <span class="font-mono font-bold text-gray-700">CHIP-<?= sprintf('%06d', $pet['pet_id'] * 7919 % 1000000) ?></span>
                </div>
            </div>
        </div>

        <!-- Clinical Medical Log Table -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-black text-[#1E1B4B] uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-syringe text-[#6D28D9]"></i> Clinical Treatment & Vaccination History
                </h2>
                <span class="text-xs text-gray-500 font-bold"><?= count($records) ?> logged intervention<?= count($records) === 1 ? '' : 's' ?></span>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-gray-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-100/80 text-gray-700 font-bold border-b border-gray-200">
                            <th class="p-3">Date Administered</th>
                            <th class="p-3">Treatment / Vaccine</th>
                            <th class="p-3">Clinical Description & Dosage</th>
                            <th class="p-3 text-center">Status / Clearance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="4" class="p-6 text-center text-gray-400 italic">
                                    No medical interventions or vaccines logged yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td class="p-3 font-semibold text-gray-800 whitespace-nowrap">
                                        <?= htmlspecialchars($r['date_administered']) ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-100 text-[#6D28D9] border border-purple-200">
                                            <?= htmlspecialchars($r['treatment_type']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-700 leading-relaxed">
                                        <?= htmlspecialchars($r['description'] ?: 'Routine preventative intervention.') ?>
                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <span class="text-emerald-700 font-bold inline-flex items-center gap-1">
                                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Administered
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Official Veterinary Clearance Statement -->
        <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-xs text-amber-900 mb-8 leading-relaxed">
            <div class="flex items-center gap-2 font-bold mb-1 text-amber-950">
                <i class="fa-solid fa-stamp text-amber-700"></i> Veterinary Health Attestation & Care Advice
            </div>
            <p>
                This animal has been physically examined and medically vetted according to PawCare shelter health protocols. 
                All vaccines and clinical treatments recorded above have been performed by licensed veterinary professionals or authorized shelter clinicians.
                Keep this document as the official vaccination passport when visiting private veterinary clinics.
            </p>
        </div>

        <!-- Signature Blocks -->
        <div class="grid grid-cols-2 gap-8 pt-6 border-t-2 border-gray-200">
            <div class="text-center">
                <div class="h-14 flex items-end justify-center pb-1">
                    <span class="font-serif italic text-lg text-purple-900 font-bold">Dr. Kristine Nicole Rios, DVM</span>
                </div>
                <div class="border-t border-gray-400 pt-1">
                    <p class="font-bold text-xs text-gray-900">Shelter Veterinary Officer</p>
                    <p class="text-[10px] text-gray-500">PawCare Animal Health Division • License #VET-88392</p>
                </div>
            </div>

            <div class="text-center">
                <div class="h-14 flex items-end justify-center pb-1">
                    <span class="font-serif italic text-base text-gray-700 font-medium">
                        <?= $adoptionInfo ? htmlspecialchars($adoptionInfo['adopter_name']) : 'PawCare Shelter Records Office' ?>
                    </span>
                </div>
                <div class="border-t border-gray-400 pt-1">
                    <p class="font-bold text-xs text-gray-900">
                        <?= $adoptionInfo ? 'Guardian / Owner Signature' : 'Authorized Shelter Custodian' ?>
                    </p>
                    <p class="text-[10px] text-gray-500">Confirmed & Signed on <?= $issueDate ?></p>
                </div>
            </div>
        </div>

        <!-- Document Footer & Watermark -->
        <div class="mt-8 pt-4 border-t border-gray-100 flex items-center justify-between text-[10px] text-gray-400">
            <span>PawCare Shelter Health Registry • Official Copy</span>
            <span>Document ID: <?= $passportCode ?></span>
        </div>
    </div>

</body>
</html>


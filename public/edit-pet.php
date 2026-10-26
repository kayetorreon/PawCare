<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Pet.php';
require_once __DIR__ . '/../classes/Auth.php';

// Only administrators are allowed to edit pets
Auth::requireAdmin('index.php?error=admin_only');

$database = new Database();
$db = $database->getConnection();
$petEntity = new Pet($db);

$id = (int)($_GET['id'] ?? $_POST['pet_id'] ?? 0);
if ($id === 0) {
    header('Location: index.php');
    exit;
}

$error = '';
$species = $db->query("SELECT species_id, species_name FROM SPECIES ORDER BY species_name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $breed       = trim($_POST['breed'] ?? '');
    $age         = (int)($_POST['age'] ?? 0);
    $gender      = $_POST['gender'] ?? '';
    $intake_date = $_POST['intake_date'] ?? '';
    $status      = $_POST['status'] ?? 'Available';
    $species_id  = (int)($_POST['species_id'] ?? 0);
    $customSpecies = trim($_POST['custom_species'] ?? '');

    // Handle user-entered custom species if not in choices
    if ($species_id === -1 || !empty($customSpecies)) {
        if (!empty($customSpecies)) {
            $checkSp = $db->prepare("SELECT species_id FROM SPECIES WHERE LOWER(species_name) = LOWER(:name) LIMIT 1");
            $checkSp->execute([':name' => $customSpecies]);
            $foundId = $checkSp->fetchColumn();
            if ($foundId) {
                $species_id = (int)$foundId;
            } else {
                $insSp = $db->prepare("INSERT INTO SPECIES (species_name) VALUES (:name)");
                $insSp->execute([':name' => ucfirst($customSpecies)]);
                $species_id = (int)$db->lastInsertId();
            }
        }
    }
    
    $existingPet = $petEntity->getPetById($id);
    $imageUrl    = trim($_POST['image_url'] ?? ($existingPet['image_url'] ?? ''));

    // Handle uploaded file if present
    if (isset($_FILES['pet_photo']) && $_FILES['pet_photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['pet_photo']['tmp_name'];
        $fileName = $_FILES['pet_photo']['name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($fileExt, $allowed, true)) {
            $uploadsDir = __DIR__ . '/uploads/pets/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $newFileName = 'pet_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $destPath    = $uploadsDir . $newFileName;
            if (move_uploaded_file($fileTmp, $destPath)) {
                $imageUrl = 'uploads/pets/' . $newFileName;
            }
        }
    }

    if (empty($imageUrl)) {
        $specStmt = $db->prepare("SELECT species_name FROM SPECIES WHERE species_id = :id");
        $specStmt->execute([':id' => $species_id]);
        $specName = $specStmt->fetchColumn() ?: '';
        $imageUrl = Pet::getDefaultImage($specName);
    }

    if ($name === '' || $breed === '' || $intake_date === '' || $species_id === 0) {
        $error = 'Please fill in all required fields.';
    } else {
        $success = $petEntity->updatePet($id, $name, $breed, $age, $gender, $intake_date, $status, $species_id, $imageUrl);

        if ($success) {
            header('Location: index.php');
            exit;
        }
        $error = 'Could not update the pet record. Please try again.';
    }

    $pet = ['pet_id' => $id, 'name' => $name, 'breed' => $breed, 'age' => $age, 'gender' => $gender,
            'intake_date' => $intake_date, 'status' => $status, 'species_id' => $species_id, 'image_url' => $imageUrl];
} else {
    $pet = $petEntity->getPetById($id);
    if (!$pet) {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PawCare — Edit Pet</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#FAF9FD] min-h-screen">
<?php include __DIR__ . '/assets/header.php'; ?>

<main class="max-w-xl mx-auto px-6 py-8">
  <h1 class="text-2xl font-bold text-[#1E1B4B]">Edit Pet</h1>
  <p class="text-sm text-gray-500 mb-6">Update <?= htmlspecialchars($pet['name']) ?>'s profile.</p>

  <?php if ($error): ?>
    <div class="mb-4 rounded-xl bg-red-50 text-red-700 text-sm px-4 py-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="edit-pet.php?id=<?= $id ?>" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-md p-6 space-y-4">
    <input type="hidden" name="pet_id" value="<?= $id ?>">
    <div>
      <label class="block text-xs font-semibold text-gray-500 mb-1">Name</label>
      <input type="text" name="name" required value="<?= htmlspecialchars($pet['name']) ?>"
             class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
    </div>

    <!-- PET PHOTO EDIT & PREVIEW -->
    <div class="p-4 bg-[#FAF9FD] rounded-xl border border-[#DDD6F3] space-y-3">
      <div class="flex items-center justify-between">
        <label class="block text-xs font-bold text-[#1E1B4B] uppercase tracking-wider">
          <i class="fa-solid fa-camera text-[#6D28D9] mr-1"></i> Pet Photo (File Upload or URL)
        </label>
        <span class="text-[10px] font-bold text-[#6D28D9] bg-purple-100 px-2 py-0.5 rounded-full">JPG, PNG, WEBP</span>
      </div>

      <!-- Current / New Preview Box -->
      <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-purple-200">
        <img id="editPhotoPreviewImg" 
             src="<?= htmlspecialchars(!empty($pet['image_url']) ? $pet['image_url'] : Pet::getDefaultImage()) ?>" 
             alt="Pet photo" 
             class="w-16 h-16 object-cover rounded-xl shadow-sm border border-purple-100">
        <div>
          <span class="text-xs font-bold text-gray-800 block" id="editPhotoPreviewLabel">Current Photo</span>
          <span class="text-[10px] text-gray-400 break-all block" id="editPhotoPreviewSub"><?= htmlspecialchars(basename($pet['image_url'] ?? 'default.jpg')) ?></span>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <span class="block text-[11px] text-gray-700 mb-1 font-bold">Upload New Image File</span>
          <input type="file" name="pet_photo" id="editPetPhotoInput" accept=".jpg,.jpeg,.png,.webp,.gif"
                 class="w-full text-xs text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#EDE9FE] file:text-[#6D28D9] hover:file:bg-[#DDD6F3] cursor-pointer bg-white border border-[#DDD6F3] rounded-xl p-1">
        </div>
        <div>
          <span class="block text-[11px] text-gray-700 mb-1 font-bold">Or Update Image URL</span>
          <input type="url" name="image_url" id="editPetPhotoUrl" placeholder="https://..." value="<?= htmlspecialchars($pet['image_url'] ?? '') ?>"
                 class="w-full border border-[#DDD6F3] rounded-xl px-3 py-2 text-xs bg-white focus:ring-1 focus:ring-[#6D28D9] focus:outline-none">
        </div>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Species</label>
        <select name="species_id" id="speciesSelect" onchange="toggleCustomSpecies(this.value)" class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none bg-white">
          <?php foreach ($species as $s): ?>
            <option value="<?= $s['species_id'] ?>" <?= $s['species_id'] == $pet['species_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['species_name']) ?>
            </option>
          <?php endforeach; ?>
          <option value="-1" class="font-bold text-[#6D28D9]">➕ Other / Add New Species...</option>
        </select>

        <div id="customSpeciesBox" class="mt-2 hidden">
          <input type="text" name="custom_species" id="customSpeciesInput" placeholder="Enter new species (e.g. Ferret, Hedgehog, Horse)"
                 class="w-full border-2 border-dashed border-[#6D28D9] bg-purple-50/50 rounded-xl px-3.5 py-2 text-xs focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Breed</label>
        <input type="text" name="breed" required value="<?= htmlspecialchars($pet['breed']) ?>"
               class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm">
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Age</label>
        <input type="number" min="0" name="age" required value="<?= (int)$pet['age'] ?>"
               class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Gender</label>
        <select name="gender" class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm">
          <option <?= $pet['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
          <option <?= $pet['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Intake Date</label>
        <input type="date" name="intake_date" required value="<?= htmlspecialchars($pet['intake_date']) ?>"
               class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
        <select name="status" class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2 text-sm">
          <option <?= $pet['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
          <option <?= $pet['status'] === 'Fostered' ? 'selected' : '' ?>>Fostered</option>
          <option <?= $pet['status'] === 'Adopted' ? 'selected' : '' ?>>Adopted</option>
        </select>
      </div>
    </div>
    <div class="flex justify-end gap-2 pt-2">
      <a href="index.php" class="px-5 py-2 rounded-xl border border-[#6D28D9] text-[#6D28D9] font-semibold text-sm">Cancel</a>
      <button type="submit" class="bg-[#6D28D9] text-white px-5 py-2 rounded-xl font-semibold text-sm hover:bg-[#5B21B6]">Update Pet</button>
    </div>
  </form>
</main>
<?php include __DIR__ . '/chat-widget.php'; ?>
<script>
function toggleCustomSpecies(val) {
    const box = document.getElementById('customSpeciesBox');
    const input = document.getElementById('customSpeciesInput');
    if (val === '-1') {
        box.classList.remove('hidden');
        input.focus();
        input.required = true;
    } else {
        box.classList.add('hidden');
        input.required = false;
        input.value = '';
    }
}

// Live Photo Preview for Edit
const editFileInput = document.getElementById('editPetPhotoInput');
const editUrlInput = document.getElementById('editPetPhotoUrl');
const editPreviewImg = document.getElementById('editPhotoPreviewImg');
const editPreviewLabel = document.getElementById('editPhotoPreviewLabel');
const editPreviewSub = document.getElementById('editPhotoPreviewSub');

if (editFileInput) {
    editFileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                editPreviewImg.src = e.target.result;
                editPreviewLabel.textContent = 'New Photo Selected';
                editPreviewSub.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            };
            reader.readAsDataURL(file);
        }
    });
}

if (editUrlInput) {
    editUrlInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (val.startsWith('http://') || val.startsWith('https://')) {
            editPreviewImg.src = val;
            editPreviewLabel.textContent = 'New Image URL';
            editPreviewSub.textContent = val;
        }
    });
}
</script>
</body>
</html>

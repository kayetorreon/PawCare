<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!empty($fullName) && !empty($email) && !empty($password)) {
        if ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            if ($auth->register($fullName, $email, $phone, $password)) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Email address is already registered.";
            }
        }
    } else {
        $error = "Please fill out all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PawCare — Adopter Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#FAF9FD] text-[#1E1B4B] min-h-screen flex flex-col justify-center py-12 px-6">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <a href="index.php" class="flex justify-center items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-[#6D28D9] rounded-2xl flex items-center justify-center text-white shadow-lg">
                <i class="fa-solid fa-paw text-2xl"></i>
            </div>
            <span class="text-2xl font-extrabold text-[#1E1B4B]">PawCare</span>
        </a>
        <h2 class="text-center text-2xl font-extrabold text-[#1E1B4B]">Create an Adopter Account</h2>
        <p class="text-center text-xs text-gray-500 mt-1">Join PawCare to apply for pet adoptions and track foster status.</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-8 shadow-xl rounded-2xl border border-[#F2EEFB]">
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-600 text-xs font-semibold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" required class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Phone Number</label>
                    <input type="text" name="phone" required class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" required class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>

                <button type="submit" class="w-full bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-sm py-3 rounded-xl shadow-md transition-all mt-2">
                    Create Account
                </button>
            </form>

            <p class="text-center text-xs text-gray-500 mt-6">
                Already have an account? <a href="login.php" class="text-[#6D28D9] font-bold hover:underline">Log in here</a>
            </p>
        </div>
    </div>
</body>
</html>
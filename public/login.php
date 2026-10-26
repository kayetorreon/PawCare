<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Handle 1-click demo logins for instant evaluation
$demo = $_GET['demo'] ?? '';
if ($demo === 'admin') {
    if ($auth->login('kristine@pawcare.org', 'password123')) {
        header("Location: index.php");
        exit;
    }
} elseif ($demo === 'volunteer') {
    if ($auth->login('ana@pawcare.org', 'password123')) {
        header("Location: index.php");
        exit;
    }
} elseif ($demo === 'adopter') {
    if ($auth->login('asinguakaye492@gmail.com', 'password123')) {
        header("Location: index.php");
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($auth->login($email, $password)) {
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email address or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PawCare — Login</title>
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
        <h2 class="text-center text-2xl font-extrabold text-[#1E1B4B]">Log in to Your Account</h2>
        <p class="text-center text-xs text-gray-500 mt-1">Select a quick demo persona or enter your credentials below.</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-8 shadow-xl rounded-2xl border border-[#F2EEFB]">
            <?php if (isset($_GET['registered'])): ?>
                <div class="mb-4 p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 text-xs font-semibold">
                    Account created successfully! You can now log in.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-600 text-xs font-semibold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- 1-CLICK DEMO TEST ACCOUNTS -->
            <div class="mb-6 p-3.5 bg-[#FAF9FD] rounded-xl border border-purple-100">
                <span class="text-[10px] font-black uppercase tracking-wider text-[#6D28D9] block text-center mb-2">
                    <i class="fa-solid fa-bolt text-amber-500 mr-1"></i> Quick 1-Click Evaluation Login
                </span>
                <div class="grid grid-cols-3 gap-2">
                    <a href="login.php?demo=admin" class="text-center py-2 px-1 bg-purple-100 hover:bg-purple-200 text-[#6D28D9] rounded-xl text-xs font-extrabold transition-colors shadow-xs">
                        👑 Admin
                    </a>
                    <a href="login.php?demo=volunteer" class="text-center py-2 px-1 bg-teal-100 hover:bg-teal-200 text-teal-800 rounded-xl text-xs font-extrabold transition-colors shadow-xs">
                        🤝 Volunteer
                    </a>
                    <a href="login.php?demo=adopter" class="text-center py-2 px-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-xl text-xs font-extrabold transition-colors shadow-xs">
                        🐾 Adopter
                    </a>
                </div>
            </div>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" required placeholder="name@pawcare.org" class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full border border-[#DDD6F3] rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#6D28D9] focus:outline-none">
                </div>

                <button type="submit" class="w-full bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-sm py-3 rounded-xl shadow-md transition-all mt-2">
                    Log In
                </button>
            </form>

            <p class="text-center text-xs text-gray-500 mt-6">
                Don't have an account? <a href="register.php" class="text-[#6D28D9] font-bold hover:underline">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>

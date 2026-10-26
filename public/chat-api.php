<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Chat.php';

$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to use the chat.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$chat = new Chat($db);

$isAdmin = Auth::isAdmin();
$currentUserId = (int)$currentUser['id'];
$adminUser = $chat->getAdminUser();

if (!$adminUser) {
    echo json_encode(['success' => false, 'error' => 'No administrator found in system.']);
    exit;
}

$adminId = (int)$adminUser['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_messages':
        if ($isAdmin) {
            $contactId = (int)($_GET['contact_id'] ?? 0);
            if ($contactId <= 0) {
                echo json_encode(['success' => true, 'messages' => [], 'contact' => null]);
                exit;
            }
            $stmt = $db->prepare("SELECT user_id, full_name, email, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = :id");
            $stmt->execute([':id' => $contactId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            $messages = $chat->getMessagesBetween($currentUserId, $contactId);
            echo json_encode([
                'success'  => true,
                'messages' => $messages,
                'contact'  => $contact,
                'my_id'    => $currentUserId
            ]);
        } else {
            // User / Volunteer chatting with Admin
            $messages = $chat->getMessagesBetween($currentUserId, $adminId);
            echo json_encode([
                'success'  => true,
                'messages' => $messages,
                'contact'  => $adminUser,
                'my_id'    => $currentUserId
            ]);
        }
        break;

    case 'get_conversations':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'error' => 'Admin only']);
            exit;
        }

        $conversations = $chat->getConversationsForAdmin($currentUserId);
        
        // Also get all other users so admin can initiate a chat if wanted
        $allUsersStmt = $db->prepare(
            "SELECT u.user_id, u.full_name, u.email, r.role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             WHERE u.user_id != :adminId 
             ORDER BY u.full_name ASC"
        );
        $allUsersStmt->execute([':adminId' => $currentUserId]);
        $allUsers = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'       => true,
            'conversations' => $conversations,
            'all_users'     => $allUsers,
            'unread_total'  => $chat->getUnreadCount($currentUserId)
        ]);
        break;

    case 'send':
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
            exit;
        }

        if ($isAdmin) {
            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            if ($receiverId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Please select a recipient.']);
                exit;
            }
        } else {
            $receiverId = $adminId;
        }

        $sent = $chat->sendMessage($currentUserId, $receiverId, $message);
        if ($sent) {
            echo json_encode([
                'success' => true,
                'sender_id' => $currentUserId,
                'receiver_id' => $receiverId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save message.']);
        }
        break;

    case 'unread_count':
        $unread = $chat->getUnreadCount($currentUserId);
        echo json_encode(['success' => true, 'unread' => $unread]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}


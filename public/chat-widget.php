<?php
require_once __DIR__ . '/../classes/Auth.php';
$chatUser = Auth::getCurrentUser();
$chatIsAdmin = Auth::isAdmin();
?>

<!-- FLOATING CHATBOX LAUNCHER BUTTON -->
<div class="fixed bottom-6 right-6 z-50 flex flex-col items-end">
    <!-- Unread badge on button -->
    <button id="pawcareChatToggleBtn" 
            onclick="togglePawcareChat()"
            class="relative flex items-center gap-2 bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-4 py-3.5 rounded-full shadow-2xl transition-all transform hover:scale-105 focus:outline-none">
        <i class="fa-solid fa-comments text-xl"></i>
        <span class="font-bold text-xs pr-1 hidden sm:inline">
            <?= $chatIsAdmin ? 'Admin Chatbox' : 'Chat with Admin' ?>
        </span>
        <span id="chatUnreadBadge" class="hidden absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full border-2 border-white shadow-sm animate-pulse">
            0
        </span>
    </button>

    <!-- CHATBOX WINDOW CONTAINER -->
    <div id="pawcareChatWindow" 
         class="hidden mt-3 w-[92vw] sm:w-[420px] md:w-[480px] h-[540px] max-h-[80vh] bg-white rounded-3xl shadow-2xl border border-purple-100 flex flex-col overflow-hidden transition-all duration-200">
        
        <?php if (!$chatUser): ?>
            <!-- GUEST NOT LOGGED IN PROMPT -->
            <div class="bg-[#6D28D9] text-white p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-headset text-sm"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm leading-tight">PawCare Shelter Chat</h4>
                        <span class="text-[10px] text-purple-200">Connect with Administrators</span>
                    </div>
                </div>
                <button onclick="togglePawcareChat()" class="text-white/80 hover:text-white p-1 rounded-lg">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-[#FAF9FD]">
                <div class="w-16 h-16 rounded-3xl bg-purple-100 text-[#6D28D9] flex items-center justify-center text-2xl mb-4">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <h3 class="text-lg font-bold text-[#1E1B4B] mb-2">Have a Question for Admin?</h3>
                <p class="text-xs text-gray-500 leading-relaxed mb-6 max-w-xs">
                    Please log in or register an account to chat directly with shelter coordinators regarding pet adoptions, foster care, or general inquiries.
                </p>
                <div class="flex flex-col gap-2.5 w-full max-w-xs">
                    <a href="login.php" class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white font-bold text-xs py-3 rounded-xl shadow-md transition-all">
                        Log In to Chat
                    </a>
                    <a href="register.php" class="bg-white border border-[#DDD6F3] text-[#6D28D9] hover:bg-[#EDE9FE] font-bold text-xs py-2.5 rounded-xl transition-all">
                        Create an Account
                    </a>
                </div>
            </div>

        <?php elseif ($chatIsAdmin): ?>
            <!-- ========================================== -->
            <!-- ADMIN CHAT INTERFACE                       -->
            <!-- ========================================== -->
            <div class="bg-[#6D28D9] text-white p-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-shield-halved text-sm"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm leading-tight">Admin Support Desk</h4>
                        <span class="text-[10px] text-purple-200">Chat with Volunteers & Adopters</span>
                    </div>
                </div>
                <button onclick="togglePawcareChat()" class="text-white/80 hover:text-white p-1 rounded-lg">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Admin Toolbar: User Selector -->
            <div class="bg-[#F3E8FF] px-4 py-2 border-b border-purple-200 flex items-center justify-between gap-2">
                <span class="text-[11px] font-bold text-[#1E1B4B] uppercase tracking-wider flex-shrink-0">
                    <i class="fa-solid fa-user-group text-[#6D28D9] mr-1"></i> User:
                </span>
                <select id="adminChatUserSelect" onchange="onAdminSelectContact()" 
                        class="text-xs bg-white border border-[#DDD6F3] rounded-lg px-2.5 py-1.5 flex-1 focus:outline-none focus:ring-1 focus:ring-[#6D28D9] font-medium text-[#1E1B4B]">
                    <option value="">Loading conversations...</option>
                </select>
            </div>

            <!-- Messages Area -->
            <div id="adminChatMessages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#FAF9FD] text-xs">
                <div class="text-center text-gray-400 py-12">
                    <i class="fa-solid fa-comments text-2xl mb-1.5 block"></i>
                    Select a user above to view conversation
                </div>
            </div>

            <!-- Message Input Form -->
            <form id="adminChatForm" onsubmit="sendAdminMessage(event)" class="p-3 bg-white border-t border-purple-100 flex items-center gap-2">
                <input type="text" id="adminChatInput" placeholder="Reply to user..." disabled
                       class="flex-1 bg-[#FAF9FD] border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs text-[#1E1B4B] focus:outline-none focus:ring-2 focus:ring-[#6D28D9] disabled:bg-gray-100">
                <button type="submit" id="adminChatSendBtn" disabled
                        class="bg-[#6D28D9] hover:bg-[#5B21B6] disabled:bg-gray-300 text-white px-4 py-2.5 rounded-xl font-bold text-xs transition-colors shadow-sm flex items-center gap-1">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- VOLUNTEER & USER / ADOPTER CHAT INTERFACE  -->
            <!-- ========================================== -->
            <div class="bg-[#6D28D9] text-white p-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-headset text-sm"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm leading-tight">PawCare Shelter Admin</h4>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            <span class="text-[10px] text-purple-200">Online & Ready to help</span>
                        </div>
                    </div>
                </div>
                <button onclick="togglePawcareChat()" class="text-white/80 hover:text-white p-1 rounded-lg">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Messages Area -->
            <div id="userChatMessages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#FAF9FD] text-xs">
                <div class="text-center text-gray-400 py-8">
                    <i class="fa-solid fa-spinner fa-spin text-xl text-[#6D28D9]"></i>
                    <p class="mt-2 text-xs">Loading conversation...</p>
                </div>
            </div>

            <!-- Message Input Form -->
            <form id="userChatForm" onsubmit="sendUserMessage(event)" class="p-3 bg-white border-t border-purple-100 flex items-center gap-2">
                <input type="text" id="userChatInput" placeholder="Type your message to the admin..." required
                       class="flex-1 bg-[#FAF9FD] border border-[#DDD6F3] rounded-xl px-3.5 py-2.5 text-xs text-[#1E1B4B] focus:outline-none focus:ring-2 focus:ring-[#6D28D9]">
                <button type="submit" 
                        class="bg-[#6D28D9] hover:bg-[#5B21B6] text-white px-4 py-2.5 rounded-xl font-bold text-xs transition-colors shadow-sm flex items-center gap-1">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>

<script>
let chatOpen = false;
let chatPollTimer = null;
const isCurrentAdmin = <?= $chatIsAdmin ? 'true' : 'false' ?>;
const isChatLoggedIn = <?= $chatUser ? 'true' : 'false' ?>;
let activeContactId = null;

function togglePawcareChat() {
    const windowEl = document.getElementById('pawcareChatWindow');
    chatOpen = !chatOpen;
    
    if (chatOpen) {
        windowEl.classList.remove('hidden');
        if (isChatLoggedIn) {
            if (isCurrentAdmin) {
                loadAdminConversations();
            } else {
                loadUserMessages();
            }
            startChatPolling();
        }
    } else {
        windowEl.classList.add('hidden');
        stopChatPolling();
    }
}

function startChatPolling() {
    stopChatPolling();
    chatPollTimer = setInterval(() => {
        if (!chatOpen) return;
        if (isCurrentAdmin) {
            if (activeContactId) {
                loadAdminMessages(activeContactId, false);
            }
        } else {
            loadUserMessages(false);
        }
    }, 3500);
}

function stopChatPolling() {
    if (chatPollTimer) {
        clearInterval(chatPollTimer);
        chatPollTimer = null;
    }
}

// Check unread count on page load
if (isChatLoggedIn) {
    checkUnreadCount();
    setInterval(checkUnreadCount, 15000);
}

function checkUnreadCount() {
    fetch('chat-api.php?action=unread_count')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('chatUnreadBadge');
            if (badge && data.success && data.unread > 0) {
                badge.textContent = data.unread;
                badge.classList.remove('hidden');
            } else if (badge) {
                badge.classList.add('hidden');
            }
        })
        .catch(() => {});
}

// ---------------- USER / VOLUNTEER FUNCTIONS ----------------
function loadUserMessages(scroll = true) {
    const container = document.getElementById('userChatMessages');
    if (!container) return;

    fetch('chat-api.php?action=get_messages')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            renderMessagesList(container, data.messages, data.my_id);
            if (scroll) {
                container.scrollTop = container.scrollHeight;
            }
        })
        .catch(err => {
            console.error(err);
        });
}

function sendUserMessage(e) {
    e.preventDefault();
    const input = document.getElementById('userChatInput');
    const msg = input.value.trim();
    if (!msg) return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('message', msg);

    input.value = '';
    fetch('chat-api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadUserMessages(true);
        } else {
            alert(data.error || 'Failed to send message');
        }
    })
    .catch(() => alert('Network error sending message.'));
}

// ---------------- ADMIN FUNCTIONS ----------------
function loadAdminConversations() {
    const select = document.getElementById('adminChatUserSelect');
    if (!select) return;

    fetch('chat-api.php?action=get_conversations')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            select.innerHTML = '<option value="">-- Choose User to Chat --</option>';

            // Active conversations
            if (data.conversations && data.conversations.length > 0) {
                const grpActive = document.createElement('optgroup');
                grpActive.label = 'Active Conversations';
                data.conversations.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.user_id;
                    const unreadBadge = c.unread_count > 0 ? ` (${c.unread_count} new)` : '';
                    opt.textContent = `${c.full_name} [${c.role_name}]${unreadBadge}`;
                    if (activeContactId && parseInt(activeContactId) === parseInt(c.user_id)) {
                        opt.selected = true;
                    }
                    grpActive.appendChild(opt);
                });
                select.appendChild(grpActive);
            }

            // All other users
            if (data.all_users && data.all_users.length > 0) {
                const grpAll = document.createElement('optgroup');
                grpAll.label = 'All Other Users';
                data.all_users.forEach(u => {
                    // Skip if already in active conversations
                    const exists = data.conversations && data.conversations.some(c => c.user_id == u.user_id);
                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = u.user_id;
                        opt.textContent = `${u.full_name} (${u.role_name})`;
                        if (activeContactId && parseInt(activeContactId) === parseInt(u.user_id)) {
                            opt.selected = true;
                        }
                        grpAll.appendChild(opt);
                    }
                });
                select.appendChild(grpAll);
            }

            if (!activeContactId && data.conversations && data.conversations.length > 0) {
                select.value = data.conversations[0].user_id;
                onAdminSelectContact();
            }
        });
}

function onAdminSelectContact() {
    const select = document.getElementById('adminChatUserSelect');
    const input = document.getElementById('adminChatInput');
    const sendBtn = document.getElementById('adminChatSendBtn');
    activeContactId = select.value ? parseInt(select.value) : null;

    if (activeContactId) {
        input.disabled = false;
        sendBtn.disabled = false;
        loadAdminMessages(activeContactId, true);
    } else {
        input.disabled = true;
        sendBtn.disabled = true;
        document.getElementById('adminChatMessages').innerHTML = `
            <div class="text-center text-gray-400 py-12">
                <i class="fa-solid fa-comments text-2xl mb-1.5 block"></i>
                Select a user above to view conversation
            </div>`;
    }
}

function loadAdminMessages(contactId, scroll = true) {
    const container = document.getElementById('adminChatMessages');
    if (!container) return;

    fetch(`chat-api.php?action=get_messages&contact_id=${contactId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            renderMessagesList(container, data.messages, data.my_id);
            if (scroll) {
                container.scrollTop = container.scrollHeight;
            }
        });
}

function sendAdminMessage(e) {
    e.preventDefault();
    if (!activeContactId) return;

    const input = document.getElementById('adminChatInput');
    const msg = input.value.trim();
    if (!msg) return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', activeContactId);
    formData.append('message', msg);

    input.value = '';
    fetch('chat-api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadAdminMessages(activeContactId, true);
        } else {
            alert(data.error || 'Failed to send message');
        }
    })
    .catch(() => alert('Network error sending message.'));
}

// ---------------- COMMON RENDER FUNCTION ----------------
function renderMessagesList(container, messages, myId) {
    if (!messages || messages.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-400 py-12">
                <i class="fa-solid fa-comment-dots text-3xl mb-2 block text-gray-300"></i>
                No messages yet. Send a message to start the conversation!
            </div>`;
        return;
    }

    let html = '';
    messages.forEach(m => {
        const isMine = parseInt(m.sender_id) === parseInt(myId);
        const timeStr = m.created_at ? m.created_at.substring(11, 16) : '';

        if (isMine) {
            html += `
                <div class="flex justify-end">
                    <div class="max-w-[78%]">
                        <div class="bg-[#6D28D9] text-white p-3 rounded-2xl rounded-tr-none shadow-sm text-xs leading-relaxed break-words">
                            ${escapeHtml(m.message)}
                        </div>
                        <div class="text-[10px] text-gray-400 text-right mt-1 font-medium pr-1">
                            ${timeStr} ${m.is_read == 1 ? '<i class="fa-solid fa-check-double text-purple-600 ml-1"></i>' : '<i class="fa-solid fa-check text-gray-400 ml-1"></i>'}
                        </div>
                    </div>
                </div>`;
        } else {
            html += `
                <div class="flex justify-start items-start gap-2">
                    <div class="w-6 h-6 rounded-full bg-purple-100 text-[#6D28D9] flex items-center justify-center text-[10px] font-bold mt-1 flex-shrink-0">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="max-w-[78%]">
                        <span class="text-[10px] font-bold text-gray-500 block mb-0.5 ml-1">${escapeHtml(m.sender_name || 'Admin')}</span>
                        <div class="bg-white border border-[#DDD6F3] text-[#1E1B4B] p-3 rounded-2xl rounded-tl-none shadow-sm text-xs leading-relaxed break-words">
                            ${escapeHtml(m.message)}
                        </div>
                        <div class="text-[10px] text-gray-400 mt-1 font-medium pl-1">
                            ${timeStr}
                        </div>
                    </div>
                </div>`;
        }
    });

    container.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>


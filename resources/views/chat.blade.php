<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div class="fixed bottom-5 right-5">
        <button id="chatToggle" class="bg-blue-500 text-white px-4 py-2 rounded-full">Chat</button>
        <div id="chatBox" class="hidden bg-white shadow-lg p-4 rounded-lg w-72">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-lg font-bold">Live Chat</h2>
                <button id="closeChat" class="text-red-500">×</button>
            </div>
            <div id="authSection" class="mb-2">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="{{ config('services.telegram.bot_name') }}"
                        data-size="large"
                        data-auth-url="https://livechat-main-h0tkup.laravel.cloud/telegram-callback"
                        data-request-access="write"></script>
            </div>
            <div id="chatSection" class="hidden">
                <div id="messages" class="h-40 overflow-y-auto border p-2 mb-2"></div>
                <div id="errorMessage" class="text-red-500 text-sm mb-2 hidden"></div>
                <input type="text" id="messageInput" class="border w-full p-2 rounded" placeholder="Type a message...">
                <button id="sendMessage" class="bg-blue-500 text-white px-3 py-1 mt-2 rounded">Send</button>
            </div>
        </div>
    </div>

    <script>
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        axios.defaults.baseURL = 'https://livechat-main-h0tkup.laravel.cloud'; // Replace with your Ngrok URL

        const elements = {
            chatToggle: document.getElementById('chatToggle'),
            chatBox: document.getElementById('chatBox'),
            closeChat: document.getElementById('closeChat'),
            authSection: document.getElementById('authSection'),
            chatSection: document.getElementById('chatSection'),
            messageInput: document.getElementById('messageInput'),
            sendMessage: document.getElementById('sendMessage'),
            messages: document.getElementById('messages'),
            errorMessage: document.getElementById('errorMessage')
        };

        elements.chatToggle.addEventListener('click', () => {
            elements.chatBox.classList.toggle('hidden');
            checkAuthStatus();
        });

        elements.closeChat.addEventListener('click', () => {
            elements.chatBox.classList.add('hidden');
        });

        function checkAuthStatus() {
            axios.get('/check-auth')
                .then(response => {
                    if (response.data.authenticated) {
                        elements.authSection.classList.add('hidden');
                        elements.chatSection.classList.remove('hidden');
                        loadChatHistory();
                    } else {
                        elements.authSection.classList.remove('hidden');
                        elements.chatSection.classList.add('hidden');
                    }
                })
                .catch(error => {
                    elements.errorMessage.textContent = 'Server error: ' + (error.response?.data.message || error.message);
                    elements.errorMessage.classList.remove('hidden');
                });
        }

        function loadChatHistory() {
            axios.get('/chat-history')
                .then(response => {
                    if (response.data.success) {
                        elements.messages.innerHTML = '';
                        response.data.chat.forEach(msg => {
                            const color = msg.sender === 'client' ? 'text-blue-500' : 'text-green-500';
                            elements.messages.innerHTML += `<div class="${color}">${msg.content}</div>`;
                        });
                        elements.messages.scrollTop = elements.messages.scrollHeight;
                    }
                });
        }

        function sendChatMessage() {
            const message = elements.messageInput.value.trim();
            if (!message) return;

            elements.sendMessage.disabled = true;
            elements.errorMessage.classList.add('hidden');

            axios.post('/send-message', { message })
                .then(response => {
                    if (response.data.success) {
                        response.data.chat.forEach(msg => {
                            const color = msg.sender === 'client' ? 'text-blue-500' : 'text-green-500';
                            elements.messages.innerHTML += `<div class="${color}">${msg.content}</div>`;
                        });
                        elements.messageInput.value = '';
                        elements.messages.scrollTop = elements.messages.scrollHeight;
                    } else {
                        elements.errorMessage.textContent = response.data.message;
                        elements.errorMessage.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    elements.errorMessage.textContent = error.response?.data.message || 'Failed to send';
                    elements.errorMessage.classList.remove('hidden');
                })
                .finally(() => {
                    elements.sendMessage.disabled = false;
                });
        }

        elements.sendMessage.addEventListener('click', sendChatMessage);
        elements.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendChatMessage();
        });

        window.onTelegramAuth = function(user) {
            // Telegram widget uses GET, handled by redirect
            console.log('Telegram auth initiated', user);
        };

        checkAuthStatus();
    </script>
</body>
</html>
<?php

// Проверяем, авторизован ли пользователь
if (!isset($_COOKIE['user_id'])) {
    header('Location: login.html');
    exit();
}

// Подключаем файл с настройками базы данных
require 'database.php';
$db_conn = DarkMessager::get_instance("dark_messager", "root");

// Получаем чаты для текущего пользователя
$chats = $db_conn->get_chats_for_user($_COOKIE['user_id']);

// Получаем всех пользователей для списка контактов
$users = $db_conn->get_all_users();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Боковая панель с чатами и контактами -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Чаты</h2>
            <button class="logout-btn" onclick="location.href='logout.php'">Выйти</button>
        </div>
        <div class="chats">
            <?php if (empty($chats)): ?>
                <p>У вас нет чатов.</p>
            <?php else: foreach ($chats as $chat): ?>
                <button class="btn-chat" id="<?= htmlspecialchars($chat['id_chat']) ?>">
                    <?= htmlspecialchars($chat['name'] ?: 'Личный чат') ?>
                </button>
            <?php endforeach; endif; ?>
        </div>
        <button class="create-group-chat" id="create-group-chat">Новая группа</button>
        <div class="user-list">
            <?php foreach ($users as $user): ?>
                <?php if ($user['id_user'] != $_COOKIE['user_id']): ?>
                    <button data-user-id="<?= htmlspecialchars($user['id_user']) ?>">
                        <?= htmlspecialchars($user['login']) ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Область чата -->
    <div class="chat-container" style="display: none;">
        <div id="messages"></div>
        <form id="message-form">
            <input type="text" id="message-input" autocomplete="off" placeholder="Напишите сообщение...">
            <button type="submit">🛫</button>
        </form>
    </div>

    <!-- Модальное окно для создания группы -->
    <div id="group-chat-modal">
        <h3>Новая группа</h3>
        <form id="group-chat-form">
            <label for="chat-name">Название группы:</label>
            <input type="text" id="chat-name" required>
            <h4>Выберите участников:</h4>
            <?php foreach ($users as $user): ?>
                <?php if ($user['id_user'] != $_COOKIE['user_id']): ?>
                    <label>
                        <input type="checkbox" name="members[]" value="<?= htmlspecialchars($user['id_user']) ?>">
                        <?= htmlspecialchars($user['login']) ?>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
            <button type="submit">Создать</button>
            <button type="button" id="cancel-group-chat">Отменить</button>
        </form>
    </div>

    <script>
        // Устанавливаем токен и идентификатор пользователя
        const token = '<?= htmlspecialchars($_COOKIE['auth_token'] ?? '') ?>';
        const userId = <?= $_COOKIE['user_id'] ?>;
        const socket = new WebSocket(`ws://myproject:8080?token=${token}`);
        let currentChatId = null;
        const chatContainer = document.querySelector('.chat-container');

        // Обработчик клика по чату
        document.querySelectorAll('.btn-chat').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                document.querySelector('.btn-chat.active')?.classList.remove('active');
                this.classList.add('active');
                chatContainer.style.display = 'flex';
                currentChatId = this.id;
                socket.send(JSON.stringify({
                    action: 'chat',
                    chat_id: currentChatId,
                }));
            });
        });

        // Обработчик отправки сообщения
        document.getElementById('message-form').addEventListener('submit', function(event) {
            event.preventDefault();
            if (!currentChatId) return;
            const input = document.getElementById('message-input');
            const msg = {
                action: 'message',
                chat_id: currentChatId,
                message: input.value,
            };
            socket.send(JSON.stringify(msg));
            input.value = '';
        });

        // Обработчик открытия модального окна
        document.getElementById('create-group-chat').addEventListener('click', function() {
            document.getElementById('group-chat-modal').style.display = 'block';
        });

        // Обработчик закрытия модального окна
        document.getElementById('cancel-group-chat').addEventListener('click', function() {
            document.getElementById('group-chat-modal').style.display = 'none';
        });

        // Обработчик создания группового чата
        document.getElementById('group-chat-form').addEventListener('submit', function(event) {
            event.preventDefault();
            const chatName = document.getElementById('chat-name').value;
            const members = Array.from(
                document.querySelectorAll('#group-chat-form input[name="members[]"]:checked')
            ).map(checkbox => parseInt(checkbox.value));
            const msg = {
                action: 'create_chat',
                chat_name: chatName,
                members: members,
            };
            socket.send(JSON.stringify(msg));
            document.getElementById('group-chat-modal').style.display = 'none';
        });

        // Обработчик клика по контакту для создания личного чата
        document.querySelectorAll('.user-list button').forEach(button => {
            button.addEventListener('click', function() {
                const user2_id = this.dataset.userId;
                const msg = {
                    action: 'private_chat',
                    user_id: user2_id,
                };
                socket.send(JSON.stringify(msg));
            });
        });

        // Обработчик удаления сообщения
        document.getElementById('messages').addEventListener('click', function(event) {
            if (event.target.classList.contains('delete-btn')) {
                const messageId = event.target.dataset.messageId;
                const msg = {
                    action: 'delete_message',
                    chat_id: currentChatId,
                    message_id: messageId,
                };
                socket.send(JSON.stringify(msg));
            }
        });

        // Обработчик входящих сообщений от WebSocket
        socket.addEventListener('message', function(event) {
            const data = JSON.parse(event.data);
            switch (data.action) {
                case 'chat_created':
                    alert(`Чат "${data.chat_name}" успешно создан!`);
                    location.reload();
                    break;
                case 'private_chat_created':
                    alert(`Чат создан!`);
                    location.reload();
                    break;
                case 'message':
                    showMessage(data);
                    break;
                case 'chat':
                    showChat(data);
                    break;
                case 'message_deleted':
                    deleteMessage(data);
                    break;
            }
        });

        // Функция отображения нового сообщения
        function showMessage(data) {
            const messages = document.getElementById('messages');
            const message = document.createElement('div');
            message.classList.add('message');
            message.classList.add(data.sender_id == userId ? 'sent' : 'received');
            message.setAttribute('data-message-id', data.message_id);
            const sender = data.sender || 'Неизвестный';
            const timestamp = new Date(data.timestamp || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            message.innerHTML = `
                <strong>${sender}</strong> <span class="timestamp">${timestamp}</span><br>
                ${data.message}
                <button class="delete-btn" data-message-id="${data.message_id}">Удалить</button>
            `;
            messages.appendChild(message);
            messages.scrollTop = messages.scrollHeight;
        }

        // Функция отображения сообщений из базы данных
        function showChat(data) {
            const messages = document.getElementById('messages');
            messages.innerHTML = '';
            // Добавляем отладку для проверки данных
            console.log("Сообщения из базы данных:", data.messages);
            console.log("Текущий userId:", userId);
            data.messages.forEach(msg => {
                const message = document.createElement('div');
                message.classList.add('message');
                // Проверяем, является ли сообщение отправленным текущим пользователем
                console.log("Сообщение:", msg, "ID отправителя:", msg.id_user);
                const isSent = msg.id_user == userId;
                message.classList.add(isSent ? 'sent' : 'received');
                message.setAttribute('data-message-id', msg.id_message);
                const timestamp = new Date(msg.datetime_message || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                message.innerHTML = `
                    <strong>${msg.sender}</strong> <span class="timestamp">${timestamp}</span><br>
                    ${msg.message}
                    <button class="delete-btn" data-message-id="${msg.id_message}">Удалить</button>
                `;
                messages.appendChild(message);
            });
            messages.scrollTop = messages.scrollHeight;
        }

        // Функция удаления сообщения
        function deleteMessage(data) {
            const messageElement = document.querySelector(`[data-message-id="${data.message_id}"]`);
            if (messageElement) {
                messageElement.remove();
            }
        }
    </script>
</body>
</html>
const socket = new WebSocket(`ws://myproject:8080?token=${token}`);
let currentChatId = null;

document.querySelectorAll('.btn-chat').forEach(button => {
    button.addEventListener('click', function(event) {
        event.preventDefault();
        document.querySelector('.btn-chat.active')?.classList.remove('active');
        this.classList.add('active');
        currentChatId = this.id;
        socket.send(JSON.stringify({
            action: 'chat',
            chat_id: currentChatId,
        }));
    });
});

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

// Открытие модального окна
document.getElementById('create-group-chat').addEventListener('click', function() {
    document.getElementById('group-chat-modal').style.display = 'block';
});

// Закрытие модального окна
document.getElementById('cancel-group-chat').addEventListener('click', function() {
    document.getElementById('group-chat-modal').style.display = 'none';
});

// Обработка формы создания группового чата
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

socket.addEventListener('message', function(event) {
    const data = JSON.parse(event.data);
    switch (data.action) {
        case 'chat_created':
            alert(`Chat "${data.chat_name}" created successfully!`);
            location.reload();
            break;
        case 'private_chat_created':
            alert(`Private chat created successfully!`);
            location.reload();
            break;
        case 'message':
            showMessage(data);
            break;
        case 'chat':
            showChat(data);
            break;
    }
});

function showMessage(data) {
    const messages = document.getElementById('messages');
    const message = document.createElement('div');
    const sender = data.sender_id == userId ? 'You' : 'Other';
    message.innerHTML = `<strong>[${sender}]</strong> ${data.timestamp}: ${data.message}`;
    messages.appendChild(message);
    messages.scrollTop = messages.scrollHeight;
}

function showChat(data) {
    const messages = document.getElementById('messages');
    messages.innerHTML = '';
    data.messages.forEach(msg => {
        const message = document.createElement('div');
        message.innerHTML = `<strong>[${msg.sender}]</strong> ${msg.datetime_message}: ${msg.message}`;
        messages.appendChild(message);
    });
    messages.scrollTop = messages.scrollHeight;
}
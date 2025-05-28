<?php
// Подключаем автозагрузчик Composer
require __DIR__ . '/vendor/autoload.php';
// Подключаем файл с функциями работы с JWT
require 'jwt_helper.php';
// Подключаем файл с настройками базы данных
require 'database.php';

// Импортируем необходимые классы из Ratchet
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

class WebSocketHandler implements MessageComponentInterface {
    // Массив для хранения подключений пользователей с их идентификаторами
    private $userConnections = [];
    // Объект для работы с базой данных
    private $db_conn;
    
    // Конструктор класса, инициализирует подключение к базе данных
    public function __construct() {
        $this->db_conn = DarkMessager::get_instance("dark_messager", "root");
    }
    
    // Обработчик открытия нового соединения
    public function onOpen(ConnectionInterface $conn) {
        // Получаем параметры запроса из URL
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);
        
        // Проверяем наличие токена в параметрах
        if (isset($params['token'])) {
            // Валидируем токен и получаем идентификатор пользователя
            $userId = JwtHelper::validateToken($params['token']);
            
            if ($userId) {
                // Сохраняем подключение пользователя
                $this->userConnections[$userId] = $conn;
                echo "Новое подключение клиента: {$userId}\n";
                return;
            }
        }
        
        // Закрываем соединение, если токен невалиден
        $conn->close();
    }

    // Обработчик получения сообщения от клиента
    public function onMessage(ConnectionInterface $from, $msg) {
        // Декодируем полученное JSON-сообщение
        $message = json_decode($msg, true);
        // Находим идентификатор пользователя по его подключению
        $userId = array_search($from, $this->userConnections);
        // Получаем данные пользователя из базы данных
        $user = $this->db_conn->get_user_by_id($userId);

        // Обрабатываем действие в зависимости от типа сообщения
        switch ($message['action']) {
            case 'message':
                // Сохраняем сообщение в базе данных и получаем его ID
                $messageId = $this->db_conn->saveMessage($message['chat_id'], $message['message'], $userId);
                // Получаем список участников чата
                $chat_members = $this->db_conn->getChatMembers($message['chat_id']);
                
                // Отправляем сообщение всем участникам чата
                foreach ($chat_members as $member) {
                    if (isset($this->userConnections[$member])) {
                        $this->userConnections[$member]->send(json_encode([
                            'action' => 'message',
                            'chat_id' => $message['chat_id'],
                            'message' => $message['message'],
                            'sender_id' => $userId,
                            'sender' => $user['login'],
                            'message_id' => $messageId,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]));
                    }
                }
                break;

            case 'chat':
                // Получаем сообщения из указанного чата и отправляем клиенту
                $chat_messages = $this->db_conn->getMessagesFromChat($message['chat_id']);
                $from->send(json_encode([
                    'action' => 'chat',
                    'chat_id' => $message['chat_id'],
                    'messages' => $chat_messages,
                ]));
                break;

            case 'create_chat':
                // Создаем новый групповой чат и отправляем подтверждение
                $chat_id = $this->db_conn->createChat($message['chat_name'], 'group', $userId, $message['members'] ?? []);
                $from->send(json_encode([
                    'action' => 'chat_created',
                    'chat_id' => $chat_id,
                    'chat_name' => $message['chat_name'],
                ]));
                break;

            case 'private_chat':
                // Создаем личный чат и отправляем подтверждение
                $chat_id = $this->db_conn->createPrivateChat($userId, $message['user_id']);
                $from->send(json_encode([
                    'action' => 'private_chat_created',
                    'chat_id' => $chat_id,
                ]));
                break;

            case 'delete_message':
                // Получаем ID сообщения и пользователя
                $messageId = $message['message_id'];
                $userId = array_search($from, $this->userConnections);
                // Пытаемся удалить сообщение
                $isDeleted = $this->db_conn->deleteMessage($messageId, $userId);

                if ($isDeleted) {
                    // Уведомляем всех участников чата об удалении сообщения
                    $chat_members = $this->db_conn->getChatMembers($message['chat_id']);
                    foreach ($chat_members as $member) {
                        if (isset($this->userConnections[$member])) {
                            $this->userConnections[$member]->send(json_encode([
                                'action' => 'message_deleted',
                                'chat_id' => $message['chat_id'],
                                'message_id' => $messageId,
                            ]));
                        }
                    }
                } else {
                    // Уведомляем пользователя об ошибке удаления
                    $from->send(json_encode([
                        'action' => 'error',
                        'message' => 'Не удалось удалить сообщение.',
                    ]));
                }
                break;
        }
    }

    // Обработчик закрытия соединения
    public function onClose(ConnectionInterface $conn) {
        // Удаляем подключение пользователя из списка
        $userId = array_search($conn, $this->userConnections);
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
        }
        echo "Клиент отключен\n";
    }

    // Обработчик ошибок
    public function onError(ConnectionInterface $conn, \Exception $e) {
        // Выводим информацию об ошибке и закрываем соединение
        echo "Произошла ошибка: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Запускаем WebSocket-сервер на порту 8080
$server = IoServer::factory(
    new HttpServer(new WsServer(new WebSocketHandler())),
    8080
);
echo "WebSocket-сервер запущен\n";
$server->run();
?>
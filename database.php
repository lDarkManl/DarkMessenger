<?php
class DarkMessager {
    // Статическая переменная для хранения единственного экземпляра класса
    private static $instance = null;
    // Объект подключения к базе данных
    private $sql_connection;
    
    // Приватный конструктор для реализации паттерна Singleton
    private function __construct($dbname, $login, $password = "") {
        // Формируем DSN для подключения к MySQL
        $dsn = "mysql:host=MySQL-8.4;dbname=" . $dbname . ";charset=utf8mb4";
        // Создаем новое подключение PDO
        $this->sql_connection = new PDO($dsn, $login, $password);
        // Устанавливаем режим обработки ошибок
        $this->sql_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    // Метод для получения единственного экземпляра класса
    public static function get_instance($dbname, $login, $password = "") {
        if (self::$instance == null) {
            self::$instance = new DarkMessager($dbname, $login, $password);
        }
        return self::$instance;
    }

    // Регистрация нового пользователя
    public function register($username, $password) {
        $stmt = $this->sql_connection->prepare("INSERT INTO Users (login, password) VALUES (:login, :password)");
        $stmt->bindParam(':login', $username);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        // Возвращаем ID и имя нового пользователя
        return ['id' => $this->sql_connection->lastInsertId(), 'username' => $username];
    }

    // Получение данных пользователя по логину
    public function get_user($username) {
        $stmt = $this->sql_connection->prepare("SELECT * FROM Users WHERE login = :login");
        $stmt->bindParam(':login', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Получение списка чатов для указанного пользователя
    public function get_chats_for_user($user_id) {
        $stmt = $this->sql_connection->prepare("
            SELECT c.id_chat, c.name 
            FROM Chat c
            JOIN Chat_members cm ON c.id_chat = cm.id_chat
            WHERE cm.id_user = :user_id
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получение всех сообщений из указанного чата
    public function getMessagesFromChat($chat_id) {
        $stmt = $this->sql_connection->prepare("
            SELECT m.id_message, m.datetime_message, u.login AS sender, m.message, m.id_user
            FROM Messages m
            JOIN Users u ON m.id_user = u.id_user
            WHERE m.id_chat = :chat_id
            ORDER BY m.datetime_message ASC
        ");
        $stmt->bindParam(':chat_id', $chat_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Сохранение нового сообщения в базе данных
    public function saveMessage($chat_id, $message, $user_id) {
        $stmt = $this->sql_connection->prepare("
            INSERT INTO Messages (datetime_message, id_user, id_chat, message) 
            VALUES (NOW(), :user_id, :chat_id, :message)
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':chat_id', $chat_id);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
        // Возвращаем ID последнего вставленного сообщения
        return $this->sql_connection->lastInsertId();
    }

    // Получение списка участников чата
    public function getChatMembers($chat_id) {
        $stmt = $this->sql_connection->prepare("
            SELECT id_user 
            FROM Chat_members 
            WHERE id_chat = :chat_id
        ");
        $stmt->bindParam(':chat_id', $chat_id);
        $stmt->execute();
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id_user');
    }

    // Создание нового чата
    public function createChat($name, $type, $creator_id, $members = []) {
        $stmt = $this->sql_connection->prepare("
            INSERT INTO Chat (type, id_user, name) 
            VALUES (:type, :creator_id, :name)
        ");
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':creator_id', $creator_id);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $chat_id = $this->sql_connection->lastInsertId();
        
        // Добавляем создателя чата
        $this->addChatMember($chat_id, $creator_id);
        // Добавляем остальных участников
        foreach ($members as $member_id) {
            $this->addChatMember($chat_id, $member_id);
        }
        return $chat_id;
    }

    // Добавление участника в чат
    public function addChatMember($chat_id, $user_id) {
        $stmt = $this->sql_connection->prepare("
            INSERT INTO Chat_members (id_user, id_chat) 
            VALUES (:user_id, :chat_id)
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':chat_id', $chat_id);
        $stmt->execute();
    }

    // Создание приватного чата между двумя пользователями
    public function createPrivateChat($user1_id, $user2_id) {
        // Проверяем, существует ли уже приватный чат
        $stmt = $this->sql_connection->prepare("
            SELECT c.id_chat 
            FROM Chat c
            JOIN Chat_members cm1 ON c.id_chat = cm1.id_chat
            JOIN Chat_members cm2 ON c.id_chat = cm2.id_chat
            WHERE c.type = 'private' 
              AND ((cm1.id_user = :user1_id AND cm2.id_user = :user2_id) 
                   OR (cm1.id_user = :user2_id AND cm2.id_user = :user1_id))
        ");
        $stmt->bindParam(':user1_id', $user1_id);
        $stmt->bindParam(':user2_id', $user2_id);
        $stmt->execute();
        $existing_chat = $stmt->fetch(PDO::FETCH_ASSOC);

        // Если чат существует, возвращаем его ID
        if ($existing_chat) {
            return $existing_chat['id_chat'];
        }

        // Получаем логины пользователей для названия чата
        $stmt = $this->sql_connection->prepare("
            SELECT login FROM Users WHERE id_user IN (:user1_id, :user2_id)
        ");
        $stmt->bindParam(':user1_id', $user1_id);
        $stmt->bindParam(':user2_id', $user2_id);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN); // Массив логинов

        // Формируем название чата
        $chat_name = implode('-', $users);

        // Создаем новый приватный чат
        $chat_id = $this->createChat($chat_name, 'private', $user1_id);
        $this->addChatMember($chat_id, $user2_id);

        return $chat_id;
    }

    // Получение списка всех пользователей
    public function get_all_users() {
        $stmt = $this->sql_connection->query("SELECT id_user, login FROM Users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получение данных пользователя по его ID
    public function get_user_by_id($user_id) {
        $stmt = $this->sql_connection->prepare("SELECT * FROM Users WHERE id_user = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Удаление сообщения из базы данных
    public function deleteMessage($message_id, $user_id) {
        $stmt = $this->sql_connection->prepare("
            DELETE FROM Messages WHERE id_message = :message_id
        ");
        $stmt->bindParam(':message_id', $message_id);
        $stmt->execute();
        return true;
    }
}
?>
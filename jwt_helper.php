<?php
require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper {
    // Секретный ключ для подписи и проверки JWT-токенов
    private static $secret_key = '5f4dcc3b5aa765d61d8327deb882cf99';
    
    // Метод для генерации JWT-токена
    public static function generateToken($user_id) {
        // Формируем полезную нагрузку (payload) токена
        $payload = [
            'iss' => 'myproject', // Издатель токена
            'aud' => 'myproject', // Аудитория токена
            'iat' => time(), // Время выпуска токена (в формате Unix timestamp)
            'exp' => time() + 3600, // Время истечения срока действия токена (1 час)
            'user_id' => $user_id // ID пользователя
        ];
        // Кодируем и возвращаем токен с использованием секретного ключа и алгоритма HS256
        return JWT::encode($payload, self::$secret_key, 'HS256');
    }
    
    // Метод для проверки и валидации JWT-токена
    public static function validateToken($token) {
        try {
            // Декодируем токен, используя секретный ключ и алгоритм HS256
            $decoded = JWT::decode($token, new Key(self::$secret_key, 'HS256'));
            // Возвращаем ID пользователя из токена
            return $decoded->user_id;
        } catch (\Exception $e) {
            // Если произошла ошибка (например, токен недействителен или истек), возвращаем false
            return false;
        }
    }
}
?>
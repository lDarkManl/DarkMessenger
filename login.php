<?php
require 'database.php';
require 'jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $db_conn = DarkMessager::get_instance("dark_messager", "root");
    $user = $db_conn->get_user($username);
    
    if ($user && password_verify($password, $user['password'])) {
        session_start();
        $token = JwtHelper::generateToken($user['id_user']);
        setcookie('auth_token', $token, time() + 3600, '/');
        $_SESSION['token'] = $token;
        $_SESSION['user_id'] = $user['id_user'];
        header("Location: messages.php");
        exit();
    } else {
        echo "Неправильное имя пользователя или пароль";
    }
}
?>
<?php
require 'database.php';
require 'jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $db_conn = DarkMessager::get_instance("dark_messager", "root");
    $user = $db_conn->register($username, $password);
    
    if ($user) {
        $token = JwtHelper::generateToken($user['id']);
        setcookie('auth_token', $token, time() + 3600, '/');
        setcookie('user_id', $user['id_user'], time() + 3600, '/');
        header("Location: messages.php");
        exit();
    } else {
        echo "Произошла ошибка. Попробуйте еще раз.";
    }
}
?>
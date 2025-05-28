<?php
require 'database.php';
require 'jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $db_conn = DarkMessager::get_instance("dark_messager", "root");
    $user = $db_conn->register($username, $password);
    
    if ($user) {
        session_start();
        $token = JwtHelper::generateToken($user['id']);
        setcookie('auth_token', $token, time() + 3600, '/');
        $_SESSION['token'] = $token;
        $_SESSION['user_id'] = $user['id'];
        header("Location: messages.php");
        exit();
    } else {
        echo "Error occurred. Please try again.";
    }
}
?>
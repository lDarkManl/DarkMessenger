<?php
session_start();
require 'jwt_helper.php';

if (!isset($_COOKIE['auth_token']) || !JwtHelper::validateToken($_COOKIE['auth_token'])) {
    header('Location: login.html');
    exit();
} else {
    header('Location: messages.php');
    exit();
}
?>
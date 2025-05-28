<?php
session_start();
require 'jwt_helper.php';

if (!isset($_SESSION['token']) || !JwtHelper::validateToken($_SESSION['token'])) {
    header('Location: login.html');
    exit();
} else {
    header('Location: messages.php');
    exit();
}
?>
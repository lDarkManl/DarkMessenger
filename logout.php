<?php
// Запускаем сессию
session_start();

// Удаляем все данные сессии
session_unset();
session_destroy();

// Перенаправляем пользователя на страницу входа
header('Location: login.html');
exit();
?>
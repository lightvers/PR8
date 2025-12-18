<?php
session_start();
include("../settings/connect_datebase.php");
include("../settings/config.php");

$login = $_POST['login'];
$password = $_POST['password'];

$password = password_hash($password, PASSWORD_DEFAULT);

//ищем пользователя
$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='".$mysqli->real_escape_string($login)."'");
$id = -1;

if($user_read = $query_user->fetch_row()) {
    echo $id;
} else {
    //вставляем с датой изменения пароля
    $current_time = date('Y-m-d H:i:s');
    $mysqli->query("INSERT INTO `users`(`login`, `password`, `roll`, `password_changed_at`) 
                    VALUES ('".$mysqli->real_escape_string($login)."', '".$mysqli->real_escape_string($password)."', 0, '".$current_time."')");
    
    $query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='".$mysqli->real_escape_string($login)."'");
    $user_new = $query_user->fetch_row();
    $id = $user_new[0];
    
    if($id != -1) {
        // Генерируем session_token
        $session_token = bin2hex(random_bytes(32));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Обновляем запись пользователя
        $update_query = "UPDATE `users` SET 
                        `session_token` = '".$mysqli->real_escape_string($session_token)."',
                        `last_activity` = '".$current_time."',
                        `user_agent` = '".$mysqli->real_escape_string($user_agent)."'
                        WHERE `id` = $id";
        
        $mysqli->query($update_query);
        
        // Устанавливаем сессию
        $_SESSION['user'] = $id;
        $_SESSION['session_token'] = $session_token;
    }
    
    echo $id;
}
?>
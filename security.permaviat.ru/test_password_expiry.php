<?php
// Простая проверка истечения пароля
function checkPasswordExpiry($password_changed_at) {
    $changed_date = new DateTime($password_changed_at);
    $current_date = new DateTime();
    $interval = $changed_date->diff($current_date);
    return $interval->days >= 1;
}

// Тестовые данные
$test_date = date('Y-m-d H:i:s', strtotime('-2 days')); // Пароль изменен 2 дня назад
if(checkPasswordExpiry($test_date)) {
    echo "Пароль ИСТЕК (прошло 2 дня)";
} else {
    echo "Пароль ДЕЙСТВИТЕЛЕН";
}
?>
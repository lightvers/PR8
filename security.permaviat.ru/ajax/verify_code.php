<?php
session_start();

// Исправляем пути подключения
include("../settings/connect_datebase.php");
include("../settings/config.php");

if(!isset($_SESSION['temp_user_id']) || !isset($_SESSION['auth_code'])) {
    echo "session_expired";
    exit();
}

// Функции для работы с геолокацией
function getLocationByIP($ip) {
    // Для тестирования на localhost возвращаем тестовые данные
    if($ip == '127.0.0.1' || $ip == '::1') {
        return [
            'city' => 'Perm',
            'country' => 'Russia',
            'lat' => 58.0105,
            'lon' => 56.2502,
            'ip' => $ip
        ];
    }
    
    // Реальная реализация для production
    $url = "https://freegeoip.app/json/" . $ip;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if($response) {
        $data = json_decode($response, true);
        return [
            'city' => $data['city'] ?? 'Unknown',
            'country' => $data['country_name'] ?? 'Unknown',
            'lat' => $data['latitude'] ?? 0,
            'lon' => $data['longitude'] ?? 0,
            'ip' => $data['ip'] ?? $ip
        ];
    }
    
    return null;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371;
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);
    
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    
    return $angle * $earthRadius;
}

function checkPasswordExpiry($password_changed_at) {
    $changed_date = new DateTime($password_changed_at);
    $current_date = new DateTime();
    $interval = $changed_date->diff($current_date);
    return $interval->days >= 1; // Пароль истекает через 1 день
}

$code = $_POST['code'] ?? '';
$current_time = time();

if($current_time > $_SESSION['code_expire']) {
    echo "expired";
    exit();
}

if($code == $_SESSION['auth_code']) {
    $user_id = $_SESSION['temp_user_id'];
    
    // Используем подготовленные запросы для безопасности
    $stmt = $mysqli->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Проверяем истечение пароля
        $password_expired = checkPasswordExpiry($user['password_changed_at']);
        if($password_expired) {
            $_SESSION['password_expired'] = true;
            $_SESSION['temp_user_id'] = $user_id;
            echo "password_expired";
            exit();
        }
        
        // Проверяем местоположение
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $current_location = getLocationByIP($ip_address);
        
        $location_check_required = false;
        if(!empty($user['last_location_lat']) && !empty($user['last_location_lon']) && $current_location) {
            $distance = calculateDistance(
                $user['last_location_lat'], 
                $user['last_location_lon'],
                $current_location['lat'], 
                $current_location['lon']
            );
            
            if($distance > 100) { // 100 км порог
                $location_check_required = true;
                $location_code = sprintf("%06d", random_int(0, 999999));
                
                $_SESSION['location_check'] = true;
                $_SESSION['location_code'] = $location_code;
                $_SESSION['location_code_expire'] = time() + 300;
                $_SESSION['current_location'] = $current_location;
                $_SESSION['distance_km'] = round($distance, 2);
                
                // Отправка email
                $subject = 'Проверка нового местоположения';
                $message = 'Обнаружен вход с нового местоположения.' . "\r\n";
                $message .= 'Расстояние от предыдущего входа: ' . round($distance, 2) . ' км' . "\r\n";
                $message .= 'Город: ' . $current_location['city'] . ', ' . $current_location['country'] . "\r\n";
                $message .= 'Код для подтверждения: ' . $location_code . "\r\n";
                $message .= 'Код действителен 5 минут.';
                $headers = 'From: nastya28042020@yandex.ru' . "\r\n" .
                           'Content-Type: text/plain; charset=utf-8';
                
                mail($user['login'], $subject, $message, $headers);
                
                echo "location_check_required";
                exit();
            }
        }
        
        // Продолжение успешной авторизации...
        $session_token = bin2hex(random_bytes(32));
        $current_time_db = date('Y-m-d H:i:s');
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Используем подготовленный запрос для обновления
        $update_stmt = $mysqli->prepare("UPDATE `users` SET 
            `session_token` = ?,
            `last_activity` = ?,
            `user_agent` = ?,
            `ip_address` = ?,
            `last_location_city` = ?,
            `last_location_country` = ?,
            `last_location_lat` = ?,
            `last_location_lon` = ?,
            `last_location_time` = ?
            WHERE `id` = ?");
        
        $update_stmt->bind_param(
            "ssssssddss", 
            $session_token, 
            $current_time_db,
            $user_agent,
            $ip_address,
            $current_location['city'],
            $current_location['country'],
            $current_location['lat'],
            $current_location['lon'],
            $current_time_db,
            $user_id
        );
        
        $update_stmt->execute();
        
        $_SESSION['user'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        $_SESSION['user_role'] = $user['roll'];
        $_SESSION['session_token'] = $session_token;
        
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['auth_code']);
        unset($_SESSION['code_expire']);
        unset($_SESSION['login_email']);
        
        if($user['roll'] == 0) {
            echo "redirect_user";
        } else if($user['roll'] == 1) {
            echo "redirect_admin";
        } else {
            echo "redirect_index";
        }
    } else {
        echo "invalid";
    }
} else {
    echo "invalid";
}
?>
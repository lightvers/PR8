<?php
// Упрощенная функция для локального тестирования
function getTestLocation() {
    return [
        'city' => 'Perm',
        'country' => 'Russia',
        'lat' => 58.0105,
        'lon' => 56.2502,
        'ip' => '127.0.0.1'
    ];
}

function calculateTestDistance($lat1, $lon1, $lat2, $lon2) {
    // Расстояние между Пермью и Москвой ~1150 км
    if($lat1 == 58.0105 && $lon1 == 56.2502 && $lat2 == 55.7558 && $lon2 == 37.6173) {
        return 1150;
    }
    return 50; // Меньше порога
}

// Тест
$old_location = ['lat' => 58.0105, 'lon' => 56.2502]; // Пермь
$new_location = ['lat' => 55.7558, 'lon' => 37.6173]; // Москва

$distance = calculateTestDistance(
    $old_location['lat'], $old_location['lon'],
    $new_location['lat'], $new_location['lon']
);

if($distance > 100) {
    echo "Требуется проверка местоположения! Расстояние: {$distance} км";
} else {
    echo "Местоположение безопасно. Расстояние: {$distance} км";
}
?>
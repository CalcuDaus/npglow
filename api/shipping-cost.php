<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/shipping-helper.php';

$province = trim($_POST['province'] ?? 'DKI Jakarta');
$city = trim($_POST['city'] ?? 'Jakarta Barat');
$courier = trim($_POST['courier'] ?? 'jnt');
$service = trim($_POST['service'] ?? 'EZ');
$subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.0;
$weight = isset($_POST['weight']) ? (int)$_POST['weight'] : 350;

$rate = NPGLOW_Shipping::calculate_rate($conn, $province, $city, $courier, $service, $subtotal, $weight);

echo json_encode([
    'success' => true,
    'rate' => $rate,
    'grand_total' => $subtotal + $rate['final_cost'],
    'formatted_grand_total' => 'Rp ' . number_format($subtotal + $rate['final_cost'], 0, ',', '.')
]);
exit();

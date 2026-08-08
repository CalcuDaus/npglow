<?php
// api/expert-status.php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';

header('Content-Type: application/json');

$status = get_expert_operational_status($conn);

echo json_encode([
    'expert_online' => $status['is_online'],
    'online_count' => $status['online_count'],
    'is_in_hours' => $status['is_in_hours'],
    'status_code' => $status['status_code'],
    'status_label' => $status['status_label'],
    'status_message' => $status['status_message'],
    'badge_class' => $status['badge_class'],
    'dot_class' => $status['dot_class'],
    'schedule_text' => $status['schedule_text'],
    'offline_message' => $status['offline_message'],
    'server_time' => $status['server_time']
]);
?>

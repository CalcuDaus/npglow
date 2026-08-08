<?php
// includes/settings-helper.php

// Ensure time zone is set to Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

/**
 * Get single setting value by key
 */
function get_setting($conn = null, $key = '', $default = '') {
    if (!$conn) {
        global $conn;
    }
    if (!$conn) return $default;

    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? $res['setting_value'] : $default;
}

/**
 * Get all settings as key-value associative array
 */
function get_all_settings($conn = null) {
    if (!$conn) {
        global $conn;
    }

    $settings = [
        'expert_start_time' => '08:00',
        'expert_end_time' => '21:00',
        'expert_work_days' => '1,2,3,4,5,6,7',
        'expert_auto_schedule' => '1',
        'expert_offline_message' => 'Tim ahli melayani konsultasi setiap hari pukul 08:00 - 21:00 WIB. Gunakan AI Assistant untuk respon instan.'
    ];
    
    if (!$conn) return $settings;

    $res = $conn->query("SELECT setting_key, setting_value FROM settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

/**
 * Save single setting
 */
function save_setting($conn = null, $key = '', $value = '') {
    if (!$conn) {
        global $conn;
    }
    if (!$conn) return false;

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if (!$stmt) return false;
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

/**
 * Get comprehensive operational and online status for Expert Consultation
 */
function get_expert_operational_status($conn = null) {
    if (!$conn) {
        global $conn;
    }

    $settings = get_all_settings($conn);
    
    $startTime = $settings['expert_start_time'] ?? '08:00';
    $endTime = $settings['expert_end_time'] ?? '21:00';
    $workDaysStr = $settings['expert_work_days'] ?? '1,2,3,4,5,6,7';
    $workDays = array_filter(array_map('trim', explode(',', $workDaysStr)));
    $autoSchedule = ($settings['expert_auto_schedule'] ?? '1') === '1';
    $offlineMessage = $settings['expert_offline_message'] ?? 'Tim ahli melayani konsultasi pukul 08:00 - 21:00 WIB.';
    
    // Day of week: 1 (Monday) to 7 (Sunday)
    $currentDay = (int)date('N');
    $currentTime = date('H:i');
    
    // Check if current time is within operational hours
    $isInHours = true;
    if ($autoSchedule) {
        $isDayActive = in_array((string)$currentDay, $workDays);
        $isTimeActive = ($currentTime >= $startTime && $currentTime <= $endTime);
        $isInHours = ($isDayActive && $isTimeActive);
    }
    
    // Format human-friendly schedule text
    $dayNames = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
    ];
    
    if (count($workDays) === 7) {
        $daysText = 'Setiap Hari (Senin - Minggu)';
    } elseif (count($workDays) === 5 && !in_array('6', $workDays) && !in_array('7', $workDays)) {
        $daysText = 'Senin - Jumat';
    } else {
        $named = array_map(function($d) use ($dayNames) { return $dayNames[(int)$d] ?? ''; }, $workDays);
        $daysText = implode(', ', array_filter($named));
    }
    $scheduleText = "{$daysText} • {$startTime} - {$endTime} WIB";
    
    // Check active online experts (active within last 90 seconds and is_online = 1)
    $onlineCount = 0;
    if ($conn) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as online_count 
            FROM users 
            WHERE role = 'expert' 
            AND is_online = 1 
            AND last_active >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
        ");
        if ($stmt) {
            $stmt->execute();
            $onlineCount = (int)($stmt->get_result()->fetch_assoc()['online_count'] ?? 0);
        }
    }
    
    // Determine status
    if (!$isInHours) {
        $statusCode = 'outside_hours';
        $statusLabel = 'Tutup (Di Luar Jam Kerja)';
        $badgeClass = 'bg-amber-50 text-amber-700 border border-amber-200';
        $dotClass = 'bg-amber-500';
        $isExpertOnline = false;
        $statusMessage = "Buka kembali pukul {$startTime} WIB ({$scheduleText})";
    } elseif ($onlineCount > 0) {
        $statusCode = 'online';
        $statusLabel = 'Online Sekarang';
        $badgeClass = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
        $dotClass = 'bg-emerald-500 pulse-ring';
        $isExpertOnline = true;
        $statusMessage = 'Tim Ahli siap melayani konsultasi Anda sekarang.';
    } else {
        $statusCode = 'expert_offline';
        $statusLabel = 'Sedang Offline';
        $badgeClass = 'bg-gray-100 text-gray-500 border border-gray-200';
        $dotClass = 'bg-gray-400';
        $isExpertOnline = false;
        $statusMessage = 'Tim Ahli sedang istirahat / belum standby. Anda tetap dapat mengirim chat atau menggunakan AI Assistant.';
    }
    
    return [
        'is_in_hours' => $isInHours,
        'is_online' => $isExpertOnline,
        'online_count' => $onlineCount,
        'status_code' => $statusCode,
        'status_label' => $statusLabel,
        'status_message' => $statusMessage,
        'badge_class' => $badgeClass,
        'dot_class' => $dotClass,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'work_days' => $workDays,
        'auto_schedule' => $autoSchedule,
        'schedule_text' => $scheduleText,
        'offline_message' => $offlineMessage,
        'server_time' => date('H:i:s, d M Y')
    ];
}
?>

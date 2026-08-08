<?php
/**
 * NPGLOW Reseller Helper
 * Provides utility functions for the reseller system:
 * referral validation, store lookup, distance calculation, etc.
 */

/**
 * Haversine formula — calculate distance between two GPS coordinates (in km)
 */
function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

/**
 * Validate a referral code and return the store data, or null
 */
function validate_referral_code($conn, $code) {
    $code = trim($code);
    if (empty($code)) return null;

    $stmt = $conn->prepare("
        SELECT rs.*, u.name as reseller_name, u.email as reseller_email
        FROM reseller_stores rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.referral_code = ? AND rs.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ?: null;
}

/**
 * Get the reseller store that a user is linked to via referral.
 * Returns store data or null (null = user is linked to the main/owner store).
 */
function get_user_reseller_store($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT rs.*, u.name as reseller_name
        FROM users cu
        JOIN reseller_stores rs ON cu.referred_by = rs.user_id
        JOIN users u ON rs.user_id = u.id
        WHERE cu.id = ? AND rs.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ?: null;
}

/**
 * Get the reseller store owned by a specific reseller user_id
 */
function get_reseller_store_by_user($conn, $resellerUserId) {
    $stmt = $conn->prepare("
        SELECT rs.*, u.name as reseller_name, u.email as reseller_email
        FROM reseller_stores rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $resellerUserId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ?: null;
}

/**
 * Get products for a specific reseller store.
 * Returns product data with reseller's custom price and stock.
 */
function get_reseller_products($conn, $resellerStoreId) {
    $stmt = $conn->prepare("
        SELECT p.*, rp.custom_price, rp.stock, rp.is_available,
               COALESCE(rp.custom_price, p.price) as display_price
        FROM reseller_products rp
        JOIN products p ON rp.product_id = p.id
        WHERE rp.reseller_store_id = ? AND rp.is_available = 1
        ORDER BY p.id DESC
    ");
    $stmt->bind_param("i", $resellerStoreId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all active reseller stores, sorted by distance from a user location.
 * If no user coordinates provided, returns sorted by store name.
 */
function get_nearest_resellers($conn, $userLat = null, $userLng = null, $limit = 20) {
    $stmt = $conn->prepare("
        SELECT rs.*, u.name as reseller_name
        FROM reseller_stores rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.is_active = 1
        ORDER BY rs.store_name ASC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate distances if user coordinates are provided
    if ($userLat !== null && $userLng !== null) {
        foreach ($stores as &$store) {
            if ($store['latitude'] && $store['longitude']) {
                $store['distance_km'] = round(haversine_distance(
                    $userLat, $userLng,
                    (float)$store['latitude'], (float)$store['longitude']
                ), 1);
            } else {
                $store['distance_km'] = null;
            }
        }
        unset($store);

        // Sort: stores with distance first (ascending), then stores without distance
        usort($stores, function ($a, $b) {
            if ($a['distance_km'] === null && $b['distance_km'] === null) return 0;
            if ($a['distance_km'] === null) return 1;
            if ($b['distance_km'] === null) return -1;
            return $a['distance_km'] <=> $b['distance_km'];
        });
    }

    return $stores;
}

/**
 * Link a user to a reseller via referral code.
 * Returns true on success, error message string on failure.
 */
function link_user_to_reseller($conn, $userId, $referralCode) {
    $store = validate_referral_code($conn, $referralCode);
    if (!$store) {
        return 'Kode referral tidak valid atau toko tidak aktif.';
    }

    // Don't allow linking to own store
    if ($store['user_id'] == $userId) {
        return 'Anda tidak bisa menggunakan kode referral toko sendiri.';
    }

    $stmt = $conn->prepare("UPDATE users SET referral_code_used = ?, referred_by = ? WHERE id = ?");
    $stmt->bind_param("sii", $referralCode, $store['user_id'], $userId);
    if ($stmt->execute()) {
        return true;
    }
    return 'Gagal memperbarui referral. Silakan coba lagi.';
}

/**
 * Unlink a user from any reseller (back to main store).
 */
function unlink_user_from_reseller($conn, $userId) {
    $stmt = $conn->prepare("UPDATE users SET referral_code_used = NULL, referred_by = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    return $stmt->execute();
}

/**
 * Generate a unique referral code for a new reseller store.
 */
function generate_referral_code($conn, $prefix = 'NP') {
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = strtoupper($prefix . '-' . substr(bin2hex(random_bytes(3)), 0, 6));
        $stmt = $conn->prepare("SELECT id FROM reseller_stores WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return $code;
        }
    }
    // Fallback with timestamp
    return strtoupper($prefix . '-' . substr(md5(microtime()), 0, 6));
}

/**
 * Generate a URL-friendly slug from a store name.
 */
function generate_store_slug($conn, $storeName) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $storeName), '-'));
    $baseSlug = $slug;
    $counter = 1;

    while (true) {
        $stmt = $conn->prepare("SELECT id FROM reseller_stores WHERE store_slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

/**
 * Get reseller stats (order count, revenue, customer count)
 */
function get_reseller_stats($conn, $resellerStoreId) {
    // Total orders
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE reseller_store_id = ?");
    $stmt->bind_param("i", $resellerStoreId);
    $stmt->execute();
    $totalOrders = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Total revenue (from paid orders)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE reseller_store_id = ? AND payment_status = 'paid'");
    $stmt->bind_param("i", $resellerStoreId);
    $stmt->execute();
    $totalRevenue = (float)($stmt->get_result()->fetch_assoc()['revenue'] ?? 0);

    // Linked customers
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM users 
        WHERE referred_by = (SELECT user_id FROM reseller_stores WHERE id = ?)
    ");
    $stmt->bind_param("i", $resellerStoreId);
    $stmt->execute();
    $customerCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Products listed
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reseller_products WHERE reseller_store_id = ? AND is_available = 1");
    $stmt->bind_param("i", $resellerStoreId);
    $stmt->execute();
    $productCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return [
        'total_orders'   => $totalOrders,
        'total_revenue'  => $totalRevenue,
        'customer_count' => $customerCount,
        'product_count'  => $productCount,
    ];
}

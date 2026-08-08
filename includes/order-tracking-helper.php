<?php
// Order Tracking & Status Helper for NPGLOW

if (!function_exists('add_order_tracking_log')) {
    /**
     * Add a milestone log for an order tracking timeline
     */
    function add_order_tracking_log($conn, $orderId, $statusKey, $title, $description, $location = 'NPGLOW System', $customTime = null) {
        $orderId = (int)$orderId;
        $statusKey = trim($statusKey);
        $title = trim($title);
        $description = trim($description);
        $location = trim($location ?: 'NPGLOW System');
        $createdAt = $customTime ?: date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO order_tracking_logs (order_id, status_key, title, description, location, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $orderId, $statusKey, $title, $description, $location, $createdAt);
        return $stmt->execute();
    }
}

if (!function_exists('get_order_tracking_logs')) {
    /**
     * Retrieve all tracking logs for an order sorted by newest first or chronological
     */
    function get_order_tracking_logs($conn, $orderId, $direction = 'DESC') {
        $orderId = (int)$orderId;
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $stmt = $conn->prepare("SELECT * FROM order_tracking_logs WHERE order_id = ? ORDER BY created_at {$direction}, id {$direction}");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('get_order_status_info')) {
    /**
     * Return comprehensive metadata, two-tone badge classes, and step indices for an order status
     */
    function get_order_status_info($orderStatus, $paymentStatus = 'pending') {
        $orderStatus = strtolower(trim((string)$orderStatus));
        $paymentStatus = strtolower(trim((string)$paymentStatus));

        // Default metadata
        $meta = [
            'key' => 'unpaid',
            'label' => 'Belum Bayar',
            'badge_class' => 'bg-blue-50 text-primary border border-blue-200',
            'dot_class' => 'bg-primary',
            'text_class' => 'text-primary',
            'step' => 1,
            'summary' => 'Menunggu penyelesaian pembayaran',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        ];

        if ($orderStatus === 'cancelled' || $paymentStatus === 'rejected') {
            return [
                'key' => 'cancelled',
                'label' => 'Dibatalkan',
                'badge_class' => 'bg-rose-50 text-rose-700 border border-rose-200',
                'dot_class' => 'bg-rose-500',
                'text_class' => 'text-rose-600',
                'step' => 0,
                'summary' => 'Pesanan dibatalkan atau pembayaran ditolak',
                'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>'
            ];
        }

        if ($orderStatus === 'delivered') {
            return [
                'key' => 'delivered',
                'label' => 'Selesai',
                'badge_class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'dot_class' => 'bg-emerald-500',
                'text_class' => 'text-emerald-600',
                'step' => 5,
                'summary' => 'Pesanan telah diterima oleh pembeli',
                'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            ];
        }

        if ($orderStatus === 'shipped') {
            return [
                'key' => 'shipped',
                'label' => 'Sedang Dikirim',
                'badge_class' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'dot_class' => 'bg-indigo-500',
                'text_class' => 'text-indigo-600',
                'step' => 4,
                'summary' => 'Paket dalam perjalanan ekspedisi ke alamat tujuan',
                'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>'
            ];
        }

        if ($orderStatus === 'processing' || $paymentStatus === 'paid') {
            return [
                'key' => 'processing',
                'label' => 'Sedang Dikemas',
                'badge_class' => 'bg-amber-50 text-amber-800 border border-amber-200',
                'dot_class' => 'bg-amber-500',
                'text_class' => 'text-amber-600',
                'step' => 3,
                'summary' => 'Pembayaran terverifikasi, tim gudang sedang menyiapkan produk',
                'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'
            ];
        }

        if ($paymentStatus === 'waiting_verification') {
            return [
                'key' => 'unpaid',
                'label' => 'Menunggu Verifikasi',
                'badge_class' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'dot_class' => 'bg-amber-500',
                'text_class' => 'text-amber-600',
                'step' => 2,
                'summary' => 'Bukti pembayaran telah dikirim, menunggu persetujuan admin',
                'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
            ];
        }

        return $meta;
    }
}

if (!function_exists('mark_order_shipped')) {
    /**
     * Mark an order as shipped, save tracking number, and add realistic milestone logs
     */
    function mark_order_shipped($conn, $orderId, $courier, $trackingNumber, $destinationCity = '') {
        $orderId = (int)$orderId;
        $courier = trim($courier ?: 'J&T');
        $trackingNumber = trim($trackingNumber);
        $destinationCity = trim($destinationCity ?: 'Kota Tujuan');

        $stmt = $conn->prepare("UPDATE orders SET order_status = 'shipped', tracking_number = ?, shipping_courier = ?, shipped_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $trackingNumber, $courier, $orderId);
        $success = $stmt->execute();

        if ($success) {
            // Milestone: Handover to courier
            add_order_tracking_log(
                $conn,
                $orderId,
                'shipped',
                "Paket Diserahkan ke Ekspedisi ({$courier})",
                "Pesanan telah dipacking rapi dan diserahkan ke pihak {$courier} dengan Nomor Resi: {$trackingNumber}.",
                "Drop Point {$courier} Jakarta Barat"
            );

            // Milestone: Origin Transit Sort Hub
            $t2 = date('Y-m-d H:i:s', strtotime('+20 minutes'));
            add_order_tracking_log(
                $conn,
                $orderId,
                'in_transit',
                "Paket Tiba di Fasilitas Sortir",
                "Paket telah dipindai dan diproses di Hub Sortir Gateway Jakarta untuk diteruskan ke {$destinationCity}.",
                "Jakarta Gateway Sort Center",
                $t2
            );

            return true;
        }
        return false;
    }
}

if (!function_exists('mark_order_delivered')) {
    /**
     * Mark order as delivered and add final completion log
     */
    function mark_order_delivered($conn, $orderId, $receivedBy = '') {
        $orderId = (int)$orderId;
        $receivedBy = trim($receivedBy);

        $stmt = $conn->prepare("UPDATE orders SET order_status = 'delivered', delivered_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $success = $stmt->execute();

        if ($success) {
            $desc = !empty($receivedBy) 
                ? "Paket telah diterima dengan baik oleh yang bersangkutan ({$receivedBy}). Terima kasih telah berbelanja di NPGLOW Official!" 
                : "Paket telah sampai di alamat tujuan dan diterima dengan baik. Terima kasih telah berbelanja di NPGLOW Official!";

            add_order_tracking_log(
                $conn,
                $orderId,
                'delivered',
                "Pesanan Telah Diterima (Selesai)",
                $desc,
                "Alamat Penerima"
            );
            return true;
        }
        return false;
    }
}

// Aliases for convenience
if (!function_exists('mark_order_as_shipped')) {
    function mark_order_as_shipped($conn, $orderId, $trackingNumber, $courier = 'J&T', $desc = '', $location = '') {
        return mark_order_shipped($conn, $orderId, $courier, $trackingNumber, $location);
    }
}

if (!function_exists('mark_order_as_delivered')) {
    function mark_order_as_delivered($conn, $orderId, $receivedBy = '') {
        return mark_order_delivered($conn, $orderId, $receivedBy);
    }
}

if (!function_exists('get_order_tracking_history')) {
    function get_order_tracking_history($conn, $orderId, $direction = 'DESC') {
        return get_order_tracking_logs($conn, $orderId, $direction);
    }
}

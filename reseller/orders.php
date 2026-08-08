<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth-helper.php';
require_once __DIR__ . '/../includes/reseller-helper.php';
require_once __DIR__ . '/../includes/order-tracking-helper.php';
require_once __DIR__ . '/../includes/icon-helper.php';
require_once __DIR__ . '/../includes/image-helper.php';

guard_reseller_only();

$userId = (int)$_SESSION['user_id'];
$store = get_reseller_store_by_user($conn, $userId);
if (!$store) {
    header("Location: index.php");
    exit();
}
$storeId = (int)$store['id'];

$successMsg = '';
$errorMsg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    // Verify order belongs to this reseller store
    $stmt = $conn->prepare("SELECT id, user_id FROM orders WHERE id = ? AND reseller_store_id = ?");
    $stmt->bind_param("ii", $orderId, $storeId);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();

    if (!$orderData) {
        $errorMsg = "Pesanan tidak ditemukan atau bukan milik toko Anda.";
    } else {
        $customerUserId = (int)$orderData['user_id'];

        if ($action === 'approve_payment') {
            $uStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', status = 'completed' WHERE id = ?");
            $uStmt->bind_param("i", $orderId);
            $uStmt->execute();

            $userStmt = $conn->prepare("UPDATE users SET has_purchased = 1 WHERE id = ?");
            $userStmt->bind_param("i", $customerUserId);
            $userStmt->execute();

            add_order_tracking_log(
                $conn,
                $orderId,
                'processing',
                'Pembayaran Terverifikasi oleh Reseller',
                'Pembayaran telah diverifikasi oleh ' . $store['store_name'] . '. Pesanan sedang dikemas.',
                $store['city'] ?: 'Toko Reseller'
            );

            $successMsg = "Pembayaran pesanan berhasil disetujui!";
        } elseif ($action === 'reject_payment') {
            $reason = trim($_POST['reject_reason'] ?? 'Bukti pembayaran tidak valid / belum masuk.');
            $uStmt = $conn->prepare("UPDATE orders SET payment_status = 'rejected', admin_note = ? WHERE id = ?");
            $uStmt->bind_param("si", $reason, $orderId);
            $uStmt->execute();

            add_order_tracking_log(
                $conn,
                $orderId,
                'rejected',
                'Pembayaran Ditolak',
                'Catatan: ' . $reason,
                $store['city'] ?: 'Toko Reseller'
            );

            $successMsg = "Pembayaran pesanan telah ditolak.";
        } elseif ($action === 'ship_order') {
            $trackingNumber = trim($_POST['tracking_number'] ?? '');
            $courier = trim($_POST['shipping_courier'] ?? 'J&T');
            $location = trim($_POST['location'] ?? ($store['city'] ?: 'Toko Reseller'));
            $note = trim($_POST['tracking_note'] ?? '');

            if (!empty($trackingNumber)) {
                $desc = "Paket telah diserahkan ke jasa ekspedisi {$courier} dengan nomor resi {$trackingNumber}. " . ($note ? $note : "Paket sedang dalam perjalanan menuju alamat penerima.");
                if (mark_order_as_shipped($conn, $orderId, $trackingNumber, $courier, $desc, $location)) {
                    $successMsg = "Pesanan berhasil dikirim dengan no resi: {$trackingNumber}!";
                } else {
                    $errorMsg = "Gagal memperbarui status pengiriman.";
                }
            } else {
                $errorMsg = "Nomor resi wajib diisi.";
            }
        } elseif ($action === 'deliver_order') {
            $note = trim($_POST['deliver_note'] ?? 'Paket telah diterima oleh pembeli.');
            if (mark_order_as_delivered($conn, $orderId, $note)) {
                $successMsg = "Pesanan berhasil ditandai sebagai Selesai / Diterima!";
            } else {
                $errorMsg = "Gagal menandai pesanan selesai.";
            }
        }
    }
}

// Filters & Search
$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT o.*, p.name as product_name, p.image_url as product_image,
           u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.user_id = u.id
    WHERE o.reseller_store_id = ?
";
$params = [$storeId];
$types = "i";

if ($statusFilter === 'waiting') {
    $sql .= " AND o.payment_status = 'waiting_verification'";
} elseif ($statusFilter === 'processing') {
    $sql .= " AND o.order_status = 'processing' AND o.payment_status = 'paid'";
} elseif ($statusFilter === 'shipped') {
    $sql .= " AND o.order_status = 'shipped'";
} elseif ($statusFilter === 'delivered') {
    $sql .= " AND o.order_status = 'delivered'";
}

if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR o.recipient_name LIKE ? OR u.name LIKE ? OR o.shipping_tracking_number LIKE ?)";
    $likeSearch = "%" . $search . "%";
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $types .= "ssss";
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Counts for tabs
$counts = [
    'all' => 0,
    'waiting' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0
];
$cStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN payment_status = 'waiting_verification' THEN 1 ELSE 0 END) as waiting_cnt,
        SUM(CASE WHEN order_status = 'processing' AND payment_status = 'paid' THEN 1 ELSE 0 END) as processing_cnt,
        SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_cnt,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_cnt
    FROM orders
    WHERE reseller_store_id = ?
");
$cStmt->bind_param("i", $storeId);
$cStmt->execute();
$cRes = $cStmt->get_result()->fetch_assoc();
if ($cRes) {
    $counts['all'] = (int)($cRes['total'] ?? 0);
    $counts['waiting'] = (int)($cRes['waiting_cnt'] ?? 0);
    $counts['processing'] = (int)($cRes['processing_cnt'] ?? 0);
    $counts['shipped'] = (int)($cRes['shipped_cnt'] ?? 0);
    $counts['delivered'] = (int)($cRes['delivered_cnt'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Masuk - Reseller NPGLOW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: '#10b981', 'primary-dark': '#059669' },
                fontFamily: { sans: ['Inter', 'sans-serif'] }
            }}
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-w-0">
            <?php include 'topbar.php'; ?>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <!-- Notifications -->
                <?php if ($successMsg): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-3">
                    <?= npglow_icon('check', 'w-5 h-5 text-emerald-600') ?>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
                <?php endif; ?>

                <!-- Header & Search -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-800">Pesanan Masuk</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Kelola pesanan customer yang memesan melalui toko Anda</p>
                    </div>

                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                        <div class="relative">
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari No. Pesanan / Nama..." class="pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-xs sm:text-sm font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none w-56 sm:w-64">
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <?php if (!empty($search)): ?>
                        <a href="orders.php?status=<?= urlencode($statusFilter) ?>" class="p-2 text-gray-400 hover:text-gray-600 bg-white border border-gray-200 rounded-xl">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Status Filter Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-3 mb-6 no-scrollbar">
                    <a href="orders.php?status=all" class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= $statusFilter === 'all' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
                        Semua (<?= $counts['all'] ?>)
                    </a>
                    <a href="orders.php?status=waiting" class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= $statusFilter === 'waiting' ? 'bg-amber-500 text-white shadow-sm' : 'bg-white text-amber-700 border border-amber-200 hover:bg-amber-50' ?>">
                        Verifikasi Pembayaran (<?= $counts['waiting'] ?>)
                    </a>
                    <a href="orders.php?status=processing" class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= $statusFilter === 'processing' ? 'bg-blue-500 text-white shadow-sm' : 'bg-white text-blue-700 border border-blue-200 hover:bg-blue-50' ?>">
                        Perlu Dikemas (<?= $counts['processing'] ?>)
                    </a>
                    <a href="orders.php?status=shipped" class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= $statusFilter === 'shipped' ? 'bg-purple-500 text-white shadow-sm' : 'bg-white text-purple-700 border border-purple-200 hover:bg-purple-50' ?>">
                        Dalam Pengiriman (<?= $counts['shipped'] ?>)
                    </a>
                    <a href="orders.php?status=delivered" class="px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition <?= $statusFilter === 'delivered' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
                        Selesai (<?= $counts['delivered'] ?>)
                    </a>
                </div>

                <!-- Orders List -->
                <?php if (empty($orders)): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 text-gray-400 flex items-center justify-center mx-auto mb-3">
                        <?= npglow_icon('package', 'w-7 h-7') ?>
                    </div>
                    <h3 class="text-base font-bold text-gray-800 mb-1">Tidak ada pesanan ditemukan</h3>
                    <p class="text-xs text-gray-400">Belum ada pesanan dengan filter yang dipilih.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($orders as $order): ?>
                    <?php
                        $statusMeta = get_order_status_info($order['order_status'] ?? 'unpaid', $order['payment_status'] ?? 'pending');
                        $totalPrice = (float)($order['total_amount'] ?? $order['price'] ?? 0);
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 transition hover:shadow-md">
                        <!-- Top Row: Order Number, Date, Status -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-100">
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono font-bold text-xs sm:text-sm text-gray-900 bg-gray-100 px-2.5 py-1 rounded-lg">
                                    <?= htmlspecialchars($order['order_number'] ?? ('#NP-' . $order['id'])) ?>
                                </span>
                                <span class="text-xs text-gray-400">•</span>
                                <span class="text-xs text-gray-500 font-medium"><?= date('d M Y, H:i', strtotime($order['order_date'])) ?> WIB</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold px-3 py-1 rounded-full <?= $statusMeta['badge_class'] ?>">
                                    <?= $statusMeta['status_label'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Main Body: Product & Shipping Details -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 py-4">
                            <!-- Product Details -->
                            <div class="flex items-start gap-3.5 md:col-span-1">
                                <?php if (!empty($order['product_image'])): ?>
                                <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-100 flex-shrink-0 border border-gray-100">
                                    <img src="../<?= htmlspecialchars($order['product_image']) ?>" alt="" class="w-full h-full object-cover">
                                </div>
                                <?php else: ?>
                                <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                    <?= npglow_icon('package', 'w-7 h-7') ?>
                                </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <h4 class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($order['product_name']) ?></h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Pemesan: <strong class="text-gray-700"><?= htmlspecialchars($order['customer_name']) ?></strong></p>
                                    <p class="text-xs font-extrabold text-emerald-600 mt-1">Total: Rp <?= number_format($totalPrice, 0, ',', '.') ?></p>
                                </div>
                            </div>

                            <!-- Recipient & Address -->
                            <div class="md:col-span-1 text-xs text-gray-600 space-y-1 bg-gray-50/70 p-3.5 rounded-xl border border-gray-100">
                                <p class="font-bold text-gray-800 flex items-center gap-1.5">
                                    📍 <?= htmlspecialchars($order['recipient_name'] ?? $order['customer_name']) ?> (<?= htmlspecialchars($order['recipient_phone'] ?? $order['customer_phone'] ?? '-') ?>)
                                </p>
                                <p class="text-gray-500 leading-relaxed">
                                    <?= htmlspecialchars($order['shipping_address'] ?? 'Alamat belum diisi') ?>
                                    <?php if (!empty($order['shipping_district'])): ?>, <?= htmlspecialchars($order['shipping_district']) ?><?php endif; ?>
                                    <?php if (!empty($order['shipping_city'])): ?>, <?= htmlspecialchars($order['shipping_city']) ?><?php endif; ?>
                                    <?php if (!empty($order['shipping_province'])): ?>, <?= htmlspecialchars($order['shipping_province']) ?><?php endif; ?>
                                </p>
                                <p class="text-[11px] font-semibold text-gray-500 pt-1">
                                    Kurir: <strong class="text-gray-700"><?= htmlspecialchars($order['shipping_courier'] ?? 'J&T') ?></strong> • Ongkir: Rp <?= number_format((float)($order['shipping_cost'] ?? 0), 0, ',', '.') ?>
                                </p>
                            </div>

                            <!-- Payment & Tracking Info -->
                            <div class="md:col-span-1 text-xs text-gray-600 space-y-2 flex flex-col justify-between">
                                <div class="bg-gray-50/70 p-3.5 rounded-xl border border-gray-100">
                                    <p class="font-semibold text-gray-700">Metode: <span class="font-bold text-gray-900"><?= strtoupper($order['payment_method'] ?? 'QRIS') ?></span></p>
                                    <?php if (!empty($order['shipping_tracking_number'])): ?>
                                    <p class="mt-1 font-semibold text-gray-700">No. Resi: <span class="font-mono font-bold text-purple-700 select-all"><?= htmlspecialchars($order['shipping_tracking_number']) ?></span></p>
                                    <?php endif; ?>
                                    <?php if (!empty($order['payment_proof'])): ?>
                                    <div class="mt-2">
                                        <button onclick="viewPaymentProof('../<?= htmlspecialchars($order['payment_proof']) ?>')" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 underline">
                                            🔍 Lihat Bukti Transfer
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 border-t border-gray-100 flex flex-wrap items-center justify-end gap-2.5">
                            <!-- Approve / Reject Payment (If waiting verification) -->
                            <?php if ($order['payment_status'] === 'waiting_verification'): ?>
                            <button onclick="approvePayment(<?= $order['id'] ?>)" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
                                <?= npglow_icon('check', 'w-4 h-4') ?> Setujui Pembayaran
                            </button>
                            <button onclick="rejectPayment(<?= $order['id'] ?>)" class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl border border-rose-200 transition">
                                Tolak Bukti
                            </button>
                            <?php endif; ?>

                            <!-- Ship Order (If paid and processing) -->
                            <?php if ($order['payment_status'] === 'paid' && ($order['order_status'] === 'processing' || empty($order['shipping_tracking_number']))): ?>
                            <button onclick="shipOrderModal(<?= $order['id'] ?>, '<?= htmlspecialchars($order['shipping_courier'] ?? 'J&T') ?>')" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
                                🚚 Input Resi & Kirim Paket
                            </button>
                            <?php endif; ?>

                            <!-- Mark as Delivered (If shipped) -->
                            <?php if ($order['order_status'] === 'shipped'): ?>
                            <button onclick="deliverOrder(<?= $order['id'] ?>)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
                                ✓ Tandai Selesai / Diterima
                            </button>
                            <?php endif; ?>

                            <!-- Public tracking link -->
                            <a href="../order-tracking.php?order_number=<?= urlencode($order['order_number'] ?? '') ?>" target="_blank" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition">
                                Cek Tracking ↗
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Hidden form for submissions -->
    <form id="actionForm" method="POST" class="hidden">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="order_id" id="formOrderId">
        <input type="hidden" name="tracking_number" id="formTrackingNumber">
        <input type="hidden" name="shipping_courier" id="formCourier">
        <input type="hidden" name="reject_reason" id="formRejectReason">
    </form>

    <script>
        function viewPaymentProof(url) {
            Swal.fire({
                title: 'Bukti Pembayaran',
                imageUrl: url,
                imageAlt: 'Bukti Pembayaran',
                showCloseButton: true,
                showConfirmButton: false,
                width: 'auto'
            });
        }

        function approvePayment(orderId) {
            Swal.fire({
                title: 'Setujui Pembayaran?',
                text: 'Status pesanan akan diubah menjadi "Sedang Dikemas".',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Setujui',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981'
            }).then(res => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'approve_payment';
                    document.getElementById('formOrderId').value = orderId;
                    document.getElementById('actionForm').submit();
                }
            });
        }

        function rejectPayment(orderId) {
            Swal.fire({
                title: 'Tolak Bukti Pembayaran',
                input: 'text',
                inputLabel: 'Alasan penolakan',
                inputPlaceholder: 'Contoh: Nominal transfer kurang / bukti tidak terbaca',
                showCancelButton: true,
                confirmButtonText: 'Tolak Pembayaran',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444'
            }).then(res => {
                if (res.isConfirmed && res.value) {
                    document.getElementById('formAction').value = 'reject_payment';
                    document.getElementById('formOrderId').value = orderId;
                    document.getElementById('formRejectReason').value = res.value;
                    document.getElementById('actionForm').submit();
                }
            });
        }

        function shipOrderModal(orderId, defaultCourier) {
            Swal.fire({
                title: 'Kirim Paket',
                html: `
                    <div class="text-left space-y-3">
                        <div>
                            <label class="text-xs font-bold text-gray-700">Ekspedisi</label>
                            <input id="swal-courier" type="text" value="${defaultCourier}" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm font-semibold">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-700">Nomor Resi Pengiriman</label>
                            <input id="swal-resi" type="text" placeholder="Contoh: JT1234567890" class="w-full mt-1 p-2.5 border border-gray-300 rounded-xl text-sm font-mono font-bold">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Simpan & Kirim',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#9333ea',
                preConfirm: () => {
                    const resi = document.getElementById('swal-resi').value.trim();
                    const courier = document.getElementById('swal-courier').value.trim();
                    if (!resi) {
                        Swal.showValidationMessage('Nomor resi wajib diisi');
                        return false;
                    }
                    return { resi, courier };
                }
            }).then(res => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'ship_order';
                    document.getElementById('formOrderId').value = orderId;
                    document.getElementById('formTrackingNumber').value = res.value.resi;
                    document.getElementById('formCourier').value = res.value.courier;
                    document.getElementById('actionForm').submit();
                }
            });
        }

        function deliverOrder(orderId) {
            Swal.fire({
                title: 'Tandai Selesai?',
                text: 'Pastikan paket telah benar-benar diterima oleh pelanggan.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Tandai Selesai',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981'
            }).then(res => {
                if (res.isConfirmed) {
                    document.getElementById('formAction').value = 'deliver_order';
                    document.getElementById('formOrderId').value = orderId;
                    document.getElementById('actionForm').submit();
                }
            });
        }
    </script>
</body>
</html>

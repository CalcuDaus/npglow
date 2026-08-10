<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$tab = $_GET['tab'] ?? 'cashflow';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Ensure dates include time for full day coverage
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

$response = ['success' => true, 'data' => []];

if ($tab === 'cashflow') {
    // 1. Summary Metrics (Only Paid Orders for Revenue)
    // We only count orders belonging to the main store (reseller_store_id IS NULL)
    $summaryStmt = $conn->prepare("
        SELECT 
            COUNT(id) as paid_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(shipping_cost), 0) as total_shipping
        FROM orders
        WHERE reseller_store_id IS NULL 
        AND payment_status = 'paid'
        AND order_date BETWEEN ? AND ?
    ");
    $summaryStmt->bind_param("ss", $startDateTime, $endDateTime);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();
    
    $paidOrders = (int)$summary['paid_orders'];
    $totalRevenue = (float)$summary['total_revenue'];
    $totalShipping = (float)$summary['total_shipping'];
    $avgOrderValue = $paidOrders > 0 ? $totalRevenue / $paidOrders : 0;
    
    $response['data']['summary'] = [
        'revenue' => $totalRevenue,
        'shipping' => $totalShipping,
        'paid_orders' => $paidOrders,
        'avg_order_value' => $avgOrderValue
    ];

    // 2. Daily Revenue Chart
    $chartStmt = $conn->prepare("
        SELECT DATE(order_date) as date, SUM(total_amount) as revenue
        FROM orders
        WHERE reseller_store_id IS NULL 
        AND payment_status = 'paid'
        AND order_date BETWEEN ? AND ?
        GROUP BY DATE(order_date)
        ORDER BY DATE(order_date) ASC
    ");
    $chartStmt->bind_param("ss", $startDateTime, $endDateTime);
    $chartStmt->execute();
    $chartRes = $chartStmt->get_result();
    $chartData = [];
    while ($row = $chartRes->fetch_assoc()) {
        $chartData[$row['date']] = (float)$row['revenue'];
    }
    
    // Fill in missing dates with 0
    $labels = [];
    $revenueData = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    while ($current <= $end) {
        $dateStr = date('Y-m-d', $current);
        $labels[] = $dateStr;
        $revenueData[] = $chartData[$dateStr] ?? 0;
        $current = strtotime('+1 day', $current);
    }
    $response['data']['chart'] = [
        'labels' => $labels,
        'revenue' => $revenueData
    ];

    // 3. Payment Methods
    $paymentStmt = $conn->prepare("
        SELECT payment_method, COUNT(*) as count 
        FROM orders 
        WHERE reseller_store_id IS NULL 
        AND payment_status = 'paid'
        AND order_date BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $paymentStmt->bind_param("ss", $startDateTime, $endDateTime);
    $paymentStmt->execute();
    $paymentRes = $paymentStmt->get_result();
    $paymentMethods = [];
    while ($row = $paymentRes->fetch_assoc()) {
        $paymentMethods[$row['payment_method']] = (int)$row['count'];
    }
    $response['data']['payment_methods'] = $paymentMethods;

    // 4. Transaction List
    $txStmt = $conn->prepare("
        SELECT order_number, order_date, total_amount, payment_method, payment_status, recipient_name
        FROM orders
        WHERE reseller_store_id IS NULL 
        AND order_date BETWEEN ? AND ?
        ORDER BY order_date DESC
        LIMIT 100
    ");
    $txStmt->bind_param("ss", $startDateTime, $endDateTime);
    $txStmt->execute();
    $response['data']['transactions'] = $txStmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($tab === 'sales') {
    // 1. Top Products
    $topProdStmt = $conn->prepare("
        SELECT p.name, p.image_url, COUNT(o.id) as qty_sold, COALESCE(SUM(o.total_amount), 0) as revenue
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.reseller_store_id IS NULL 
        AND o.payment_status = 'paid'
        AND o.order_date BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY qty_sold DESC
        LIMIT 10
    ");
    $topProdStmt->bind_param("ss", $startDateTime, $endDateTime);
    $topProdStmt->execute();
    $response['data']['top_products'] = $topProdStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2. Daily Sales Chart
    $salesChartStmt = $conn->prepare("
        SELECT DATE(order_date) as date, COUNT(id) as orders_count
        FROM orders
        WHERE reseller_store_id IS NULL 
        AND payment_status = 'paid'
        AND order_date BETWEEN ? AND ?
        GROUP BY DATE(order_date)
        ORDER BY DATE(order_date) ASC
    ");
    $salesChartStmt->bind_param("ss", $startDateTime, $endDateTime);
    $salesChartStmt->execute();
    $salesChartRes = $salesChartStmt->get_result();
    $salesChartData = [];
    while ($row = $salesChartRes->fetch_assoc()) {
        $salesChartData[$row['date']] = (int)$row['orders_count'];
    }

    $labels = [];
    $ordersData = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    while ($current <= $end) {
        $dateStr = date('Y-m-d', $current);
        $labels[] = $dateStr;
        $ordersData[] = $salesChartData[$dateStr] ?? 0;
        $current = strtotime('+1 day', $current);
    }
    $response['data']['chart'] = [
        'labels' => $labels,
        'orders' => $ordersData
    ];

    // 3. Order Status Overview
    $statusStmt = $conn->prepare("
        SELECT order_status, COUNT(*) as count
        FROM orders
        WHERE reseller_store_id IS NULL 
        AND order_date BETWEEN ? AND ?
        GROUP BY order_status
    ");
    $statusStmt->bind_param("ss", $startDateTime, $endDateTime);
    $statusStmt->execute();
    $statusRes = $statusStmt->get_result();
    $orderStatuses = [];
    while ($row = $statusRes->fetch_assoc()) {
        $orderStatuses[$row['order_status']] = (int)$row['count'];
    }
    $response['data']['order_statuses'] = $orderStatuses;

    // 4. Courier Usage
    $courierStmt = $conn->prepare("
        SELECT shipping_courier, COUNT(*) as count
        FROM orders
        WHERE reseller_store_id IS NULL 
        AND order_date BETWEEN ? AND ?
        GROUP BY shipping_courier
    ");
    $courierStmt->bind_param("ss", $startDateTime, $endDateTime);
    $courierStmt->execute();
    $courierRes = $courierStmt->get_result();
    $couriers = [];
    while ($row = $courierRes->fetch_assoc()) {
        $couriers[$row['shipping_courier']] = (int)$row['count'];
    }
    $response['data']['couriers'] = $couriers;
} elseif ($tab === 'customers') {
    // 1. Customer Growth (New Registrations)
    $growthStmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(id) as new_users
        FROM users
        WHERE role = 'user'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ");
    $growthStmt->bind_param("ss", $startDateTime, $endDateTime);
    $growthStmt->execute();
    $growthRes = $growthStmt->get_result();
    $growthData = [];
    while ($row = $growthRes->fetch_assoc()) {
        $growthData[$row['date']] = (int)$row['new_users'];
    }

    $labels = [];
    $usersData = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    while ($current <= $end) {
        $dateStr = date('Y-m-d', $current);
        $labels[] = $dateStr;
        $usersData[] = $growthData[$dateStr] ?? 0;
        $current = strtotime('+1 day', $current);
    }
    $response['data']['growth_chart'] = [
        'labels' => $labels,
        'new_users' => $usersData
    ];

    // 2. Active vs New Segment (Purchased vs Not Purchased within timeframe)
    $segmentStmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN u.id END) as active_customers,
            COUNT(DISTINCT CASE WHEN o.id IS NULL THEN u.id END) as inactive_customers
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'paid' AND o.order_date BETWEEN ? AND ?
        WHERE u.role = 'user'
    ");
    $segmentStmt->bind_param("ss", $startDateTime, $endDateTime);
    $segmentStmt->execute();
    $segmentRow = $segmentStmt->get_result()->fetch_assoc();
    $response['data']['customer_segments'] = [
        'active' => (int)$segmentRow['active_customers'],
        'inactive' => (int)$segmentRow['inactive_customers']
    ];

    // 3. Top Customers (Highest spending)
    $topCustStmt = $conn->prepare("
        SELECT 
            u.full_name as name, 
            u.email,
            COUNT(o.id) as total_orders, 
            COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u
        JOIN orders o ON u.id = o.user_id
        WHERE o.reseller_store_id IS NULL 
        AND o.payment_status = 'paid'
        AND o.order_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $topCustStmt->bind_param("ss", $startDateTime, $endDateTime);
    $topCustStmt->execute();
    $response['data']['top_customers'] = $topCustStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4. Reseller Performance Ranking
    $topResStmt = $conn->prepare("
        SELECT 
            rs.store_name, 
            rs.referral_code,
            u.full_name as owner_name,
            COUNT(o.id) as total_orders, 
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM reseller_stores rs
        JOIN users u ON rs.user_id = u.id
        LEFT JOIN orders o ON rs.id = o.reseller_store_id AND o.payment_status = 'paid' AND o.order_date BETWEEN ? AND ?
        GROUP BY rs.id
        ORDER BY total_revenue DESC, total_orders DESC
        LIMIT 10
    ");
    $topResStmt->bind_param("ss", $startDateTime, $endDateTime);
    $topResStmt->execute();
    $response['data']['top_resellers'] = $topResStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 5. Main Store vs Reseller Sales Contribution
    $contribStmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN reseller_store_id IS NULL THEN 1 END) as main_store_orders,
            COUNT(CASE WHEN reseller_store_id IS NOT NULL THEN 1 END) as reseller_orders,
            COALESCE(SUM(CASE WHEN reseller_store_id IS NULL THEN total_amount ELSE 0 END), 0) as main_store_revenue,
            COALESCE(SUM(CASE WHEN reseller_store_id IS NOT NULL THEN total_amount ELSE 0 END), 0) as reseller_revenue
        FROM orders
        WHERE payment_status = 'paid'
        AND order_date BETWEEN ? AND ?
    ");
    $contribStmt->bind_param("ss", $startDateTime, $endDateTime);
    $contribStmt->execute();
    $contribRow = $contribStmt->get_result()->fetch_assoc();
    $response['data']['contribution'] = [
        'orders' => [
            'main' => (int)$contribRow['main_store_orders'],
            'reseller' => (int)$contribRow['reseller_orders']
        ],
        'revenue' => [
            'main' => (float)$contribRow['main_store_revenue'],
            'reseller' => (float)$contribRow['reseller_revenue']
        ]
    ];
}

echo json_encode($response);

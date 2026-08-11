<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/settings-helper.php';
require_once '../includes/icon-helper.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$activeNav = 'reports';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Admin NPGLOW</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#3ca6f2', 'primary-dark': '#2b8cdb' }
                }
            }
        }
    </script>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Flatpickr (Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    

    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Print Styles */
        @media print {
            body, html { background: white !important; height: auto !important; overflow: visible !important; display: block !important; }
            header, #sidebarBackdrop, #adminSidebar, .no-print { display: none !important; }
            .print-only { display: block !important; }
            .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
            
            /* Fix cutoff issues by overriding h-screen, flex, and overflow */
            .flex-1, .h-screen, .overflow-hidden, .overflow-y-auto {
                height: auto !important;
                overflow: visible !important;
                display: block !important;
            }
            main#report-container {
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }
            /* Prevent charts and table breaking across pages weirdly */
            .bg-white { page-break-inside: avoid; margin-bottom: 20px; }
            canvas { max-width: 100% !important; height: auto !important; }
        }
        .print-container { 
            width: 100% !important; 
            max-width: none !important; 
            margin: 0 !important; 
            padding: 0 !important;
            background: white !important;
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex min-h-screen">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        <?php $pageTitle = 'Laporan NPGLOW'; include 'topbar.php'; ?>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8" id="report-container">
            
            <!-- Header Print Area (Hidden normally, shown in PDF/Print) -->
            <div id="print-header" class="print-only mb-8 border-b-2 border-slate-800 pb-4">
                <div class="flex items-center gap-4">
                    <img id="print-logo" src="../assets/images/logo_np_glow.jpeg" alt="NPGLOW Logo" class="w-16 h-16 rounded-xl object-contain">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 leading-tight">NPGLOW OFFICIAL</h1>
                        <p class="text-sm font-semibold text-slate-500">Laporan <span id="print-tab-name">Arus Kas</span></p>
                        <p class="text-xs text-slate-400 mt-1" id="print-date-range"></p>
                    </div>
                </div>
                <div class="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-100 text-sm text-slate-600" id="print-description">
                    Laporan ini memuat rekapitulasi arus kas masuk dari seluruh pesanan lunas di toko utama.
                </div>
            </div>

            <!-- Page Header & Filters -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 no-print">
                <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0 hide-scrollbar">
                    <button onclick="switchTab('cashflow')" id="tab-cashflow" class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm whitespace-nowrap transition-all duration-300 bg-primary text-white shadow-lg shadow-blue-500/30">
                        <?= npglow_icon('wallet', 'w-5 h-5') ?>
                        <span>Arus Kas</span>
                    </button>
                    <button onclick="switchTab('sales')" id="tab-sales" class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-slate-500 hover:bg-slate-200 whitespace-nowrap transition-all duration-300">
                        <?= npglow_icon('package', 'w-5 h-5') ?>
                        <span>Penjualan & Produk</span>
                    </button>
                    <button onclick="switchTab('customers')" id="tab-customers" class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-slate-500 hover:bg-slate-200 whitespace-nowrap transition-all duration-300">
                        <?= npglow_icon('users', 'w-5 h-5') ?>
                        <span>Pelanggan & Reseller</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <?= npglow_icon('calendar', 'w-4 h-4 text-slate-400') ?>
                        </div>
                        <input type="text" id="date-range" class="pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 shadow-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none min-w-[240px]" placeholder="Pilih Tanggal">
                    </div>
                    
                    <div class="relative group">
                        <button class="p-2.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:text-primary hover:border-primary transition shadow-sm flex items-center justify-center">
                            <?= npglow_icon('download', 'w-5 h-5') ?>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10 overflow-hidden transform origin-top-right scale-95 group-hover:scale-100">
                            <button onclick="window.print()" class="w-full text-left px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Print Laporan
                            </button>
                            <button onclick="exportToCSV()" class="w-full text-left px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2 border-t border-slate-100">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 4v12a1 1 0 001 1h8a1 1 0 001-1V4a1 1 0 00-1-1H6a1 1 0 00-1 1zm2 1h6v2H7V5zm0 4h6v2H7V9zm0 4h6v2H7v-2z" clip-rule="evenodd"></path></svg> Export CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loader -->
            <div id="loader" class="hidden flex-col items-center justify-center py-20">
                <div class="w-10 h-10 border-4 border-slate-200 border-t-primary rounded-full animate-spin mb-3"></div>
                <p class="text-sm font-semibold text-slate-500">Memuat data laporan...</p>
            </div>

            <!-- TAB: CASHFLOW -->
            <div id="content-cashflow" class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-20 h-20 bg-blue-50 rounded-full opacity-50 pointer-events-none"></div>
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                                <?= npglow_icon('wallet', 'w-5 h-5') ?>
                            </div>
                            <h3 class="font-bold text-slate-500 text-xs uppercase tracking-wider">Pendapatan</h3>
                        </div>
                        <p class="text-2xl lg:text-3xl font-black text-slate-800" id="cf-revenue">Rp 0</p>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-50 rounded-full opacity-50 pointer-events-none"></div>
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                <?= npglow_icon('truck', 'w-5 h-5') ?>
                            </div>
                            <h3 class="font-bold text-slate-500 text-xs uppercase tracking-wider">Total Ongkir</h3>
                        </div>
                        <p class="text-2xl lg:text-3xl font-black text-slate-800" id="cf-shipping">Rp 0</p>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-20 h-20 bg-purple-50 rounded-full opacity-50 pointer-events-none"></div>
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                                <?= npglow_icon('package', 'w-5 h-5') ?>
                            </div>
                            <h3 class="font-bold text-slate-500 text-xs uppercase tracking-wider">Pesanan Lunas</h3>
                        </div>
                        <p class="text-2xl lg:text-3xl font-black text-slate-800" id="cf-orders">0</p>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-20 h-20 bg-amber-50 rounded-full opacity-50 pointer-events-none"></div>
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                                <?= npglow_icon('activity', 'w-5 h-5') ?>
                            </div>
                            <h3 class="font-bold text-slate-500 text-xs uppercase tracking-wider">Rata-rata Order</h3>
                        </div>
                        <p class="text-2xl lg:text-3xl font-black text-slate-800" id="cf-avg">Rp 0</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Line Chart -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm lg:col-span-2">
                        <h3 class="font-bold text-slate-800 mb-4">Grafik Pendapatan</h3>
                        <div class="h-[300px] w-full">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Pie Chart -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4">Metode Pembayaran</h3>
                        <div class="h-[250px] w-full flex items-center justify-center">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Transaction Table -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800">Daftar Transaksi Lunas</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-slate-500 font-semibold uppercase text-[11px]">
                                <tr>
                                    <th class="px-5 py-3 rounded-tl-lg">No. Pesanan</th>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Pelanggan</th>
                                    <th class="px-5 py-3">Metode</th>
                                    <th class="px-5 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="cf-table-body" class="divide-y divide-slate-100">
                                <!-- Data injected via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: SALES -->
            <div id="content-sales" class="hidden space-y-6">
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Top Products -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4">Produk Terlaris</h3>
                        <div id="sales-top-products" class="space-y-4">
                            <!-- Injected via JS -->
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                            <h3 class="font-bold text-slate-800 mb-4">Tren Penjualan (Pesanan)</h3>
                            <div class="h-[250px] w-full">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                                <h3 class="font-bold text-slate-800 mb-4">Status Pesanan</h3>
                                <div class="h-[200px] w-full">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                                <h3 class="font-bold text-slate-800 mb-4">Penggunaan Kurir</h3>
                                <div class="h-[200px] w-full">
                                    <canvas id="courierChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- TAB: CUSTOMERS -->
            <div id="content-customers" class="hidden space-y-6">
                <!-- Customer Segments & Contribution -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4">Segmentasi Pelanggan</h3>
                        <div class="h-[200px] w-full flex items-center justify-center">
                            <canvas id="customerSegmentChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4">Kontribusi Penjualan (Order)</h3>
                        <div class="h-[200px] w-full flex items-center justify-center">
                            <canvas id="contributionOrderChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4">Kontribusi Pendapatan (Rp)</h3>
                        <div class="h-[200px] w-full flex items-center justify-center">
                            <canvas id="contributionRevenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Customer Growth Chart -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <h3 class="font-bold text-slate-800 mb-4">Tren Registrasi Pengguna Baru</h3>
                    <div class="h-[300px] w-full">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Customers Table -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800">Top 10 Pelanggan Setia</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase text-[10px]">
                                    <tr>
                                        <th class="px-4 py-3">Nama Pelanggan</th>
                                        <th class="px-4 py-3 text-center">Order</th>
                                        <th class="px-4 py-3 text-right">Total Belanja</th>
                                    </tr>
                                </thead>
                                <tbody id="top-customers-body" class="divide-y divide-slate-100">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Resellers Table -->
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800">Performa Reseller</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase text-[10px]">
                                    <tr>
                                        <th class="px-4 py-3">Toko Reseller</th>
                                        <th class="px-4 py-3 text-center">Order</th>
                                        <th class="px-4 py-3 text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody id="top-resellers-body" class="divide-y divide-slate-100">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

<script>
    // State
    let currentTab = 'cashflow';
    let rawData = null; // Store for CSV export
    let charts = {};

    // Date formatting helper
    const formatRp = (num) => 'Rp ' + parseInt(num).toLocaleString('id-ID');
    const formatDate = (dateStr) => {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    };

    // Initialize Flatpickr
    let dateStart = new Date();
    dateStart.setDate(dateStart.getDate() - 30);
    let dateEnd = new Date();

    const fp = flatpickr("#date-range", {
        mode: "range",
        locale: "id",
        dateFormat: "Y-m-d",
        defaultDate: [dateStart, dateEnd],
        onClose: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                dateStart = selectedDates[0];
                dateEnd = selectedDates[1];
                document.getElementById('print-date-range').innerText = `Periode: ${formatDate(dateStart)} - ${formatDate(dateEnd)}`;
                loadData();
            }
        }
    });
    
    document.getElementById('print-date-range').innerText = `Periode: ${formatDate(dateStart)} - ${formatDate(dateEnd)}`;

    // Tab Switching
    function switchTab(tab) {
        currentTab = tab;
        let title = 'Arus Kas';
        if (tab === 'sales') title = 'Penjualan & Produk';
        if (tab === 'customers') title = 'Pelanggan & Reseller';
        document.getElementById('print-tab-name').innerText = title;
        
        // Update Buttons
        const btnCf = document.getElementById('tab-cashflow');
        const btnSl = document.getElementById('tab-sales');
        const btnCu = document.getElementById('tab-customers');
        
        const activeClass = 'flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm whitespace-nowrap transition-all duration-300 bg-primary text-white shadow-lg shadow-blue-500/30';
        const inactiveClass = 'flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-slate-500 hover:bg-slate-200 whitespace-nowrap transition-all duration-300';
        
        btnCf.className = inactiveClass;
        btnSl.className = inactiveClass;
        btnCu.className = inactiveClass;
        
        document.getElementById('content-cashflow').classList.add('hidden');
        document.getElementById('content-sales').classList.add('hidden');
        document.getElementById('content-customers').classList.add('hidden');
        
        if (tab === 'cashflow') {
            document.getElementById('print-description').innerText = 'Laporan ini memuat rekapitulasi arus kas masuk dari seluruh pesanan lunas di toko utama beserta metrik pendapatan dan rincian transaksi.';
            btnCf.className = activeClass;
            document.getElementById('content-cashflow').classList.remove('hidden');
        } else if (tab === 'sales') {
            document.getElementById('print-description').innerText = 'Laporan ini memuat rekapitulasi performa penjualan produk di toko utama beserta tingkat penyelesaian pesanan dan penggunaan jasa kurir.';
            btnSl.className = activeClass;
            document.getElementById('content-sales').classList.remove('hidden');
        } else if (tab === 'customers') {
            document.getElementById('print-description').innerText = 'Laporan ini memuat rekapitulasi data pengguna baru, tingkat pelanggan aktif, serta kontribusi performa dari toko utama maupun seluruh reseller terdaftar.';
            btnCu.className = activeClass;
            document.getElementById('content-customers').classList.remove('hidden');
        }
        
        loadData();
    }

    // Chart Setup Helper
    function createChart(id, type, data, options = {}) {
        const ctx = document.getElementById(id).getContext('2d');
        if (charts[id]) charts[id].destroy();
        
        // Common defaults
        Chart.defaults.font.family = 'Inter';
        Chart.defaults.color = '#64748b';
        
        charts[id] = new Chart(ctx, { type, data, options });
    }

    // Load Data
    async function loadData() {
        // Show loader
        document.getElementById('loader').classList.remove('hidden');
        document.getElementById(`content-${currentTab}`).classList.add('hidden');

        try {
            const startStr = dateStart.toISOString().split('T')[0];
            const endStr = dateEnd.toISOString().split('T')[0];
            const res = await fetch(`../api/reports-data.php?tab=${currentTab}&start_date=${startStr}&end_date=${endStr}`);
            const result = await res.json();
            
            if (result.success) {
                rawData = result.data;
                if (currentTab === 'cashflow') {
                    renderCashflow(result.data);
                } else if (currentTab === 'sales') {
                    renderSales(result.data);
                } else if (currentTab === 'customers') {
                    renderCustomers(result.data);
                }
            }
        } catch (e) {
            console.error('Failed to load report data', e);
        } finally {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById(`content-${currentTab}`).classList.remove('hidden');
        }
    }

    // Render Cashflow UI
    function renderCashflow(data) {
        // Summaries
        document.getElementById('cf-revenue').innerText = formatRp(data.summary.revenue);
        document.getElementById('cf-shipping').innerText = formatRp(data.summary.shipping);
        document.getElementById('cf-orders').innerText = data.summary.paid_orders;
        document.getElementById('cf-avg').innerText = formatRp(data.summary.avg_order_value);

        // Revenue Chart
        createChart('revenueChart', 'line', {
            labels: data.chart.labels.map(formatDate),
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: data.chart.revenue,
                borderColor: '#3ca6f2',
                backgroundColor: 'rgba(60, 166, 242, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4] } },
                x: { grid: { display: false } }
            }
        });

        // Payment Methods Chart
        const pmLabels = Object.keys(data.payment_methods).map(k => k.toUpperCase());
        const pmData = Object.values(data.payment_methods);
        createChart('paymentChart', 'doughnut', {
            labels: pmLabels,
            datasets: [{
                data: pmData,
                backgroundColor: ['#3ca6f2', '#10b981', '#f59e0b', '#8b5cf6'],
                borderWidth: 0
            }]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '70%'
        });

        // Table
        const tbody = document.getElementById('cf-table-body');
        tbody.innerHTML = '';
        if (data.transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-slate-500">Tidak ada transaksi lunas di periode ini.</td></tr>';
        } else {
            data.transactions.forEach(tx => {
                if (tx.payment_status !== 'paid') return;
                const methodBadge = tx.payment_method === 'qris' 
                    ? `<span class="px-2 py-0.5 rounded bg-blue-50 text-blue-600 font-bold text-[10px]">QRIS</span>`
                    : `<span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-600 font-bold text-[10px]">TRANSFER</span>`;
                
                tbody.innerHTML += `
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-mono text-xs font-bold text-slate-700">${tx.order_number || '-'}</td>
                        <td class="px-5 py-3">${formatDate(tx.order_date.split(' ')[0])}</td>
                        <td class="px-5 py-3 font-semibold text-slate-700">${tx.recipient_name}</td>
                        <td class="px-5 py-3">${methodBadge}</td>
                        <td class="px-5 py-3 text-right font-bold text-slate-800">${formatRp(tx.total_amount)}</td>
                    </tr>
                `;
            });
        }
    }

    // Render Sales UI
    function renderSales(data) {
        // Top Products
        const tpContainer = document.getElementById('sales-top-products');
        tpContainer.innerHTML = '';
        if (data.top_products.length === 0) {
            tpContainer.innerHTML = '<p class="text-slate-500 text-sm text-center py-4">Belum ada penjualan di periode ini.</p>';
        } else {
            data.top_products.forEach((prod, i) => {
                tpContainer.innerHTML += `
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded bg-slate-100 text-slate-500 font-bold text-xs flex items-center justify-center flex-shrink-0">${i+1}</div>
                        <div class="w-10 h-10 rounded-lg overflow-hidden border border-slate-100 flex-shrink-0">
                            <img src="../${prod.image_url}" class="w-full h-full object-cover">
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-bold text-sm text-slate-800 truncate">${prod.name}</h4>
                            <p class="text-xs text-slate-500">${prod.qty_sold} terjual</p>
                        </div>
                        <div class="text-right font-bold text-sm text-emerald-600 whitespace-nowrap">
                            ${formatRp(prod.revenue)}
                        </div>
                    </div>
                `;
            });
        }

        // Sales Trend Chart
        createChart('salesTrendChart', 'bar', {
            labels: data.chart.labels.map(formatDate),
            datasets: [{
                label: 'Jumlah Pesanan',
                data: data.chart.orders,
                backgroundColor: '#3ca6f2',
                borderRadius: 4
            }]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4] }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        });

        // Status Chart
        const statusColors = {
            'paid': '#3ca6f2',
            'processing': '#f59e0b',
            'shipped': '#6366f1',
            'delivered': '#10b981',
            'unpaid': '#94a3b8',
            'cancelled': '#f43f5e'
        };
        const sLabels = Object.keys(data.order_statuses).map(k => k.toUpperCase());
        const sData = Object.values(data.order_statuses);
        const sBg = Object.keys(data.order_statuses).map(k => statusColors[k] || '#ccc');
        
        createChart('statusChart', 'pie', {
            labels: sLabels,
            datasets: [{ data: sData, backgroundColor: sBg, borderWidth: 0 }]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        });

        // Courier Chart
        const cLabels = Object.keys(data.couriers).map(c => c.toUpperCase());
        const cData = Object.values(data.couriers);
        createChart('courierChart', 'bar', {
            labels: cLabels,
            datasets: [{
                data: cData,
                backgroundColor: '#10b981',
                borderRadius: 4
            }]
        }, {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        });
    }

    // Render Customers UI
    function renderCustomers(data) {
        // Customer Segments
        createChart('customerSegmentChart', 'doughnut', {
            labels: ['Aktif (Belanja)', 'Belum Beli'],
            datasets: [{
                data: [data.customer_segments.active, data.customer_segments.inactive],
                backgroundColor: ['#10b981', '#cbd5e1'],
                borderWidth: 0
            }]
        }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '70%' });

        // Contribution Orders
        createChart('contributionOrderChart', 'pie', {
            labels: ['Toko Utama', 'Reseller'],
            datasets: [{
                data: [data.contribution.orders.main, data.contribution.orders.reseller],
                backgroundColor: ['#3ca6f2', '#8b5cf6'],
                borderWidth: 0
            }]
        }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } });

        // Contribution Revenue
        createChart('contributionRevenueChart', 'pie', {
            labels: ['Toko Utama', 'Reseller'],
            datasets: [{
                data: [data.contribution.revenue.main, data.contribution.revenue.reseller],
                backgroundColor: ['#3ca6f2', '#8b5cf6'],
                borderWidth: 0
            }]
        }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } });

        // Growth Chart
        createChart('growthChart', 'line', {
            labels: data.growth_chart.labels.map(formatDate),
            datasets: [{
                label: 'Pengguna Baru',
                data: data.growth_chart.new_users,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        }, {
            responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { borderDash: [4, 4] } }, x: { grid: { display: false } } }
        });

        // Top Customers Table
        const tcBody = document.getElementById('top-customers-body');
        tcBody.innerHTML = '';
        if (data.top_customers.length === 0) {
            tcBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-slate-500 text-xs">Belum ada pelanggan</td></tr>';
        } else {
            data.top_customers.forEach(tc => {
                tcBody.innerHTML += `
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-semibold text-slate-700">
                            <div>${tc.name}</div>
                            <div class="text-[10px] text-slate-400 font-normal">${tc.email}</div>
                        </td>
                        <td class="px-4 py-2 text-center text-xs">${tc.total_orders}x</td>
                        <td class="px-4 py-2 text-right font-bold text-slate-800">${formatRp(tc.total_spent)}</td>
                    </tr>
                `;
            });
        }

        // Top Resellers Table
        const trBody = document.getElementById('top-resellers-body');
        trBody.innerHTML = '';
        if (data.top_resellers.length === 0) {
            trBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-slate-500 text-xs">Belum ada reseller aktif</td></tr>';
        } else {
            data.top_resellers.forEach(tr => {
                trBody.innerHTML += `
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <div class="font-semibold text-emerald-600">${tr.store_name}</div>
                            <div class="text-[10px] text-slate-500">Pemilik: ${tr.owner_name}</div>
                        </td>
                        <td class="px-4 py-2 text-center text-xs">${tr.total_orders}</td>
                        <td class="px-4 py-2 text-right font-bold text-slate-800">${formatRp(tr.total_revenue)}</td>
                    </tr>
                `;
            });
        }
    }

    // Export CSV
    function exportToCSV() {
        if (!rawData) return;
        
        let csvContent = "data:text/csv;charset=utf-8,";
        
        if (currentTab === 'cashflow') {
            csvContent += "Nomor Pesanan,Tanggal,Pelanggan,Metode,Status,Total\n";
            rawData.transactions.forEach(row => {
                csvContent += `${row.order_number},${row.order_date},"${row.recipient_name}",${row.payment_method},${row.payment_status},${row.total_amount}\n`;
            });
        } else if (currentTab === 'sales') {
            csvContent += "Nama Produk,Terjual,Pendapatan\n";
            rawData.top_products.forEach(row => {
                csvContent += `"${row.name}",${row.qty_sold},${row.revenue}\n`;
            });
        } else if (currentTab === 'customers') {
            csvContent += "Tipe,Nama,Email,Total Order,Total Pendapatan\n";
            rawData.top_customers.forEach(row => {
                csvContent += `Pelanggan,"${row.name}","${row.email}",${row.total_orders},${row.total_spent}\n`;
            });
            rawData.top_resellers.forEach(row => {
                csvContent += `Reseller,"${row.store_name}","${row.owner_name}",${row.total_orders},${row.total_revenue}\n`;
            });
        }
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Data-${currentTab}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Initial load
    loadData();
</script>

</body>
</html>

<?php
require_once 'config.php';

// Initialize transaction object with error handling
try {
    $transaction = new Transaction();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_transactions':
                // Sanitize and validate input
                $filters = [
                    'status' => filter_var($_POST['status'] ?? 'all', FILTER_SANITIZE_STRING),
                    'period' => filter_var($_POST['period'] ?? 'all', FILTER_SANITIZE_STRING),
                    'search' => filter_var($_POST['search'] ?? '', FILTER_SANITIZE_STRING),
                    'limit' => min(max((int)($_POST['limit'] ?? 10), 1), 100), // Limit between 1-100
                    'offset' => max((int)($_POST['offset'] ?? 0), 0)
                ];
                
                $transactions = $transaction->getTransactions($filters);
                $totalCount = $transaction->getTransactionCount($filters);
                
                echo json_encode([
                    'success' => true,
                    'data' => $transactions ?: [],
                    'total' => $totalCount ?: 0
                ]);
                break;
                
            case 'get_transaction_detail':
                $orderId = filter_var($_POST['order_id'] ?? '', FILTER_SANITIZE_STRING);
                
                if (empty($orderId)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID transaksi tidak valid'
                    ]);
                    break;
                }
                
                $detail = $transaction->getTransactionDetail($orderId);
                
                if ($detail) {
                    echo json_encode([
                        'success' => true,
                        'data' => $detail
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Transaksi tidak ditemukan'
                    ]);
                }
                break;
                
            case 'export_csv':
                $filters = [
                    'status' => filter_var($_POST['status'] ?? 'all', FILTER_SANITIZE_STRING),
                    'period' => filter_var($_POST['period'] ?? 'all', FILTER_SANITIZE_STRING),
                    'search' => filter_var($_POST['search'] ?? '', FILTER_SANITIZE_STRING)
                ];
                
                $transactions = $transaction->getTransactions($filters);
                
                if (empty($transactions)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Tidak ada data untuk diekspor'
                    ]);
                    break;
                }
                
                // Set headers for CSV download
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="rekap_transaksi_' . date('Y-m-d_H-i-s') . '.csv"');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                // Create CSV content
                $output = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // CSV header
                fputcsv($output, ['No. Transaksi', 'Tanggal', 'Nama Pelanggan', 'Total', 'Metode Pembayaran', 'Status', 'Item']);
                
                // CSV data with better formatting
                foreach ($transactions as $row) {
                    fputcsv($output, [
                        $row['order_id'] ?? '',
                        isset($row['transaction_date']) ? date('d/m/Y H:i', strtotime($row['transaction_date'])) : '',
                        $row['customer_name'] ?? '',
                        isset($row['total_amount']) ? 'Rp ' . number_format($row['total_amount'], 0, ',', '.') : 'Rp 0',
                        $row['payment_method'] ?? '',
                        $row['status'] ?? '',
                        $row['items_summary'] ?? ''
                    ]);
                }
                
                fclose($output);
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'message' => 'Action tidak valid'
                ]);
                break;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle new transaction submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transaction'])) {
    try {
        // Validate required fields
        $required_fields = ['customer_name', 'customer_phone', 'total_amount', 'payment_method', 'items'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field $field wajib diisi");
            }
        }
        
        // Sanitize input data
        $orderData = [
            'customer_name' => filter_var(trim($_POST['customer_name']), FILTER_SANITIZE_STRING),
            'customer_phone' => filter_var(trim($_POST['customer_phone']), FILTER_SANITIZE_STRING),
            'customer_address' => filter_var(trim($_POST['customer_address'] ?? ''), FILTER_SANITIZE_STRING),
            'total_amount' => filter_var($_POST['total_amount'], FILTER_VALIDATE_FLOAT),
            'payment_method' => filter_var($_POST['payment_method'], FILTER_SANITIZE_STRING),
            'items' => json_decode($_POST['items'], true)
        ];
        
        // Validate data
        if ($orderData['total_amount'] <= 0) {
            throw new Exception("Total amount harus lebih besar dari 0");
        }
        
        if (!is_array($orderData['items']) || empty($orderData['items'])) {
            throw new Exception("Data items tidak valid");
        }
        
        // Validate phone number format (basic)
        if (!preg_match('/^[\d\-\+\(\)\s]+$/', $orderData['customer_phone'])) {
            throw new Exception("Format nomor telepon tidak valid");
        }
        
        $orderId = $transaction->addTransaction($orderData);
        $success_message = "Transaksi berhasil ditambahkan dengan ID: " . htmlspecialchars($orderId);
        
    } catch (Exception $e) {
        $error_message = "Error: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Warung Wedang Ronde</title>
    <meta name="description" content="Kelola dan lihat riwayat transaksi Warung Wedang Ronde">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");
        
        :root {
            --primary-color: rgba(181, 69, 7, 0.9);
            --primary-hover: rgb(156, 60, 6);
            --accent-color: #ffcc80;
            --background-color: #f9f9f9;
            --card-background: white;
            --text-color: #333;
            --border-color: #ddd;
            --success-color: #4caf50;
            --warning-color: #ffc107;
            --danger-color: #f44336;
            --shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Poppins", sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .logo span {
            color: var(--accent-color);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 15px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 5px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--accent-color);
            background-color: rgba(255, 255, 255, 0.1);
        }

        .recap-container {
            max-width: 1200px;
            margin: 100px auto 50px auto;
            padding: 30px;
            background-color: var(--card-background);
            border-radius: 10px;
            box-shadow: var(--shadow);
        }

        .recap-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .recap-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .recap-subtitle {
            color: #666;
            font-size: 1rem;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
            animation: slideInDown 0.3s ease;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: var(--success-color);
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: var(--danger-color);
        }

        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-label {
            font-weight: 500;
            white-space: nowrap;
        }

        .filter-select, .search-input {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background-color: white;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus, .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(181, 69, 7, 0.1);
        }

        .search-bar {
            display: flex;
            align-items: center;
        }

        .search-input {
            border-radius: 5px 0 0 5px;
            border-right: none;
            width: 200px;
        }

        .btn {
            padding: 8px 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .search-btn {
            border-radius: 0 5px 5px 0;
        }

        .export-btn {
            margin-bottom: 20px;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: var(--shadow);
            border-radius: 5px;
            overflow: hidden;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .transactions-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .transactions-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .transactions-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            text-transform: uppercase;
        }

        .status-success {
            background-color: var(--success-color);
        }

        .status-pending {
            background-color: var(--warning-color);
            color: #333;
        }

        .status-cancelled {
            background-color: var(--danger-color);
        }

        .view-details-btn {
            display: inline-block;
            padding: 5px 10px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .view-details-btn:hover {
            background-color: var(--primary-hover);
            transform: scale(1.05);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            gap: 5px;
        }

        .pagination-btn {
            padding: 8px 15px;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-color);
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f5f5f5;
            transform: translateY(-1px);
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-btn:disabled {
            background-color: #f5f5f5;
            color: #aaa;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            text-align: center;
            padding: 50px 0;
        }

        .empty-state img {
            width: 100px;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state p {
            margin-top: 20px;
            color: #666;
            font-size: 1.1rem;
        }

        .empty-state a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .empty-state a:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .transaction-details-modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideInUp 0.3s ease;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--text-color);
        }

        .modal-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: bold;
        }

        .transaction-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #eee;
        }

        .transaction-info-label {
            font-weight: 500;
        }

        .transaction-info-value {
            font-weight: 600;
            text-align: right;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item-name {
            flex: 2;
        }

        .order-item-qty {
            flex: 1;
            text-align: center;
        }

        .order-item-price {
            flex: 1;
            text-align: right;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #eee;
        }

        .order-total-label {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .order-total-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 15px;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .nav-links {
                gap: 10px;
            }
            
            .nav-links a {
                padding: 6px 8px;
                font-size: 0.9rem;
            }
            
            .recap-container {
                padding: 20px 15px;
                margin: 80px 10px 20px;
            }
            
            .recap-title {
                font-size: 1.5rem;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }

            .search-input {
                width: 100%;
            }

            .transactions-table {
                font-size: 0.85rem;
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .transactions-table th,
            .transactions-table td {
                padding: 8px 6px;
                min-width: 100px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 15px;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 3px;
            }
            
            .pagination-btn {
                padding: 6px 10px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .recap-container {
                margin: 80px 5px 20px;
                padding: 15px 10px;
            }
            
            .filter-section {
                padding: 10px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 6px 4px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo">Warung <span>Wedang Ronde</span></a>
            <ul class="nav-links">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="katalog.php">Menu</a></li>
                <li><a href="order.php">Pesan</a></li>
                <li><a href="rekap.php" class="active">Riwayat</a></li>
                <li><a href="about.php">Tentang</a></li>
            </ul>
        </nav>
    </header>

    <div class="recap-container">
        <div class="recap-header">
            <h1 class="recap-title">Riwayat Transaksi</h1>
            <p class="recap-subtitle">Kelola dan pantau semua transaksi penjualan</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <div class="filter-group">
                <label class="filter-label">Status:</label>
                <select id="statusFilter" class="filter-select">
                    <option value="all">Semua Status</option>
                    <option value="success">Berhasil</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Periode:</label>
                <select id="periodFilter" class="filter-select">
                    <option value="all">Semua Periode</option>
                    <option value="today">Hari Ini</option>
                    <option value="week">Minggu Ini</option>
                    <option value="month">Bulan Ini</option>
                </select>
            </div>

            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Cari transaksi...">
                <button id="searchBtn" class="btn search-btn">Cari</button>
            </div>
        </div>

        <button id="exportBtn" class="btn export-btn">Export CSV</button>

        <div id="transactionsContainer">
            <div class="loading">
                <div class="spinner"></div>
                Memuat data transaksi...
            </div>
        </div>

        <div id="paginationContainer"></div>
    </div>

    <!-- Modal Detail Transaksi -->
    <div id="transactionModal" class="transaction-details-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title">Detail Transaksi</h2>
            </div>
            <div id="modalContent">
                <div class="loading">
                    <div class="spinner"></div>
                    Memuat detail transaksi...
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPage = 1;
        let currentFilters = {
            status: 'all',
            period: 'all',
            search: ''
        };
        const itemsPerPage = 10;

        // DOM elements
        const statusFilter = document.getElementById('statusFilter');
        const periodFilter = document.getElementById('periodFilter');
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const exportBtn = document.getElementById('exportBtn');
        const transactionsContainer = document.getElementById('transactionsContainer');
        const paginationContainer = document.getElementById('paginationContainer');
        const modal = document.getElementById('transactionModal');
        const closeModal = document.querySelector('.close-modal');
        const modalContent = document.getElementById('modalContent');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadTransactions();
            
            // Event listeners
            statusFilter.addEventListener('change', handleFilterChange);
            periodFilter.addEventListener('change', handleFilterChange);
            searchBtn.addEventListener('click', handleSearch);
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    handleSearch();
                }
            });
            exportBtn.addEventListener('click', handleExport);
            closeModal.addEventListener('click', closeTransactionModal);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeTransactionModal();
                }
            });
        });

        // Handle filter changes
        function handleFilterChange() {
            currentFilters.status = statusFilter.value;
            currentFilters.period = periodFilter.value;
            currentPage = 1;
            loadTransactions();
        }

        // Handle search
        function handleSearch() {
            currentFilters.search = searchInput.value.trim();
            currentPage = 1;
            loadTransactions();
        }

        // Load transactions with AJAX
        function loadTransactions() {
            showLoading(transactionsContainer);
            
            const formData = new FormData();
            formData.append('action', 'get_transactions');
            formData.append('status', currentFilters.status);
            formData.append('period', currentFilters.period);
            formData.append('search', currentFilters.search);
            formData.append('limit', itemsPerPage);
            formData.append('offset', (currentPage - 1) * itemsPerPage);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayTransactions(data.data);
                    displayPagination(data.total);
                } else {
                    showError('Gagal memuat data transaksi');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Terjadi kesalahan saat memuat data');
            });
        }

        // Display transactions in table
        function displayTransactions(transactions) {
            if (transactions.length === 0) {
                transactionsContainer.innerHTML = `
                    <div class="empty-state">
                        <p>Tidak ada transaksi ditemukan</p>
                        <a href="order.php">Buat Transaksi Baru</a>
                    </div>
                `;
                return;
            }

            let tableHTML = `
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            transactions.forEach(transaction => {
                const statusClass = getStatusClass(transaction.status);
                const formattedDate = formatDate(transaction.transaction_date);
                const formattedAmount = formatCurrency(transaction.total_amount);

                tableHTML += `
                    <tr>
                        <td><strong>${escapeHtml(transaction.order_id)}</strong></td>
                        <td>${formattedDate}</td>
                        <td>${escapeHtml(transaction.customer_name)}</td>
                        <td><strong>${formattedAmount}</strong></td>
                        <td>${escapeHtml(transaction.payment_method)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHtml(transaction.status)}</span></td>
                        <td><button class="view-details-btn" onclick="viewTransactionDetail('${escapeHtml(transaction.order_id)}')">Detail</button></td>
                    </tr>
                `;
            });

            tableHTML += `
                    </tbody>
                </table>
            `;

            transactionsContainer.innerHTML = tableHTML;
        }

        // Display pagination
        function displayPagination(totalItems) {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            let paginationHTML = '<div class="pagination">';
            
            // Previous button
            paginationHTML += `
                <button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
                    &laquo; Prev
                </button>
            `;

            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                paginationHTML += `<button class="pagination-btn" onclick="changePage(1)">1</button>`;
                if (startPage > 2) {
                    paginationHTML += `<span class="pagination-btn" disabled>...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<span class="pagination-btn" disabled>...</span>`;
                }
                paginationHTML += `<button class="pagination-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
            }

            // Next button
            paginationHTML += `
                <button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
                    Next &raquo;
                </button>
            `;

            paginationHTML += '</div>';
            paginationContainer.innerHTML = paginationHTML;
        }

        // Change page
        function changePage(page) {
            if (page >= 1 && page !== currentPage) {
                currentPage = page;
                loadTransactions();
            }
        }

        // View transaction detail
        function viewTransactionDetail(orderId) {
            if (!orderId) return;
            
            modal.style.display = 'block';
            showLoading(modalContent);

            const formData = new FormData();
            formData.append('action', 'get_transaction_detail');
            formData.append('order_id', orderId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayTransactionDetail(data.data);
                } else {
                    modalContent.innerHTML = `<p class="error">Error: ${data.message || 'Gagal memuat detail transaksi'}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = '<p class="error">Terjadi kesalahan saat memuat detail transaksi</p>';
            });
        }

        // Display transaction detail in modal
        function displayTransactionDetail(transaction) {
            const statusClass = getStatusClass(transaction.status);
            const formattedDate = formatDate(transaction.transaction_date);
            
            let detailHTML = `
                <div class="transaction-info-item">
                    <span class="transaction-info-label">No. Transaksi:</span>
                    <span class="transaction-info-value">${escapeHtml(transaction.order_id)}</span>
                </div>
                <div class="transaction-info-item">
                    <span class="transaction-info-label">Tanggal:</span>
                    <span class="transaction-info-value">${formattedDate}</span>
                </div>
                <div class="transaction-info-item">
                    <span class="transaction-info-label">Nama Pelanggan:</span>
                    <span class="transaction-info-value">${escapeHtml(transaction.customer_name)}</span>
                </div>
                <div class="transaction-info-item">
                    <span class="transaction-info-label">No. Telepon:</span>
                    <span class="transaction-info-value">${escapeHtml(transaction.customer_phone || '-')}</span>
                </div>
                <div class="transaction-info-item">
                    <span class="transaction-info-label">Alamat:</span>
                    <span class="transaction-info-value">${escapeHtml(transaction.customer_address || '-')}</span>
                </div>
                <div class="transaction-info-item">
                    <span class="transaction-info-label">Metode Pembayaran:</span>
                    <span class="transaction-info-value">${escapeHtml(transaction.payment_method)}</span>
                </div>
                <div class="transaction-info-item">
                    <span class="transaction-info-label">Status:</span>
                    <span class="transaction-info-value">
                        <span class="status-badge ${statusClass}">${escapeHtml(transaction.status)}</span>
                    </span>
                </div>
                <hr>
                <h3 style="margin: 20px 0 10px 0; color: var(--primary-color);">Detail Pesanan</h3>
            `;

            if (transaction.items && transaction.items.length > 0) {
                detailHTML += `
                    <div class="order-item" style="font-weight: bold; border-bottom: 2px solid #ddd;">
                        <div class="order-item-name">Item</div>
                        <div class="order-item-qty">Qty</div>
                        <div class="order-item-price">Harga</div>
                    </div>
                `;

                transaction.items.forEach(item => {
                    detailHTML += `
                        <div class="order-item">
                            <div class="order-item-name">${escapeHtml(item.name)}</div>
                            <div class="order-item-qty">${item.quantity}</div>
                            <div class="order-item-price">${formatCurrency(item.price * item.quantity)}</div>
                        </div>
                    `;
                });
            }

            detailHTML += `
                <div class="order-total">
                    <span class="order-total-label">Total Pembayaran:</span>
                    <span class="order-total-value">${formatCurrency(transaction.total_amount)}</span>
                </div>
            `;

            modalContent.innerHTML = detailHTML;
        }

        // Close transaction modal
        function closeTransactionModal() {
            modal.style.display = 'none';
        }

        // Handle export CSV
        function handleExport() {
            const formData = new FormData();
            formData.append('action', 'export_csv');
            formData.append('status', currentFilters.status);
            formData.append('period', currentFilters.period);
            formData.append('search', currentFilters.search);

            // Show loading state
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<div class="spinner"></div> Mengekspor...';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.headers.get('content-type')?.includes('text/csv')) {
                    return response.blob();
                } else {
                    return response.json();
                }
            })
            .then(data => {
                if (data instanceof Blob) {
                    // Create download link
                    const url = window.URL.createObjectURL(data);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `rekap_transaksi_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    showSuccess('File CSV berhasil diunduh');
                } else if (data.success === false) {
                    showError(data.message || 'Gagal mengekspor data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Terjadi kesalahan saat mengekspor data');
            })
            .finally(() => {
                // Reset button state
                exportBtn.disabled = false;
                exportBtn.innerHTML = 'Export CSV';
            });
        }

        // Utility functions
        function showLoading(element) {
            element.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    Memuat data...
                </div>
            `;
        }

        function showError(message) {
            transactionsContainer.innerHTML = `
                <div class="alert alert-error">
                    ${escapeHtml(message)}
                </div>
            `;
        }

        function showSuccess(message) {
            // Create temporary success message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success';
            alertDiv.textContent = message;
            
            // Insert at the top of recap container
            const recapContainer = document.querySelector('.recap-container');
            recapContainer.insertBefore(alertDiv, recapContainer.firstChild);
            
            // Remove after 3 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        function getStatusClass(status) {
            switch (status?.toLowerCase()) {
                case 'success':
                case 'berhasil':
                case 'selesai':
                    return 'status-success';
                case 'pending':
                case 'menunggu':
                    return 'status-pending';
                case 'cancelled':
                case 'dibatalkan':
                case 'batal':
                    return 'status-cancelled';
                default:
                    return 'status-pending';
            }
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (error) {
                return dateString;
            }
        }

        function formatCurrency(amount) {
            if (isNaN(amount)) return 'Rp 0';
            
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Auto-refresh every 30 seconds (optional)
        setInterval(() => {
            loadTransactions();
        }, 30000);
    </script>
</body>
</html>
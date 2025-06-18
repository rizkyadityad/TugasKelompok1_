<?php
/**
 * Database Connection for Warung Wedang Ronde
 * File: koneksi.php
 */

// Konfigurasi database
$DB_HOST = 'localhost';          // Host database
$DB_USER = 'root';               // Username database
$DB_PASS = '';                   // Password database
$DB_NAME = 'wedang_ronde'; // Nama database

// Timezone untuk Indonesia
date_default_timezone_set('Asia/Jakarta');

try {
    // Membuat koneksi menggunakan MySQLi
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    // Set charset ke UTF-8
    $conn->set_charset("utf8");
    
    // Cek koneksi
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    
    // Set timezone database
    $conn->query("SET time_zone = '+07:00'");
    
} catch (Exception $e) {
    // Log error (dalam produksi, gunakan file log)
    error_log("Database Error: " . $e->getMessage());
    
    // Tampilkan pesan error yang user-friendly
    die("Maaf, terjadi kesalahan koneksi database. Silakan coba lagi nanti.");
}

/**
 * Fungsi untuk menutup koneksi database
 */
function closeConnection($connection = null) {
    global $conn;
    $connection = $connection ?: $conn;
    
    if ($connection && !$connection->connect_error) {
        $connection->close();
    }
}

/**
 * Fungsi untuk escape string (mencegah SQL injection)
 */
function escapeString($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

/**
 * Fungsi untuk execute prepared statement dengan error handling
 */
function executeQuery($query, $params = [], $types = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare statement gagal: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Execute query gagal: " . $stmt->error);
        }
        
        return $stmt;
        
    } catch (Exception $e) {
        error_log("Query Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Fungsi untuk mendapatkan data dengan prepared statement
 */
function fetchData($query, $params = [], $types = '') {
    try {
        $stmt = executeQuery($query, $params, $types);
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
        
    } catch (Exception $e) {
        error_log("Fetch Data Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fungsi untuk mendapatkan satu baris data
 */
function fetchSingleData($query, $params = [], $types = '') {
    $data = fetchData($query, $params, $types);
    return !empty($data) ? $data[0] : null;
}

/**
 * Fungsi untuk insert data dan return last insert ID
 */
function insertData($query, $params = [], $types = '') {
    global $conn;
    
    try {
        $stmt = executeQuery($query, $params, $types);
        $lastId = $conn->insert_id;
        $stmt->close();
        
        return $lastId;
        
    } catch (Exception $e) {
        error_log("Insert Data Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fungsi untuk update/delete data dan return affected rows
 */
function modifyData($query, $params = [], $types = '') {
    try {
        $stmt = executeQuery($query, $params, $types);
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
        
    } catch (Exception $e) {
        error_log("Modify Data Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fungsi untuk memulai transaction
 */
function beginTransaction() {
    global $conn;
    return $conn->begin_transaction();
}

/**
 * Fungsi untuk commit transaction
 */
function commitTransaction() {
    global $conn;
    return $conn->commit();
}

/**
 * Fungsi untuk rollback transaction
 */
function rollbackTransaction() {
    global $conn;
    return $conn->rollback();
}

/**
 * Fungsi untuk membuat tabel jika belum ada
 */
function createTablesIfNotExist() {
    global $conn;
    
    // Tabel users
    $createUsersTable = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'customer') DEFAULT 'customer',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Tabel categories
    $createCategoriesTable = "
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        image VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Tabel products
    $createProductsTable = "
    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Tabel transactions
    $createTransactionsTable = "
    CREATE TABLE IF NOT EXISTS transactions (
        id VARCHAR(50) PRIMARY KEY,
        user_id INT,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_email VARCHAR(100),
        customer_address TEXT NOT NULL,
        customer_notes TEXT,
        subtotal DECIMAL(10,2) NOT NULL,
        tax DECIMAL(10,2) NOT NULL,
        shipping DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        payment_method ENUM('cash', 'transfer', 'ewallet', 'qris') NOT NULL,
        payment_status ENUM('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
        order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Tabel transaction_items
    $createTransactionItemsTable = "
    CREATE TABLE IF NOT EXISTS transaction_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50),
        product_id INT,
        product_name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Execute table creation
    $tables = [
        'users' => $createUsersTable,
        'categories' => $createCategoriesTable,
        'products' => $createProductsTable,
        'transactions' => $createTransactionsTable,
        'transaction_items' => $createTransactionItemsTable
    ];
    
    foreach ($tables as $tableName => $sql) {
        if (!$conn->query($sql)) {
            error_log("Error creating table $tableName: " . $conn->error);
        }
    }
}

// Buat tabel jika belum ada (hanya jalankan sekali saat setup)
// Uncomment baris di bawah ini untuk membuat tabel otomatis
// createTablesIfNotExist();

/**
 * Fungsi untuk insert sample data (untuk testing)
 */
function insertSampleData() {
    global $conn;
    
    // Sample categories
    $categories = [
        ['Wedang Ronde', 'Minuman wedang ronde tradisional dengan berbagai varian'],
        ['Minuman Panas', 'Berbagai minuman hangat tradisional'],
    ];
    
    foreach ($categories as $category) {
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category[0], $category[1]);
        $stmt->execute();
    }
    
    // Sample products
    $products = [
        [1, 'Wedang Ronde Original', 'Wedang ronde klasik dengan ronde isi kacang', 10000],
        [1, 'Wedang Cemue', 'Wedang dengan roti tawar dan kuah santan', 8000],
    ];
    
    foreach ($products as $product) {
        $stmt = $conn->prepare("INSERT IGNORE INTO products (category_id, name, description, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $product[0], $product[1], $product[2], $product[3]);
        $stmt->execute();
    }
}

// Uncomment untuk insert sample data (hanya jalankan sekali)
// insertSampleData();

?>
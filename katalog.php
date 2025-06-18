<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'koneksi.php';

try {
    // Ambil semua menu items yang aktif
    $sql = "SELECT id, name, price, image, category, description FROM menu_items WHERE is_active = 1 ORDER BY category, name";
    $result = $conn->query($sql);
    
    $menuItems = array();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $menuItems[] = array(
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'price' => (int)$row['price'],
                'image' => $row['image'],
                'category' => $row['category'],
                'description' => $row['description']
            );
        }
    }
    
    echo json_encode($menuItems);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'error' => true,
        'message' => 'Gagal mengambil data menu: ' . $e->getMessage()
    ));
}

$conn->close();
?>
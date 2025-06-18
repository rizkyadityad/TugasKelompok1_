<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "wedang_ronde";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// classes/Transaction.php
class Transaction {
    private $conn;
    private $table_name = "transactions";
    private $items_table = "transaction_items";

    public $id;
    public $timestamp;
    public $customer_name;
    public $customer_phone;
    public $customer_email;
    public $customer_address;
    public $customer_notes;
    public $subtotal;
    public $tax;
    public $shipping;
    public $total;
    public $payment_method;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Membuat transaksi baru
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET id=:id, timestamp=:timestamp, customer_name=:customer_name, 
                     customer_phone=:customer_phone, customer_email=:customer_email,
                     customer_address=:customer_address, customer_notes=:customer_notes,
                     subtotal=:subtotal, tax=:tax, shipping=:shipping, total=:total,
                     payment_method=:payment_method, status=:status";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":timestamp", $this->timestamp);
        $stmt->bindParam(":customer_name", $this->customer_name);
        $stmt->bindParam(":customer_phone", $this->customer_phone);
        $stmt->bindParam(":customer_email", $this->customer_email);
        $stmt->bindParam(":customer_address", $this->customer_address);
        $stmt->bindParam(":customer_notes", $this->customer_notes);
        $stmt->bindParam(":subtotal", $this->subtotal);
        $stmt->bindParam(":tax", $this->tax);
        $stmt->bindParam(":shipping", $this->shipping);
        $stmt->bindParam(":total", $this->total);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Menambahkan item ke transaksi
    public function addItem($transaction_id, $item_name, $item_price, $quantity) {
        $query = "INSERT INTO " . $this->items_table . " 
                 SET transaction_id=:transaction_id, item_name=:item_name, 
                     item_price=:item_price, quantity=:quantity";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":transaction_id", $transaction_id);
        $stmt->bindParam(":item_name", $item_name);
        $stmt->bindParam(":item_price", $item_price);
        $stmt->bindParam(":quantity", $quantity);

        return $stmt->execute();
    }

    // Mengambil semua transaksi dengan filter
    public function getAll($date_from = null, $date_to = null, $status = null, $payment_method = null) {
        $query = "SELECT t.*, 
                         GROUP_CONCAT(
                             CONCAT(ti.item_name, '|', ti.item_price, '|', ti.quantity) 
                             SEPARATOR '||'
                         ) as items
                  FROM " . $this->table_name . " t
                  LEFT JOIN " . $this->items_table . " ti ON t.id = ti.transaction_id
                  WHERE 1=1";

        $params = array();

        if($date_from) {
            $query .= " AND DATE(t.timestamp) >= :date_from";
            $params[':date_from'] = $date_from;
        }

        if($date_to) {
            $query .= " AND DATE(t.timestamp) <= :date_to";
            $params[':date_to'] = $date_to;
        }

        if($status) {
            $query .= " AND t.status = :status";
            $params[':status'] = $status;
        }

        if($payment_method) {
            $query .= " AND t.payment_method = :payment_method";
            $params[':payment_method'] = $payment_method;
        }

        $query .= " GROUP BY t.id ORDER BY t.timestamp DESC";

        $stmt = $this->conn->prepare($query);
        
        foreach($params as $key => $value) {
            $stmt->bindParam($key, $value);
        }

        $stmt->execute();
        return $stmt;
    }

    // Mengambil satu transaksi berdasarkan ID
    public function getById($id) {
        $query = "SELECT t.*, 
                         GROUP_CONCAT(
                             CONCAT(ti.item_name, '|', ti.item_price, '|', ti.quantity) 
                             SEPARATOR '||'
                         ) as items
                  FROM " . $this->table_name . " t
                  LEFT JOIN " . $this->items_table . " ti ON t.id = ti.transaction_id
                  WHERE t.id = :id
                  GROUP BY t.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt;
    }

    // Mengambil statistik transaksi
    public function getStatistics($date_from = null, $date_to = null) {
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(total), 0) as total_revenue,
                    COALESCE(AVG(total), 0) as avg_transaction,
                    COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as today_transactions
                  FROM " . $this->table_name . " 
                  WHERE 1=1";

        $params = array();

        if($date_from) {
            $query .= " AND DATE(timestamp) >= :date_from";
            $params[':date_from'] = $date_from;
        }

        if($date_to) {
            $query .= " AND DATE(timestamp) <= :date_to";
            $params[':date_to'] = $date_to;
        }

        $stmt = $this->conn->prepare($query);
        
        foreach($params as $key => $value) {
            $stmt->bindParam($key, $value);
        }

        $stmt->execute();
        return $stmt;
    }

    // Update status transaksi
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}

// api/transactions.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/Transaction.php';

$database = new Database();
$db = $database->getConnection();
$transaction = new Transaction($db);

$request_method = $_SERVER["REQUEST_METHOD"];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', $path);

switch($request_method) {
    case 'GET':
        if(isset($_GET['id'])) {
            // Get single transaction
            getTransaction($_GET['id'], $transaction);
        } elseif(isset($_GET['stats'])) {
            // Get statistics
            getStatistics($transaction);
        } else {
            // Get all transactions
            getTransactions($transaction);
        }
        break;
    
    case 'POST':
        // Create new transaction
        createTransaction($transaction);
        break;
    
    case 'PUT':
        // Update transaction status
        updateTransactionStatus($transaction);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed"));
        break;
}

function getTransactions($transaction) {
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : null;

    $stmt = $transaction->getAll($date_from, $date_to, $status, $payment_method);
    $num = $stmt->rowCount();

    if($num > 0) {
        $transactions_arr = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transaction_item = array(
                "id" => $row['id'],
                "timestamp" => $row['timestamp'],
                "customer" => array(
                    "name" => $row['customer_name'],
                    "phone" => $row['customer_phone'],
                    "email" => $row['customer_email'],
                    "address" => $row['customer_address'],
                    "notes" => $row['customer_notes']
                ),
                "items" => parseItems($row['items']),
                "subtotal" => floatval($row['subtotal']),
                "tax" => floatval($row['tax']),
                "shipping" => floatval($row['shipping']),
                "total" => floatval($row['total']),
                "payment_method" => $row['payment_method'],
                "status" => $row['status']
            );
            array_push($transactions_arr, $transaction_item);
        }

        http_response_code(200);
        echo json_encode($transactions_arr);
    } else {
        http_response_code(200);
        echo json_encode(array());
    }
}

function getTransaction($id, $transaction) {
    $stmt = $transaction->getById($id);
    $num = $stmt->rowCount();

    if($num > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $transaction_item = array(
            "id" => $row['id'],
            "timestamp" => $row['timestamp'],
            "customer" => array(
                "name" => $row['customer_name'],
                "phone" => $row['customer_phone'],
                "email" => $row['customer_email'],
                "address" => $row['customer_address'],
                "notes" => $row['customer_notes']
            ),
            "items" => parseItems($row['items']),
            "subtotal" => floatval($row['subtotal']),
            "tax" => floatval($row['tax']),
            "shipping" => floatval($row['shipping']),
            "total" => floatval($row['total']),
            "payment_method" => $row['payment_method'],
            "status" => $row['status']
        );

        http_response_code(200);
        echo json_encode($transaction_item);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Transaction not found"));
    }
}

function getStatistics($transaction) {
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

    $stmt = $transaction->getStatistics($date_from, $date_to);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats = array(
        "total_transactions" => intval($row['total_transactions']),
        "total_revenue" => floatval($row['total_revenue']),
        "avg_transaction" => floatval($row['avg_transaction']),
        "today_transactions" => intval($row['today_transactions'])
    );

    http_response_code(200);
    echo json_encode($stats);
}

function createTransaction($transaction) {
    $data = json_decode(file_get_contents("php://input"));

    if(!empty($data->id) && !empty($data->customer->name) && !empty($data->items)) {
        $transaction->id = $data->id;
        $transaction->timestamp = $data->timestamp;
        $transaction->customer_name = $data->customer->name;
        $transaction->customer_phone = $data->customer->phone;
        $transaction->customer_email = $data->customer->email ?? '';
        $transaction->customer_address = $data->customer->address;
        $transaction->customer_notes = $data->customer->notes ?? '';
        $transaction->subtotal = $data->subtotal;
        $transaction->tax = $data->tax;
        $transaction->shipping = $data->shipping;
        $transaction->total = $data->total;
        $transaction->payment_method = $data->payment_method;
        $transaction->status = $data->status ?? 'pending';

        if($transaction->create()) {
            // Add items
            foreach($data->items as $item) {
                $transaction->addItem($data->id, $item->name, $item->price, $item->quantity);
            }

            http_response_code(201);
            echo json_encode(array("message" => "Transaction created successfully"));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Unable to create transaction"));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Unable to create transaction. Data is incomplete"));
    }
}

function updateTransactionStatus($transaction) {
    $data = json_decode(file_get_contents("php://input"));

    if(!empty($data->id) && !empty($data->status)) {
        if($transaction->updateStatus($data->id, $data->status)) {
            http_response_code(200);
            echo json_encode(array("message" => "Transaction status updated"));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Unable to update transaction status"));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Unable to update transaction. Data is incomplete"));
    }
}

function parseItems($items_string) {
    if(empty($items_string)) return array();
    
    $items = array();
    $items_data = explode('||', $items_string);
    
    foreach($items_data as $item_data) {
        $item_parts = explode('|', $item_data);
        if(count($item_parts) >= 3) {
            $items[] = array(
                "name" => $item_parts[0],
                "price" => floatval($item_parts[1]),
                "quantity" => intval($item_parts[2])
            );
        }
    }
    
    return $items;
}
?>
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    switch($action) {
        case 'list':
            // Get parameters
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;

            // Build query
            $query = "SELECT 
                p.PenjualanID,
                p.TanggalPenjualan,
                p.TotalHarga,
                pl.NamaPelanggan,
                COUNT(d.DetailID) as JumlahItem
            FROM penjualan p
            LEFT JOIN pelanggan pl ON p.PelangganID = pl.PelangganID
            LEFT JOIN detailpenjualan d ON p.PenjualanID = d.PenjualanID
            WHERE DATE(p.TanggalPenjualan) BETWEEN :start_date AND :end_date";

            $params = [
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ];

            // Add search filter if provided
            if (!empty($search)) {
                $query .= " AND (pl.NamaPelanggan LIKE :search OR p.PenjualanID LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            $query .= " GROUP BY p.PenjualanID
                        ORDER BY p.TanggalPenjualan DESC
                        LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();

            $transactions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $transactions[] = $row;
            }

            // Get total count for pagination
            $count_query = "SELECT COUNT(DISTINCT p.PenjualanID) as total 
                           FROM penjualan p
                           LEFT JOIN pelanggan pl ON p.PelangganID = pl.PelangganID
                           WHERE DATE(p.TanggalPenjualan) BETWEEN :start_date AND :end_date";
            
            if (!empty($search)) {
                $count_query .= " AND (pl.NamaPelanggan LIKE :search OR p.PenjualanID LIKE :search)";
            }

            $count_stmt = $db->prepare($count_query);
            $count_stmt->bindValue(':start_date', $start_date);
            $count_stmt->bindValue(':end_date', $end_date);
            if (!empty($search)) {
                $count_stmt->bindValue(':search', '%' . $search . '%');
            }
            $count_stmt->execute();
            $total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
            $total = $total_result ? $total_result['total'] : 0;

            echo json_encode([
                'success' => true,
                'data' => $transactions,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
            break;

        case 'detail':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
                exit;
            }

            // Get transaction header
            $query = "SELECT 
                p.PenjualanID,
                p.TanggalPenjualan,
                p.TotalHarga,
                pl.NamaPelanggan,
                pl.Alamat,
                pl.NomorTelepon
            FROM penjualan p
            LEFT JOIN pelanggan pl ON p.PelangganID = pl.PelangganID
            WHERE p.PenjualanID = :id";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $_GET['id']);
            $stmt->execute();
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transaction) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
                exit;
            }

            // Get transaction items
            $items_query = "SELECT 
                d.DetailID,
                b.NamaBarang,
                d.Jumlah,
                d.HargaJual,
                (d.Jumlah * d.HargaJual) as Subtotal
            FROM detailpenjualan d
            INNER JOIN barang b ON d.BarangID = b.BarangID
            WHERE d.PenjualanID = :id";

            $items_stmt = $db->prepare($items_query);
            $items_stmt->bindValue(':id', $_GET['id']);
            $items_stmt->execute();
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            $transaction['items'] = $items;

            echo json_encode([
                'success' => true,
                'data' => $transaction
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => [
            'action' => $action,
            'get_params' => $_GET
        ]
    ]);
}
?>
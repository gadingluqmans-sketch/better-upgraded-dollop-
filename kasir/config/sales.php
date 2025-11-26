<?php
/**
 * Sales API
 * Handles sales history and reporting
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    switch($action) {
        case 'list':
            // Get sales history with customer name
            $query = "SELECT 
                        p.PenjualanID,
                        p.TanggalPenjualan,
                        p.TotalHarga,
                        pel.NamaPelanggan,
                        p.created_at
                      FROM penjualan p
                      LEFT JOIN pelanggan pel ON p.PelangganID = pel.PelangganID
                      ORDER BY p.TanggalPenjualan DESC, p.PenjualanID DESC
                      LIMIT 50";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $sales = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sales[] = [
                    'PenjualanID' => (int)$row['PenjualanID'],
                    'TanggalPenjualan' => $row['TanggalPenjualan'],
                    'TotalHarga' => (float)$row['TotalHarga'],
                    'NamaPelanggan' => $row['NamaPelanggan'] ?? 'Pelanggan Umum',
                    'created_at' => $row['created_at']
                ];
            }
            
            echo json_encode($sales);
            break;
            
        case 'detail':
            // Get sale detail with items
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sale ID is required'
                ]);
                exit;
            }
            
            // Get sale header
            $querySale = "SELECT 
                            p.PenjualanID,
                            p.TanggalPenjualan,
                            p.TotalHarga,
                            pel.NamaPelanggan,
                            pel.NomorTelepon
                          FROM penjualan p
                          LEFT JOIN pelanggan pel ON p.PelangganID = pel.PelangganID
                          WHERE p.PenjualanID = :id";
            
            $stmtSale = $db->prepare($querySale);
            $stmtSale->bindParam(':id', $_GET['id']);
            $stmtSale->execute();
            
            $sale = $stmtSale->fetch(PDO::FETCH_ASSOC);
            
            if (!$sale) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sale not found'
                ]);
                exit;
            }
            
            // Get sale items
            $queryItems = "SELECT 
                            d.DetailID,
                            d.JumlahProduk,
                            d.Subtotal,
                            pr.NamaProduk,
                            pr.Harga
                          FROM detailpenjualan d
                          JOIN produk pr ON d.ProdukID = pr.ProdukID
                          WHERE d.PenjualanID = :id";
            
            $stmtItems = $db->prepare($queryItems);
            $stmtItems->bindParam(':id', $_GET['id']);
            $stmtItems->execute();
            
            $items = [];
            while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
                $items[] = [
                    'DetailID' => (int)$row['DetailID'],
                    'NamaProduk' => $row['NamaProduk'],
                    'Harga' => (float)$row['Harga'],
                    'JumlahProduk' => (int)$row['JumlahProduk'],
                    'Subtotal' => (float)$row['Subtotal']
                ];
            }
            
            echo json_encode([
                'sale' => [
                    'PenjualanID' => (int)$sale['PenjualanID'],
                    'TanggalPenjualan' => $sale['TanggalPenjualan'],
                    'TotalHarga' => (float)$sale['TotalHarga'],
                    'NamaPelanggan' => $sale['NamaPelanggan'] ?? 'Pelanggan Umum',
                    'NomorTelepon' => $sale['NomorTelepon'] ?? '-'
                ],
                'items' => $items
            ]);
            break;
            
        case 'summary':
            // Get sales summary
            $querySummary = "SELECT 
                                COUNT(*) as total_transactions,
                                SUM(TotalHarga) as total_revenue,
                                AVG(TotalHarga) as avg_transaction,
                                MAX(TotalHarga) as max_transaction,
                                MIN(TotalHarga) as min_transaction
                             FROM penjualan
                             WHERE TanggalPenjualan >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            
            $stmt = $db->prepare($querySummary);
            $stmt->execute();
            
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'total_transactions' => (int)$summary['total_transactions'],
                'total_revenue' => (float)$summary['total_revenue'],
                'avg_transaction' => (float)$summary['avg_transaction'],
                'max_transaction' => (float)$summary['max_transaction'],
                'min_transaction' => (float)$summary['min_transaction']
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
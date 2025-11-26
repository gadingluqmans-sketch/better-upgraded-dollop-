<?php
/**
 * Checkout API
 * Handles transaction processing with automatic stock updates
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get POST data
$data = json_decode(file_get_contents("php://input"));

// Validate input data
if (empty($data->items) || empty($data->total)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data: items and total are required'
    ]);
    exit;
}

try {
    // Start transaction
    $db->beginTransaction();
    
    // 1. Insert into penjualan table
    $queryPenjualan = "INSERT INTO penjualan (TanggalPenjualan, TotalHarga, PelangganID) 
                       VALUES (CURDATE(), :total, :pelangganId)";
    
    $stmtPenjualan = $db->prepare($queryPenjualan);
    $stmtPenjualan->bindParam(':total', $data->total);
    
    // PelangganID is optional
    $pelangganId = isset($data->pelangganId) ? $data->pelangganId : null;
    $stmtPenjualan->bindParam(':pelangganId', $pelangganId, PDO::PARAM_INT);
    
    if (!$stmtPenjualan->execute()) {
        throw new Exception('Failed to create sale record');
    }
    
    // Get the newly created PenjualanID
    $penjualanId = $db->lastInsertId();
    
    // 2. Prepare queries for detail and stock update
    $queryDetail = "INSERT INTO detailpenjualan 
                    (PenjualanID, ProdukID, JumlahProduk, Subtotal) 
                    VALUES (:penjualanId, :produkId, :jumlah, :subtotal)";
    $stmtDetail = $db->prepare($queryDetail);
    
    $queryCheckStock = "SELECT Stok FROM produk WHERE ProdukID = :produkId";
    $stmtCheckStock = $db->prepare($queryCheckStock);
    
    $queryUpdateStock = "UPDATE produk 
                         SET Stok = Stok - :jumlah 
                         WHERE ProdukID = :produkId";
    $stmtStock = $db->prepare($queryUpdateStock);
    
    // 3. Process each item in the cart
    foreach ($data->items as $item) {
        // Validate item data
        if (empty($item->ProdukID) || empty($item->quantity) || empty($item->subtotal)) {
            throw new Exception('Invalid item data');
        }
        
        // Check if stock is sufficient
        $stmtCheckStock->bindParam(':produkId', $item->ProdukID);
        $stmtCheckStock->execute();
        $currentStock = $stmtCheckStock->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentStock) {
            throw new Exception('Product ID ' . $item->ProdukID . ' not found');
        }
        
        if ($currentStock['Stok'] < $item->quantity) {
            throw new Exception('Insufficient stock for product ID: ' . $item->ProdukID . 
                              ' (Available: ' . $currentStock['Stok'] . ', Requested: ' . $item->quantity . ')');
        }
        
        // Insert detail penjualan
        $stmtDetail->bindParam(':penjualanId', $penjualanId);
        $stmtDetail->bindParam(':produkId', $item->ProdukID);
        $stmtDetail->bindParam(':jumlah', $item->quantity);
        $stmtDetail->bindParam(':subtotal', $item->subtotal);
        
        if (!$stmtDetail->execute()) {
            throw new Exception('Failed to insert sale detail for product ID: ' . $item->ProdukID);
        }
        
        // Update stock
        $stmtStock->bindParam(':jumlah', $item->quantity);
        $stmtStock->bindParam(':produkId', $item->ProdukID);
        
        if (!$stmtStock->execute()) {
            throw new Exception('Failed to update stock for product ID: ' . $item->ProdukID);
        }
        
        // Verify that stock was actually updated
        if ($stmtStock->rowCount() === 0) {
            throw new Exception('Failed to update stock for product ID: ' . $item->ProdukID);
        }
    }
    
    // 4. Commit transaction if everything is successful
    $db->commit();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Transaction completed successfully',
        'penjualanId' => $penjualanId,
        'totalItems' => count($data->items),
        'totalAmount' => $data->total
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
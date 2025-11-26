<?php
/**
 * Products API
 * Handles all product-related operations
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Sesuaikan path ini jika struktur folder Anda berbeda, 
// tapi berdasarkan file yang diupload, path ini dibiarkan tetap.
require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

// Get the action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    switch($action) {
        case 'list':
            // Get all products
            $query = "SELECT ProdukID, NamaProduk, Harga, Stok 
                      FROM produk 
                      ORDER BY NamaProduk ASC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $products = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $products[] = [
                    'ProdukID' => (int)$row['ProdukID'],
                    'NamaProduk' => $row['NamaProduk'],
                    'Harga' => (float)$row['Harga'],
                    'Stok' => (int)$row['Stok']
                ];
            }
            
            echo json_encode($products);
            break;
            
        case 'get':
            // Get single product by ID
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Product ID is required'
                ]);
                exit;
            }
            
            $query = "SELECT ProdukID, NamaProduk, Harga, Stok 
                      FROM produk 
                      WHERE ProdukID = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);
            $stmt->execute();
            
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                echo json_encode([
                    'ProdukID' => (int)$product['ProdukID'],
                    'NamaProduk' => $product['NamaProduk'],
                    'Harga' => (float)$product['Harga'],
                    'Stok' => (int)$product['Stok']
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Product not found'
                ]);
            }
            break;
            
        case 'add':
            // Add new product (POST)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'message' => 'Method not allowed'
                ]);
                exit;
            }
            
            $data = json_decode(file_get_contents("php://input"));
            
            // Validasi input
            if (empty($data->NamaProduk) || !isset($data->Harga) || !isset($data->Stok)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields (NamaProduk, Harga, Stok)'
                ]);
                exit;
            }
            
            $query = "INSERT INTO produk (NamaProduk, Harga, Stok) 
                      VALUES (:nama, :harga, :stok)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama', $data->NamaProduk);
            $stmt->bindParam(':harga', $data->Harga);
            $stmt->bindParam(':stok', $data->Stok);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Product added successfully',
                    'id' => $db->lastInsertId()
                ]);
            } else {
                throw new Exception('Failed to add product');
            }
            break;
            
        case 'update':
            // Update product stock AND price [FIXED]
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'message' => 'Method not allowed'
                ]);
                exit;
            }
            
            $data = json_decode(file_get_contents("php://input"));
            
            // Validasi: Pastikan Harga juga dicek
            if (empty($data->ProdukID) || !isset($data->Stok) || !isset($data->Harga)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Product ID, Stock, and Price are required'
                ]);
                exit;
            }
            
            // Query diupdate untuk mengubah Stok DAN Harga
            $query = "UPDATE produk 
                      SET Stok = :stok, Harga = :harga 
                      WHERE ProdukID = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':stok', $data->Stok);
            $stmt->bindParam(':harga', $data->Harga); // Binding parameter Harga
            $stmt->bindParam(':id', $data->ProdukID);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Product updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update product');
            }
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
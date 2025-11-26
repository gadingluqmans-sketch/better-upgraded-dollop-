<?php
/**
 * Customers API
 * Handles all customer-related operations
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Since this file is INSIDE config folder, we include database.php from the same directory
require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    switch($action) {
        case 'list':
            // Get all customers
            $query = "SELECT PelangganID, NamaPelanggan, Alamat, NomorTelepon 
                      FROM pelanggan 
                      ORDER BY NamaPelanggan ASC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $customers = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $customers[] = [
                    'PelangganID' => (int)$row['PelangganID'],
                    'NamaPelanggan' => $row['NamaPelanggan'],
                    'Alamat' => $row['Alamat'],
                    'NomorTelepon' => $row['NomorTelepon']
                ];
            }
            
            // CHANGE: Return just the array (not wrapped in success/data)
            echo json_encode($customers);
            break;
            
        case 'get':
            // Get single customer by ID
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer ID is required'
                ]);
                exit;
            }
            
            $query = "SELECT PelangganID, NamaPelanggan, Alamat, NomorTelepon 
                      FROM pelanggan 
                      WHERE PelangganID = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);
            $stmt->execute();
            
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                echo json_encode([
                    'PelangganID' => (int)$customer['PelangganID'],
                    'NamaPelanggan' => $customer['NamaPelanggan'],
                    'Alamat' => $customer['Alamat'],
                    'NomorTelepon' => $customer['NomorTelepon']
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer not found'
                ]);
            }
            break;
            
        case 'add':
            // Add new customer
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'message' => 'Method not allowed'
                ]);
                exit;
            }
            
            $data = json_decode(file_get_contents("php://input"));
            
            if (empty($data->NamaPelanggan)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer name is required'
                ]);
                exit;
            }
            
            $query = "INSERT INTO pelanggan (NamaPelanggan, Alamat, NomorTelepon) 
                      VALUES (:nama, :alamat, :telepon)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama', $data->NamaPelanggan);
            $stmt->bindParam(':alamat', $data->Alamat);
            $stmt->bindParam(':telepon', $data->NomorTelepon);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Customer added successfully',
                    'id' => $db->lastInsertId()
                ]);
            } else {
                throw new Exception('Failed to add customer');
            }
            break;
            
        case 'update':
            // Update customer
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'message' => 'Method not allowed'
                ]);
                exit;
            }
            
            $data = json_decode(file_get_contents("php://input"));
            
            if (empty($data->PelangganID) || empty($data->NamaPelanggan)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer ID and name are required'
                ]);
                exit;
            }
            
            $query = "UPDATE pelanggan 
                      SET NamaPelanggan = :nama, 
                          Alamat = :alamat, 
                          NomorTelepon = :telepon 
                      WHERE PelangganID = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama', $data->NamaPelanggan);
            $stmt->bindParam(':alamat', $data->Alamat);
            $stmt->bindParam(':telepon', $data->NomorTelepon);
            $stmt->bindParam(':id', $data->PelangganID);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Customer updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update customer');
            }
            break;
            
        case 'delete':
            // Delete customer
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer ID is required'
                ]);
                exit;
            }
            
            $query = "DELETE FROM pelanggan WHERE PelangganID = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Customer deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete customer');
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
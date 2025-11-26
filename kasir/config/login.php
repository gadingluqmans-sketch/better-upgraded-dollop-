<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'database.php';

// Debug: Log the request
error_log("Login attempt: " . file_get_contents("php://input"));

$database = new Database();
$db = $database->getConnection();

// Debug: Check database connection
if (!$db) {
    error_log("Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    error_log("Received data: " . print_r($data, true));
    
    if (empty($data->username) || empty($data->password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username dan password harus diisi',
            'debug' => ['received_data' => $data]
        ]);
        exit;
    }

    try {
        $query = "SELECT UserID, Username, PasswordHash, NamaLengkap, Role 
                  FROM users 
                  WHERE Username = :username";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $data->username);
        $stmt->execute();
        
        error_log("Query executed, row count: " . $stmt->rowCount());
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found: " . print_r($user, true));
            
            // Verify password
            $passwordMatch = password_verify($data->password, $user['PasswordHash']);
            error_log("Password verification: " . ($passwordMatch ? 'MATCH' : 'NO MATCH'));
            
            if ($passwordMatch) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Login berhasil',
                    'user' => [
                        'UserID' => (int)$user['UserID'],
                        'Username' => $user['Username'],
                        'NamaLengkap' => $user['NamaLengkap'],
                        'Role' => $user['Role']
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Password salah'
                ]);
            }
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Username tidak ditemukan'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST method.'
    ]);
}
?>
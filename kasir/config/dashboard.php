<?php
/**
 * Dashboard API
 * Returns aggregated data for dashboard display
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Get today's date
    $today = date('Y-m-d');
    
    // 1. Get today's income and transaction count
    $queryToday = "SELECT 
                    COALESCE(SUM(TotalHarga), 0) as today_income,
                    COUNT(*) as today_count
                   FROM penjualan 
                   WHERE DATE(TanggalPenjualan) = :today";
    $stmtToday = $db->prepare($queryToday);
    $stmtToday->bindParam(':today', $today);
    $stmtToday->execute();
    $todayData = $stmtToday->fetch(PDO::FETCH_ASSOC);
    
    // 2. Get last 10 transactions
    $queryRecent = "SELECT 
                        p.PenjualanID,
                        p.TanggalPenjualan,
                        p.TotalHarga,
                        COALESCE(pel.NamaPelanggan, 'Umum') as NamaPelanggan
                    FROM penjualan p
                    LEFT JOIN pelanggan pel ON p.PelangganID = pel.PelangganID
                    ORDER BY p.PenjualanID DESC
                    LIMIT 10";
    $stmtRecent = $db->prepare($queryRecent);
    $stmtRecent->execute();
    $recentTransactions = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Get last 7 days sales data
    $salesChart = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        $querySales = "SELECT COALESCE(SUM(TotalHarga), 0) as total
                       FROM penjualan 
                       WHERE DATE(TanggalPenjualan) = :date";
        $stmtSales = $db->prepare($querySales);
        $stmtSales->bindParam(':date', $date);
        $stmtSales->execute();
        $dayData = $stmtSales->fetch(PDO::FETCH_ASSOC);
        
        $salesChart[] = [
            'tanggal' => $date,
            'total' => $dayData['total']
        ];
    }
    
    // Return all data
    echo json_encode([
        'success' => true,
        'today_income' => (float)$todayData['today_income'],
        'today_count' => (int)$todayData['today_count'],
        'recent_transactions' => $recentTransactions,
        'sales_chart' => $salesChart
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
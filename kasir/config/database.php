<?php
// File: config/database.php

class Database {
    private $host = "localhost";
    private $db_name = "kasir_app"; // Pastikan nama DB ini SAMA dengan yang ada di phpMyAdmin
    private $username = "root";
    private $password = "";          // Kosongkan jika password root Anda kosong
    public $conn;

    // Mendapatkan koneksi database
    public function getConnection(){
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        } catch(PDOException $exception){
            echo "Connection error: " . $exception->getMessage();
            exit();
        }
        return $this->conn;
    }
}
?>
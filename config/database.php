<?php
/**
 * Database Configuration
 * Learning Management System for NEUST-MGT BSIT Department
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'lms_neust_normalized';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                )
            );
        } catch(PDOException $exception) {
            // Don't echo here - let the calling code handle errors
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

// Create global database instance
try {
    $database = new Database();
    $db = $database->getConnection();
    $pdo = $db; // Create $pdo variable for compatibility with student files
} catch (Exception $e) {
    // Log the error but don't output it directly
    error_log("Database connection error: " . $e->getMessage());
    // Set a flag that can be checked later
    $db = null;
    $pdo = null;
}
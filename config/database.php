<?php
// config/database.php

class Database
{
    private $host = "localhost";
    private $db_name = "dino_management";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );

            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $this->conn;

        } catch (PDOException $e) {
            // Tampilkan error dengan format yang lebih baik
            $error_message = "Database Connection Error: " . $e->getMessage();

            // Log error ke file
            $log_dir = dirname(__DIR__) . '/logs';
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }
            error_log(date('[Y-m-d H:i:s] ') . $error_message . PHP_EOL, 3, $log_dir . '/database_errors.log');

            // Untuk development, tampilkan error
            if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
                die("<div style='padding: 20px; margin: 20px; border: 2px solid #f00; background: #fee; border-radius: 10px;'>
                    <h2 style='color: #c00;'>⚠️ Database Error</h2>
                    <p><strong>Message:</strong> {$e->getMessage()}</p>
                    <p><strong>Code:</strong> {$e->getCode()}</p>
                    <p>Please check:</p>
                    <ol>
                        <li>Laragon MySQL service is running</li>
                        <li>Database 'dino_management' exists</li>
                        <li>Username and password are correct</li>
                    </ol>
                </div>");
            } else {
                // Untuk production, tampilkan pesan umum
                die("Database connection failed. Please contact administrator.");
            }
        }
    }
}
?>
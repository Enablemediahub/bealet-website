<?php
/**
 * Bealet Website - Database Connection
 * PDO connection with error handling
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        try {
            $this->pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $this->logError('Database Connection Failed', $e->getMessage());
            if (DEBUG_MODE) {
                die('Database Connection Error: ' . $e->getMessage());
            } else {
                die('Database Connection Error. Please try again later.');
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function prepare($query) {
        try {
            return $this->pdo->prepare($query);
        } catch (PDOException $e) {
            $this->logError('Query Prepare Failed', $e->getMessage());
            throw $e;
        }
    }

    public function query($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query Execution Failed', $e->getMessage());
            throw $e;
        }
    }

    public function fetch($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }

    public function fetchAll($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }

    public function insert($query, $params = []) {
        try {
            $stmt = $this->query($query, $params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->logError('Insert Failed', $e->getMessage());
            throw $e;
        }
    }

    public function update($query, $params = []) {
        return $this->query($query, $params);
    }

    public function delete($query, $params = []) {
        return $this->query($query, $params);
    }

    public function execute($query, $params = []) {
        return $this->query($query, $params);
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }

    private function logError($title, $message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $title: $message\n";
        
        error_log($logMessage, 3, $logFile);
    }

    private function __clone() {}
    public function __wakeup() {}
}

// Get database instance
$db = Database::getInstance();

<?php
/**
 * Proxcel - Conexão com Banco de Dados
 * 
 * Conexão PDO segura com prepared statements.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => APP_DEBUG ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die("Erro de conexão: " . $e->getMessage());
            } else {
                error_log("Database connection error: " . $e->getMessage());
                die("Erro interno do servidor. Tente novamente mais tarde.");
            }
        }
    }

    /**
     * Singleton - retorna instância única da conexão
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna o objeto PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Executa query com prepared statement
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retorna um único registro
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Retorna todos os registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insere registro e retorna o ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Atualiza registro e retorna linhas afetadas
     */
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Deleta registro e retorna linhas afetadas
     */
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Retorna nome da tabela com prefixo
     */
    public static function table($name) {
        return DB_TABLE_PREFIX . $name;
    }

    /**
     * Inicia transação
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Reverte transação
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    // Previne clonagem
    private function __clone() {}

    // Previne deserialização
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function para acesso rápido ao banco
 */
function db() {
    return Database::getInstance();
}

/**
 * Helper function para nome de tabela com prefixo
 */
function tb($name) {
    return Database::table($name);
}

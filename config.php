<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros diretamente (evita exposição)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Tenta iniciar a sessão com tratamento de erro
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Erro ao iniciar sessão: " . $e->getMessage());
    die("Erro ao iniciar sessão. Verifique as permissões do diretório de sessões.");
}

// Configurações do Banco de Dados
define('SQLITE_DB', __DIR__ . '/todo.sqlite');
define('PIN_CODE', '0201');

// Debug: Logar erros em arquivo local para diagnóstico
function logError($msg) {
    $date = date('Y-m-d H:i:s');
    @file_put_contents(__DIR__ . '/debug.log', "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

/**
 * Retorna uma instância do PDO (SQLite)
 */
function getDB() {
    try {
        $db = new PDO('sqlite:' . SQLITE_DB);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Inicializa as tabelas se não existirem
        $db->exec("CREATE TABLE IF NOT EXISTS boards (
            id TEXT PRIMARY KEY,
            name TEXT,
            display_order INTEGER DEFAULT 0,
            archived INTEGER DEFAULT 0,
            columns TEXT,
            trash TEXT
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS tags (
            name TEXT PRIMARY KEY,
            color TEXT,
            scope TEXT DEFAULT 'global',
            project_ids TEXT
        )");
        
        return $db;
    } catch (Exception $e) {
        logError("Erro de Conexão SQLite: " . $e->getMessage());
        throw new Exception("Erro na conexão com o banco de dados: " . $e->getMessage());
    }
}
?>

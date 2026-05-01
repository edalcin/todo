<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Carrega variáveis de ambiente do arquivo .env manualmente (simples e sem dependências)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Erro ao iniciar sessão: " . $e->getMessage());
    die("Erro ao iniciar sessão.");
}

// Configurações do Banco de Dados
define('SQLITE_DB', __DIR__ . '/todo.sqlite');

// PIN de acesso vindo do .env ou padrão seguro
define('PIN_CODE', $_ENV['PIN_CODE'] ?? '9999');

function logError($msg) {
    $date = date('Y-m-d H:i:s');
    @file_put_contents(__DIR__ . '/debug.log', "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

function getDB() {
    try {
        $db = new PDO('sqlite:' . SQLITE_DB);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
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
        throw new Exception("Erro na conexão com o banco de dados.");
    }
}
?>

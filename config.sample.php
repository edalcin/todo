<?php
// config.sample.php - Rename to config.php and set your PIN

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

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

// DEFINA SEU PIN DE ACESSO AQUI
define('PIN_CODE', '1234');

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

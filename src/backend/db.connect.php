<?php
/**
 * Script de Conexão com o Banco de Dados
 *
 * Estabelece a conexão com o MySQL usando as credenciais
 * definidas durante o provisionamento do Vagrant.
 */

// --- Credenciais do Banco de Dados ---
// (Definidas em scripts/bootstrap.sh)
define('DB_HOST', 'localhost');
define('DB_USER', 'user');
define('DB_PASS', 'password');
define('DB_NAME', 'gerenciador_db');

/**
 * Cria e retorna uma nova conexão mysqli.
 *
 * @return mysqli
 * @throws Exception Se a conexão falhar
 */
function getDbConnection() {
    // Habilita o error reporting do MySQLi para lançar exceções
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        // Define o charset para UTF-8
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Em caso de falha, lança uma exceção genérica
        throw new Exception("Falha na conexão com o banco de dados: " . $e->getMessage());
    }
}


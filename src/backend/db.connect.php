<?php
// Configurações do banco de dados
$host = '127.0.0.1'; // Usar 127.0.0.1 ao invés de localhost pode evitar problemas de DNS
$dbname = 'gerenciador';
$user = 'webuser';
$pass = 'webpass';
$charset = 'utf8mb4';

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Opções do PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa prepares nativos do MySQL
];

try {
    // Cria a instância do PDO
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Em caso de erro na conexão, lança uma exceção com a mensagem de erro
    // Em um ambiente de produção, você deveria logar este erro e mostrar uma mensagem genérica.
    http_response_code(500);
    echo json_encode(['message' => 'Falha na conexão com o banco de dados.']);
    // throw new \PDOException($e->getMessage(), (int)$e->getCode());
    exit();
}

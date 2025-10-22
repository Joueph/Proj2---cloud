<?php
// ATIVA A EXIBIÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// CORREÇÃO: Nome do arquivo alterado para 'db.connect.php' (com ponto)
require_once(__DIR__ . '/db.connect.php');
require_once(__DIR__ . '/vagrant.php');

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Roteamento baseado no método HTTP (para corresponder ao app.js)
    switch ($method) {
        
        case 'GET':
            // Se 'log_id' estiver presente, busca o log
            if (isset($_GET['log_id'])) {
                $id = intval($_GET['log_id']);
                $logContent = VagrantManager::getLog($id);
                // Retorna como texto plano, pois o app.js espera .text()
                header('Content-Type: text/plain');
                echo $logContent ?: '(Arquivo de log vazio)';
            
            // Senão, lista os ambientes
            } else {
                $ambientes = VagrantManager::list();
                echo json_encode($ambientes);
            }
            break;

        case 'POST':
            // Cria um novo ambiente
            $data = json_decode(file_get_contents('php://input'));
            
            if (!$data || empty($data->nome) || empty($data->comando)) {
                throw new Exception("Dados inválidos. Nome e Comando são obrigatórios.");
            }

            $id = VagrantManager::create(
                $data->nome,
                $data->comando,
                $data->cpu_limit ?? 'N/A',
                $data->memoria_limit ?? 'N/A'
            );
            echo json_encode(['status' => 'success', 'id' => $id]);
            break;

        case 'DELETE':
            // Remove um ambiente
            if (!isset($_GET['id'])) {
                throw new Exception("ID do ambiente não fornecido para remoção.");
            }
            $id = intval($_GET['id']);
            VagrantManager::remove($id);
            echo json_encode(['status' => 'success', 'message' => 'Ambiente removido.']);
            break;

        default:
            throw new Exception("Método HTTP não suportado.");
    }
} catch (Exception $e) {
    // Se algo der errado, captura a exceção e retorna um JSON de erro
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>


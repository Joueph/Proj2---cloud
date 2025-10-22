<?php
// ATIVA A EXIBIÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- CORREÇÃO ---
// Usar __DIR__ garante que o PHP procure os arquivos no diretório correto.
// __DIR__ é o caminho completo para a pasta 'backend' (/var/www/html/backend)
// Garante que não há espaços extras nos nomes dos arquivos.
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/vagrant.php');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $nome = $_POST['nome'] ?? 'ambiente_sem_nome';
            $comando = $_POST['comando'] ?? '';
            $cpu = $_POST['cpu'] ?? 10;
            $memoria = $_POST['memoria'] ?? 256;

            if (empty($comando)) {
                throw new Exception("O comando não pode estar vazio.");
            }

            $id = VagrantManager::create($nome, $comando, $cpu, $memoria);
            echo json_encode(['status' => 'success', 'id' => $id]);
            break;

        case 'list':
            $ambientes = VagrantManager::list();
            echo json_encode($ambientes);
            break;

        case 'status':
            // Esta ação é chamada internamente por 'list', mas pode ser usada para debug
            $pid = $_GET['pid'] ?? 0;
            $status = VagrantManager::getStatus($pid);
            echo json_encode(['status' => $status]);
            break;

        case 'stop':
            $pid = $_POST['pid'] ?? 0;
            if (empty($pid)) {
                 throw new Exception("PID inválido.");
            }
            $success = VagrantManager::stop($pid);
            echo json_encode(['status' => $success ? 'success' : 'failed']);
            break;

        case 'get_log':
            $logFile = $_GET['log'] ?? '';
            
            // Validação de segurança básica para impedir "directory traversal"
            // basename() garante que estamos apenas pegando o nome do arquivo
            if (empty($logFile) || basename($logFile) !== $logFile) {
                 throw new Exception("Nome de arquivo de log inválido.");
            }

            $fullLogPath = "/var/www/logs/" . $logFile;

            if (!file_exists($fullLogPath)) {
                throw new Exception("Arquivo de log não encontrado em: $fullLogPath");
            }
            
            $logContent = file_get_contents($fullLogPath);
            echo json_encode(['status' => 'success', 'log' => ($logContent ?: '(Arquivo de log vazio)')]);
            break;

        default:
            throw new Exception("Ação inválida.");
    }
} catch (Exception $e) {
    // Se algo der errado, captura a exceção e retorna um JSON de erro
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>


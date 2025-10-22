<?php
// Define o tipo de conteúdo da resposta como JSON
header('Content-Type: application/json');

// Inclui os arquivos necessários
require_once 'db_connect.php';
require_once 'vagrant.php';

// Determina o método da requisição HTTP (GET, POST, DELETE, etc.)
$method = $_SERVER['REQUEST_METHOD'];

// Trata a requisição com base no método
switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        // Se o método não for suportado, retorna um erro 405
        http_response_code(405);
        echo json_encode(['message' => 'Método não permitido']);
        break;
}

/**
 * Trata as requisições GET.
 * Pode listar todos os ambientes ou obter o log de um ambiente específico.
 */
function handleGet($pdo) {
    if (isset($_GET['log_id'])) {
        // Obter log de um ambiente
        $stmt = $pdo->prepare("SELECT log_path FROM ambientes WHERE id = ?");
        $stmt->execute([$_GET['log_id']]);
        $ambiente = $stmt->fetch();

        if ($ambiente && file_exists($ambiente['log_path'])) {
            header('Content-Type: text/plain'); // Retorna como texto plano
            echo file_get_contents($ambiente['log_path']);
        } else {
            http_response_code(404);
            echo "Log não encontrado.";
        }
    } else {
        // Listar todos os ambientes
        $stmt = $pdo->query("SELECT * FROM ambientes ORDER BY data_criacao DESC");
        $ambientes = $stmt->fetchAll();
        
        // Atualiza o status antes de enviar
        foreach ($ambientes as &$ambiente) {
            if ($ambiente['status'] === 'RUNNING' && $ambiente['pid']) {
                if (!isProcessRunning($ambiente['pid'])) {
                    $ambiente['status'] = 'FINISHED';
                    // Atualiza o status no banco
                    $updateStmt = $pdo->prepare("UPDATE ambientes SET status = 'FINISHED' WHERE id = ?");
                    $updateStmt->execute([$ambiente['id']]);
                }
            }
        }

        echo json_encode($ambientes);
    }
}

/**
 * Trata as requisições POST para criar um novo ambiente.
 */
function handlePost($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validação simples dos dados de entrada
    if (empty($data['nome']) || empty($data['comando'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Nome e comando são obrigatórios.']);
        return;
    }

    $resultado = iniciarProcesso($data['comando']);

    if ($resultado['pid']) {
        $stmt = $pdo->prepare(
            "INSERT INTO ambientes (nome, comando, cpu_limit, memoria_limit, status, pid, log_path) 
             VALUES (?, ?, ?, ?, 'RUNNING', ?, ?)"
        );
        $stmt->execute([
            $data['nome'],
            $data['comando'],
            $data['cpu_limit'] ?: 'N/A',
            $data['memoria_limit'] ?: 'N/A',
            $resultado['pid'],
            $resultado['log_path']
        ]);
        http_response_code(201); // Created
        echo json_encode(['message' => 'Ambiente criado com sucesso!', 'id' => $pdo->lastInsertId()]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['message' => 'Falha ao iniciar o processo.']);
    }
}

/**
 * Trata as requisições DELETE para remover/parar um ambiente.
 */
function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'ID do ambiente não fornecido.']);
        return;
    }

    $id = $_GET['id'];

    // Busca o PID do processo no banco de dados
    $stmt = $pdo->prepare("SELECT pid, status FROM ambientes WHERE id = ?");
    $stmt->execute([$id]);
    $ambiente = $stmt->fetch();

    if ($ambiente) {
        if ($ambiente['status'] === 'RUNNING' && $ambiente['pid']) {
            pararProcesso($ambiente['pid']);
        }
        
        // Remove o registro do banco de dados (ou apenas atualiza o status para 'TERMINATED')
        $deleteStmt = $pdo->prepare("DELETE FROM ambientes WHERE id = ?");
        $deleteStmt->execute([$id]);

        // Aqui você também poderia optar por apagar o arquivo de log:
        // if (file_exists($ambiente['log_path'])) { unlink($ambiente['log_path']); }

        echo json_encode(['message' => 'Ambiente removido com sucesso.']);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['message' => 'Ambiente não encontrado.']);
    }
}


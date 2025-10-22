<?php
/**
 * Classe VagrantManager
 *
 * Simula o gerenciamento de "ambientes" (processos) dentro da VM.
 * Esta classe é o núcleo da lógica do backend.
 */
class VagrantManager {

    // Caminho base para onde os logs serão armazenados
    private const LOG_DIR = "/var/www/logs/";

    /**
     * Cria um novo "ambiente" (inicia um processo em background).
     *
     * @param string $nome Nome descritivo do ambiente
     * @param string $comando O comando de shell a ser executado
     * @param string $cpu_limit Limite de CPU (vem como string do form)
     * @param string $memoria_limit Limite de Memória (vem como string do form)
     * @return int ID do ambiente no banco de dados
     * @throws Exception Se falhar em criar o processo ou registrar no DB
     */
    public static function create($nome, $comando, $cpu_limit, $memoria_limit) {
        $conn = getDbConnection();

        // 1. Preparar o arquivo de log
        $logFile = "log_" . time() . "_" . uniqid() . ".txt";
        $fullLogPath = self::LOG_DIR . $logFile;
        
        // --- PONTO CHAVE DA IMPLEMENTAÇÃO ---
        // `nohup ... & echo $!` executa em background e retorna o PID
        
        // TODO: Implementar a lógica de namespaces e cgroups
        // $comandoReal = "systemd-run --scope -p CPUQuota={$cpu}% $comando";
        
        $comandoReal = sprintf('nohup %s > %s 2>&1 & echo $!', $comando, $fullLogPath);
        
        // 2. Executar o comando
        $pid = shell_exec($comandoReal);

        if (empty($pid) || !is_numeric(trim($pid))) {
            throw new Exception("Falha ao iniciar o processo em background. Comando: $comandoReal. Saída: $pid");
        }
        
        $pid = intval(trim($pid));

        // 3. Registrar no Banco de Dados
        // CORREÇÃO: Alterado para corresponder ao database.sql (cpu_limit, memoria_limit, log_path)
        $stmt = $conn->prepare("INSERT INTO ambientes (nome, pid, comando, cpu_limit, memoria_limit, log_path, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $status = 'Running'; // O status "Running" corresponde ao CSS (status.running)
        
        // CORREÇÃO: bind_param alterado de "sisdiss" para "sisssss" para corresponder às colunas VARCHAR
        $stmt->bind_param("sisssss", $nome, $pid, $comando, $cpu_limit, $memoria_limit, $logFile, $status);
        
        if (!$stmt->execute()) {
            // Se falhar ao salvar no DB, tenta matar o processo órfão
            self::stop($pid);
            throw new Exception("Falha ao registrar o ambiente no banco de dados: " . $stmt->error);
        }

        $id = $stmt->insert_id;
        $stmt->close();
        $conn->close();

        return $id;
    }

    /**
     * Lista todos os ambientes registrados no banco de dados,
     * atualizando seus status (se estão rodando ou não).
     *
     * @return array
     */
    public static function list() {
        $conn = getDbConnection();
        $ambientes = [];

        // CORREÇÃO: Alterado nome das colunas no SELECT para corresponder ao database.sql
        $result = $conn->query("SELECT id, nome, comando, cpu_limit, memoria_limit, status, pid, log_path, data_criacao FROM ambientes ORDER BY data_criacao DESC");
        
        while ($row = $result->fetch_assoc()) {
            // Atualiza o status ANTES de enviar para o frontend
            if ($row['status'] == 'Running') {
                $row['status'] = self::getStatus($row['pid']);
                
                // Se o status mudou, atualiza o DB
                if ($row['status'] != 'Running') {
                    $stmt = $conn->prepare("UPDATE ambientes SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $row['status'], $row['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $ambientes[] = $row;
        }

        $result->free();
        $conn->close();
        return $ambientes;
    }

    /**
     * Para (mata) um processo em execução baseado no seu PID.
     *
     * @param int $pid O Process ID a ser terminado
     * @return bool True se o comando foi enviado
     */
    public static function stop($pid) {
        if (empty($pid) || !is_numeric($pid)) {
            return false;
        }
        
        // Usa `kill` para terminar o processo.
        shell_exec(sprintf('kill %d 2>/dev/null', $pid));
        
        // Atualiza o status no DB
        try {
            $conn = getDbConnection();
            // CORREÇÃO: Atualiza o status para "Finished" (para corresponder ao CSS status.finished)
            $stmt = $conn->prepare("UPDATE ambientes SET status = 'Finished' WHERE pid = ? AND status = 'Running'");
            $stmt->bind_param("i", $pid);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // Ignora erros de DB aqui
        }

        return true;
    }

    /**
     * Verifica se um processo (PID) ainda está em execução no sistema.
     *
     * @param int $pid O Process ID
     * @return string O status ("Running", "Finished", "Error")
     */
    public static function getStatus($pid) {
        if (empty($pid) || !is_numeric($pid)) {
            return 'Error';
        }

        $output = trim(shell_exec(sprintf('ps -p %d -o pid=', $pid)));

        if ($output == $pid) {
            return 'Running';
        }

        // CORREÇÃO: Retorna "Finished" para corresponder ao CSS (status.finished)
        return 'Finished'; 
    }

    /**
     * Remove um ambiente do banco de dados (e tenta parar o processo).
     *
     * @param int $id O ID do ambiente no DB
     * @return bool
     * @throws Exception
     */
    public static function remove($id) {
        $conn = getDbConnection();
        
        // 1. Pega o PID antes de deletar
        $stmt = $conn->prepare("SELECT pid FROM ambientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && !empty($row['pid'])) {
            // 2. Tenta parar o processo
            self::stop($row['pid']);
        }
        $stmt->close();

        // 3. Deleta o registro do banco
        $stmt = $conn->prepare("DELETE FROM ambientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if (!$success) {
            throw new Exception("Falha ao remover o ambiente do banco de dados: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        return true;
    }

    /**
     * Busca o conteúdo do log de um ambiente pelo ID.
     *
     * @param int $id O ID do ambiente no DB
     * @return string O conteúdo do log
     * @throws Exception
     */
    public static function getLog($id) {
        $conn = getDbConnection();
        
        // 1. Pega o caminho do log no DB
        // CORREÇÃO: Seleciona a coluna `log_path`
        $stmt = $conn->prepare("SELECT log_path FROM ambientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$row || empty($row['log_path'])) {
            throw new Exception("Arquivo de log não encontrado no registro do banco.");
        }

        $fullLogPath = self::LOG_DIR . $row['log_path'];

        if (!file_exists($fullLogPath)) {
            throw new Exception("Arquivo de log não existe no disco: $fullLogPath");
        }

        return file_get_contents($fullLogPath);
    }
}


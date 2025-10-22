<?php
/**
 * Classe VagrantManager
 * Gerencia processos e estatísticas do sistema.
 */
class VagrantManager {

    private const LOG_DIR = "/var/www/logs/";

    /**
     * Cria um novo "ambiente" (inicia um processo em background).
     * Aplica limites de cgroup (CPU/Memória) usando systemd-run.
     *
     * @param string $nome
     * @param string $comando
     * @param string $cpu_limit (ex: "50%" ou "N/A")
     * @param string $memoria_limit (ex: "256M" ou "N/A")
     * @return int
     * @throws Exception
     */
    public static function create($nome, $comando, $cpu_limit, $memoria_limit) {
        $conn = getDbConnection();

        $logFile = "log_" . time() . "_" . uniqid() . ".txt";
        $fullLogPath = self::LOG_DIR . $logFile;
        
        // --- IMPLEMENTAÇÃO CGROUPS (Início) ---

        // 1. Comando base que será executado
        //    (nohup... & echo $! para obter o PID)
        $comandoBase = sprintf('nohup %s > %s 2>&1 & echo $!', $comando, $fullLogPath);

        // 2. Comando systemd-run (requer sudo, configurado no bootstrap.sh)
        //    Usamos '--scope' para criar um cgroup transitório
        //    Usamos '--quiet' para suprimir a saída do systemd-run
        $cgroupCmd = "sudo /usr/bin/systemd-run --scope --quiet";
        
        $hasLimits = false;

        // 3. Adicionar limite de CPU, se fornecido
        if ($cpu_limit !== 'N/A' && !empty($cpu_limit)) {
            // Remove 'N/A', '%' ou qualquer coisa que não seja número
            $cpu_quota_val = intval(preg_replace('/[^0-9]/', '', $cpu_limit));
            if ($cpu_quota_val > 0) {
                // systemd-run aceita CPUQuota=XX%
                $cgroupCmd .= " -p CPUQuota={$cpu_quota_val}%";
                $hasLimits = true;
            }
        }

        // 4. Adicionar limite de Memória, se fornecido
        if ($memoria_limit !== 'N/A' && !empty($memoria_limit)) {
            // Garante que o formato seja algo como '256M' (padrão do systemd)
            $mem_limit_val = escapeshellarg(strtoupper($memoria_limit));
            $cgroupCmd .= " -p MemoryLimitBytes={$mem_limit_val}";
            $hasLimits = true;
        }

        // 5. Montar o comando final
        $comandoReal = "";
        if ($hasLimits) {
            // Executa o comando base (nohup...) dentro de um shell ('sh -c')
            // O 'sh -c' é executado dentro do cgroup criado pelo systemd-run
            $comandoReal = sprintf('%s sh -c %s',
                $cgroupCmd,
                escapeshellarg($comandoBase) // Coloca 'nohup ping... & echo $!' entre aspas
            );
        } else {
            // Se nenhum limite foi definido, executa como antes (sem sudo/systemd)
            $comandoReal = $comandoBase;
        }
        // --- IMPLEMENTAÇÃO CGROUPS (Fim) ---

        
        // 6. Executar o comando (com ou sem cgroups)
        $pid = shell_exec($comandoReal);

        if (empty($pid) || !is_numeric(trim($pid))) {
            // Se falhar, $pid pode conter uma mensagem de erro (ex: do sudo)
            throw new Exception("Falha ao iniciar o processo. (Verifique as permissões 'sudoers' para www-data). Comando: $comandoReal. Saída: $pid");
        }
        
        $pid = intval(trim($pid));

        // 7. Registrar no Banco de Dados
        $stmt = $conn->prepare(
            "INSERT INTO ambientes (nome, pid, comando, cpu_limit, memoria_limit, log_path, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $status = 'Running'; // O status correto é 'Running'
        $stmt->bind_param("sisssss", $nome, $pid, $comando, $cpu_limit, $memoria_limit, $logFile, $status);
        
        if (!$stmt->execute()) {
            // Se falhar ao salvar no DB, tenta matar o processo órfão
            self::stop($pid);
            throw new Exception("Falha ao registrar no banco: " . $stmt->error);
        }

        $id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        return $id;
    }

    /**
     * Lista todos os ambientes, com filtros e ordenação.
     *
     * @param string $filter_status Filtra por status (ex: 'all', 'Running', 'Finished')
     * @param string $sort_by Ordena os resultados (ex: 'data_criacao_desc')
     * @return array
     * @throws Exception
     */
    public static function list($filter_status = 'all', $sort_by = 'data_criacao_desc') {
        $conn = getDbConnection();
        $ambientes = [];
        
        // --- Construção da Query Dinâmica ---
        $sql = "SELECT id, nome, comando, cpu_limit, memoria_limit, status, pid, log_path, data_criacao FROM ambientes";
        $params = [];
        $types = "";

        // 1. Adicionar Filtro (WHERE)
        if ($filter_status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }

        // 2. Adicionar Ordenação (ORDER BY) - Whitelist para segurança
        $order_clause = " ORDER BY data_criacao DESC"; // Padrão
        switch ($sort_by) {
            case 'data_criacao_asc':
                $order_clause = " ORDER BY data_criacao ASC";
                break;
            case 'cpu_desc':
                // Converte o VARCHAR '50%' para o número 50 para ordenar corretamente
                $order_clause = " ORDER BY CAST(cpu_limit AS UNSIGNED) DESC";
                break;
            case 'memoria_desc':
                // Converte o VARCHAR '256M' para o número 256
                $order_clause = " ORDER BY CAST(memoria_limit AS UNSIGNED) DESC";
                break;
        }
        $sql .= $order_clause;
        // --- Fim da Construção da Query ---
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             throw new Exception("Falha ao preparar a query: " . $conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Atualiza o status ANTES de enviar para o frontend
            if ($row['status'] == 'Running') {
                $row['status'] = self::getStatus($row['pid']);
                
                if ($row['status'] != 'Running') {
                    // Atualiza o status no DB para "Finished" ou "Error"
                    $updateStmt = $conn->prepare("UPDATE ambientes SET status = ? WHERE id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("si", $row['status'], $row['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }
            }
            $ambientes[] = $row;
        }

        $stmt->close();
        $conn->close();
        return $ambientes;
    }

    /**
     * Para (mata) um processo e remove do banco.
     *
     * @param int $id O ID do ambiente no DB
     * @return bool
     * @throws Exception
     */
    public static function remove($id) {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("SELECT pid, status FROM ambientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['status'] === 'Running' && !empty($row['pid'])) {
            // Tenta matar o processo
            self::stop($row['pid']);
        }
        $stmt->close();

        // Remove o registo do banco
        $stmt = $conn->prepare("DELETE FROM ambientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Falha ao remover o ambiente: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        return true;
    }

    /**
     * Função interna para parar um processo (kill)
     */
    private static function stop($pid) {
        if (empty($pid) || !is_numeric($pid)) return false;
        // Usa `kill` para terminar o processo.
        shell_exec(sprintf('kill %d 2>/dev/null', $pid));
        return true;
    }

    /**
     * Verifica se um processo (PID) ainda está em execução.
     */
    public static function getStatus($pid) {
        if (empty($pid) || !is_numeric($pid)) return 'Error';
        // `ps -p $pid` procura pelo processo
        // `-o pid=` imprime apenas o PID (sem cabeçalho)
        $output = trim(shell_exec(sprintf('ps -p %d -o pid=', $pid)));
        
        // Se a saída for igual ao PID, o processo está a rodar.
        // Se for vazia, o processo terminou.
        return ($output == $pid) ? 'Running' : 'Finished';
    }

    /**
     * Busca o conteúdo do log de um ambiente pelo ID.
     */
    public static function getLog($id) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT log_path FROM ambientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$row || empty($row['log_path'])) {
            throw new Exception("Log não encontrado no banco.");
        }
        
        // Validação de segurança básica (impede 'directory traversal')
        $logFile = basename($row['log_path']);
        $fullLogPath = self::LOG_DIR . $logFile;

        if (!file_exists($fullLogPath)) {
            throw new Exception("Arquivo de log não existe no disco: $fullLogPath");
        }
        return file_get_contents($fullLogPath);
    }

    /**
     * NOVO: Busca estatísticas de CPU e Memória da VM.
     * (Versão corrigida e mais robusta)
     *
     * @return array
     */
    public static function getSystemStats() {
        // --- Get Memória Usage ---
        // 'LC_ALL=C' garante que a saída seja em inglês (para 'grep Mem')
        // Altera o awk para retornar 'usado' e 'total' separados por espaço
        $mem_cmd = "LC_ALL=C free -m | grep Mem | awk '{print $3 \" \" $2}'";
        $mem_output = trim(shell_exec($mem_cmd));
        $mem_parts = explode(' ', $mem_output);
        
        $mem_used = isset($mem_parts[0]) ? intval($mem_parts[0]) : 0;
        $mem_total = isset($mem_parts[1]) ? intval($mem_parts[1]) : 4096; // 4096 como fallback
        $mem_percent = 0;
        if ($mem_total > 0) {
            $mem_percent = ($mem_used / $mem_total) * 100;
        }

        // --- Get CPU Usage ---
        // 'LC_ALL=C' garante que a saída seja em inglês (para 'grep' e decimal '.')
        $cpu_cmd = "LC_ALL=C top -bn1 | grep -i \"Cpu(s)\" | awk '{print 100 - $8}'";
        $cpu_output = trim(shell_exec($cpu_cmd));
        $cpu_percent = floatval($cpu_output);

        // Log de debug (pode ser removido em produção)
        if (empty($mem_output)) {
            error_log("Comando de Memória falhou: $mem_cmd");
        }
        if (empty($cpu_output) || !is_numeric($cpu_output)) {
             error_log("Comando de CPU falhou: $cpu_cmd - Saída: $cpu_output");
        }

        // Retorna os dados brutos para o frontend
        return [
            'cpu_percent' => $cpu_percent,
            'mem_used' => $mem_used,
            'mem_total' => $mem_total,
            'mem_percent' => $mem_percent
        ];
    }
}
?>


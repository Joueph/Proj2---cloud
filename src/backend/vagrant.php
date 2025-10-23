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
        
        // Comando base para obter o PID.
        $comandoBase = sprintf('nohup %s > %s 2>&1 & echo $!', $comando, $fullLogPath);

        // Comando systemd-run para criar um cgroup transitório (requer sudo).
        $cgroupCmd = "sudo /usr/bin/systemd-run --scope --quiet";
        
        $hasLimits = false;

        if ($cpu_limit !== 'N/A' && !empty($cpu_limit)) {
            $cpu_quota_val = intval(preg_replace('/[^0-9]/', '', $cpu_limit));
            if ($cpu_quota_val > 0) {
                $cgroupCmd .= " -p CPUQuota={$cpu_quota_val}%";
                $hasLimits = true;
            }
        }

        if ($memoria_limit !== 'N/A' && !empty($memoria_limit)) {
            $mem_limit_val_str = strtoupper(trim($memoria_limit));

            // Se o usuário digitou apenas um número (ex: 256), assume que são Megabytes (M).
            if (is_numeric($mem_limit_val_str)) {
                $mem_limit_val_str .= "M";
            }
            
            // Valida o formato (ex: "256M", "1G") para prevenir injeção de comando.
            if (!preg_match('/^[0-9]+[KMG]?$/', $mem_limit_val_str)) {
                throw new Exception("Formato de memória inválido. Use um número (ex: 256) ou um valor com sufixo (ex: 256M, 1G).");
            }
            

            // [FIX] NÃO usar escapeshellarg() aqui. systemd-run quer o valor literal, 
            // não uma string entre aspas.
            // A propriedade correta no Cgroups v2 (Ubuntu 20.04) é 'MemoryMax'.
            $cgroupCmd .= " -p MemoryMax={$mem_limit_val_str}";
            $hasLimits = true;
        }

        // Sempre executa dentro do systemd-run para garantir que o processo esteja em seu próprio Cgroup.
        $comandoReal = sprintf('%s sh -c %s',
            $cgroupCmd,
            escapeshellarg($comandoBase)
        );

        $pid = shell_exec($comandoReal . ' 2>&1');

        if (empty($pid) || !is_numeric(trim($pid))) {
            // Se falhar, $pid pode conter uma mensagem de erro (ex: do sudo).
            throw new Exception("Falha ao iniciar o processo. (Verifique as permissões 'sudoers' para www-data). Comando: $comandoReal. Saída: $pid");
        }
        
        $pid = intval(trim($pid));

        // 7. Registrar no Banco de Dados
        $stmt = $conn->prepare(
            "INSERT INTO ambientes (nome, pid, comando, cpu_limit, memoria_limit, log_path, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $status = 'Running';
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
        
        $sql = "SELECT id, nome, comando, cpu_limit, memoria_limit, status, pid, log_path, data_criacao FROM ambientes";
        $params = [];
        $types = "";

        if ($filter_status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }

        // 2. Adicionar Ordenação (ORDER BY) - Whitelist para segurança
        $order_clause = " ORDER BY data_criacao DESC"; // Ordenação padrão
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
            // Atualiza o status em tempo real antes de enviar para o frontend.
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
        // Usa 'kill -9' (SIGKILL) para forçar o término de processos que podem
        // ignorar o 'kill' (SIGTERM) padrão.
        shell_exec(sprintf('kill -9 %d 2>/dev/null', $pid));
        return true;
    }

    /**
     * Verifica se um processo (PID) ainda está em execução.
     */
    public static function getStatus($pid) {
        if (empty($pid) || !is_numeric($pid)) return 'Error';
        // `ps -p $pid -o pid=` imprime apenas o PID (sem cabeçalho) se o processo existir.
        $output = trim(shell_exec(sprintf('ps -p %d -o pid=', $pid)));
        // Se a saída for igual ao PID, o processo está rodando.
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
        
        // Validação de segurança para impedir 'directory traversal'.
        $logFile = basename($row['log_path']);
        $fullLogPath = self::LOG_DIR . $logFile;

        if (!file_exists($fullLogPath)) {
            throw new Exception("Arquivo de log não existe no disco: $fullLogPath");
        }
        return file_get_contents($fullLogPath);
    }

    /**
     * Busca estatísticas de CPU e Memória da VM.
     *
     * @return array
     */
    public static function getSystemStats() {
        // 'LC_ALL=C' garante que a saída do comando seja em inglês.
        $mem_cmd = "LC_ALL=C free -m | grep Mem | awk '{print $3 \" \" $2}'";
        $mem_output = trim(shell_exec($mem_cmd));
        $mem_parts = explode(' ', $mem_output);
        
        $mem_used = isset($mem_parts[0]) ? intval($mem_parts[0]) : 0;
        $mem_total = isset($mem_parts[1]) ? intval($mem_parts[1]) : 4096; // 4096 como fallback
        $mem_percent = 0;
        if ($mem_total > 0) {
            $mem_percent = ($mem_used / $mem_total) * 100;
        }

        // awk '{print 100 - $15 - $16}' calcula o uso de CPU (100 - %idle - %iowait).
        $cpu_cmd = "LC_ALL=C vmstat 1 2 | tail -1 | awk '{print 100 - $15 - $16}'";
        $cpu_output = trim(shell_exec($cpu_cmd));
        $cpu_percent = floatval($cpu_output);

        if (empty($mem_output)) {
            error_log("Comando de Memória falhou: $mem_cmd");
        }
        if (empty($cpu_output) || !is_numeric($cpu_output)) {
             error_log("Comando de CPU falhou: $cpu_cmd - Saída: $cpu_output");
        }

        return [
            'cpu_percent' => $cpu_percent,
            'mem_used' => $mem_used,
            'mem_total' => $mem_total,
            'mem_percent' => $mem_percent
        ];
    }
}
?>

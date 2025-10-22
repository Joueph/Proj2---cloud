<?php
/**
 * Classe VagrantManager
 *
 * Simula o gerenciamento de "ambientes" (processos) dentro da VM.
 * Esta classe é o núcleo da lógica do backend.
 */
class VagrantManager {

    // Caminho base para onde os logs serão armazenados
    // (Deve ser o mesmo que está mapeado no Vagrantfile e no bootstrap.sh)
    private const LOG_DIR = "/var/www/logs/";

    /**
     * Cria um novo "ambiente" (inicia um processo em background).
     *
     * @param string $nome Nome descritivo do ambiente
     * @param string $comando O comando de shell a ser executado
     * @param int $cpu Limite de CPU (ainda não implementado)
     * @param int $memoria Limite de Memória (ainda não implementado)
     * @return int ID do ambiente no banco de dados
     * @throws Exception Se falhar em criar o processo ou registrar no DB
     */
    public static function create($nome, $comando, $cpu, $memoria) {
        $conn = getDbConnection();

        // 1. Preparar o arquivo de log
        // Gera um nome de arquivo de log único
        $logFile = "log_" . time() . "_" . uniqid() . ".txt";
        $fullLogPath = self::LOG_DIR . $logFile;
        
        // --- PONTO CHAVE DA IMPLEMENTAÇÃO ---
        // Aqui é onde o comando real é montado.
        // `nohup` : Garante que o processo continue rodando mesmo se o script PHP terminar.
        // `... > $fullLogPath 2>&1` : Redireciona stdout (1) e stderr (2) para o mesmo arquivo de log.
        // `&` : Coloca o processo em background.
        // `echo $!` : Imprime o Process ID (PID) do processo que acabou de ser iniciado.
        // `2>&1` no final garante que o PID seja a única saída (stderr -> stdout).
        
        // TODO: Implementar a lógica de namespaces e cgroups (limites de CPU/Memória)
        // Por enquanto, executa o comando diretamente.
        // A lógica de cgroups/namespaces (ex: usando `systemd-run` ou `unshare`)
        // deveria ser adicionada aqui, envolvendo o $comando.
        // Ex (pseudo-código): $comandoReal = "systemd-run --scope -p CPUQuota={$cpu}% $comando";
        
        $comandoReal = sprintf('nohup %s > %s 2>&1 & echo $!', $comando, $fullLogPath);
        
        // 2. Executar o comando
        // shell_exec() executa o comando e retorna a saída (que deve ser apenas o PID)
        $pid = shell_exec($comandoReal);

        if (empty($pid) || !is_numeric(trim($pid))) {
            throw new Exception("Falha ao iniciar o processo em background. Comando: $comandoReal. Saída: $pid");
        }
        
        $pid = intval(trim($pid));

        // 3. Registrar no Banco de Dados
        $stmt = $conn->prepare("INSERT INTO ambientes (nome, pid, comando, cpu, memoria, log_file, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $status = 'Em Execução';
        $stmt->bind_param("sisdiss", $nome, $pid, $comando, $cpu, $memoria, $logFile, $status);
        
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

        $result = $conn->query("SELECT * FROM ambientes ORDER BY data_criacao DESC");
        
        while ($row = $result->fetch_assoc()) {
            // Atualiza o status ANTES de enviar para o frontend
            if ($row['status'] == 'Em Execução') {
                $row['status'] = self::getStatus($row['pid']);
                
                // Se o status mudou de "Em Execução" para algo, atualiza o DB
                if ($row['status'] != 'Em Execução') {
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
     * @return bool True se o comando foi enviado, False se falhou
     */
    public static function stop($pid) {
        if (empty($pid) || !is_numeric($pid)) {
            return false;
        }
        
        // Usa `kill` para terminar o processo.
        // O `2>/dev/null` suprime erros (ex: se o processo já morreu)
        shell_exec(sprintf('kill %d 2>/dev/null', $pid));
        
        // Atualiza o status no DB
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("UPDATE ambientes SET status = 'Terminado (manual)' WHERE pid = ? AND status = 'Em Execução'");
            $stmt->bind_param("i", $pid);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // Ignora erros de DB aqui, o foco é parar o processo
        }

        return true;
    }

    /**
     * Verifica se um processo (PID) ainda está em execução no sistema.
     *
     * @param int $pid O Process ID
     * @return string O status ("Em Execução", "Terminado", "Erro")
     */
    public static function getStatus($pid) {
        if (empty($pid) || !is_numeric($pid)) {
            return 'Erro (PID inválido)';
        }

        // Comando mágico do Linux:
        // `ps -p $pid` : Procura pelo processo com o PID
        // `-o pid=` : Pede para imprimir SÓ o PID (sem cabeçalho)
        // O `trim` remove espaços em branco.
        $output = trim(shell_exec(sprintf('ps -p %d -o pid=', $pid)));

        // Se a saída for igual ao PID, o processo está rodando.
        if ($output == $pid) {
            return 'Em Execução';
        }

        // Se a saída for vazia, o processo não está mais rodando.
        // (Não podemos saber se terminou com sucesso ou erro, apenas que terminou)
        return 'Terminado'; 
    }
}


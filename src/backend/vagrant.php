<?php

// NOTA: A especificação do projeto menciona namespaces e cgroups.
// A implementação direta via PHP a partir de um servidor web (usuário www-data)
// é complexa e exige permissões elevadas.
// Esta abordagem simplificada foca em gerenciar processos em background,
// que cumpre os requisitos centrais de iniciar, parar e monitorar a saída de um comando.

/**
 * Inicia um comando como um processo em background.
 *
 * @param string $command O comando a ser executado.
 * @return array Um array com 'pid' e 'log_path'. Retorna pid nulo em caso de falha.
 */
function iniciarProcesso($command) {
    // Define o caminho para a pasta de logs. Garanta que esta pasta exista e tenha permissões de escrita para o usuário do Apache (www-data).
    $logDir = '/var/www/logs/';
    // Cria um nome de arquivo de log único
    $logFile = $logDir . 'ambiente_' . time() . '_' . uniqid() . '.log';

    // O comando é modificado para:
    // 1. `nohup ...`: Garante que o processo não morra se a sessão do terminal for fechada.
    // 2. `> "$logFile" 2>&1`: Redireciona tanto a saída padrão (stdout) quanto a saída de erro (stderr) para o nosso arquivo de log.
    // 3. `&`: Executa o comando em background.
    // 4. `echo $!`: Imediatamente após, imprime o PID (Process ID) do último processo iniciado em background.
    $fullCommand = sprintf('nohup %s > %s 2>&1 & echo $!', $command, $logFile);

    // Executa o comando e captura a saída (que será o PID)
    $pid = shell_exec($fullCommand);

    // Limpa a saída para garantir que temos apenas o número do PID
    $pid = trim($pid);

    if (is_numeric($pid)) {
        return ['pid' => (int)$pid, 'log_path' => $logFile];
    }

    return ['pid' => null, 'log_path' => null];
}

/**
 * Para um processo usando seu PID.
 *
 * @param int $pid O PID do processo a ser parado.
 * @return bool True se o comando kill foi enviado, false caso contrário.
 */
function pararProcesso($pid) {
    if (!is_numeric($pid) || $pid <= 0) {
        return false;
    }
    // `kill` envia o sinal SIGTERM (15) por padrão, que é uma terminação "graciosa".
    // Para forçar, seria `kill -9 $pid` (SIGKILL).
    shell_exec("kill " . (int)$pid);
    return true;
}

/**
 * Verifica se um processo com um determinado PID está em execução.
 *
 * @param int $pid O PID do processo a ser verificado.
 * @return bool True se o processo está rodando, false caso contrário.
 */
function isProcessRunning($pid) {
    if (!is_numeric($pid) || $pid <= 0) {
        return false;
    }
    // `ps -p $pid` retorna um status de saída 0 se o processo existe, e 1 caso contrário.
    // A saída do comando é capturada em $output e o status em $return_var.
    exec("ps -p " . (int)$pid, $output, $return_var);
    return $return_var === 0;
}

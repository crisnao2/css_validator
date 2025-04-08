<?php

require __DIR__ . '/../vendor/autoload.php';

// Definir SIGTERM manualmente, já que a extensão pcntl não está disponível
if (!defined('SIGTERM')) {
    define('SIGTERM', 15);
}

if (!defined('SIGKILL')) {
    define('SIGKILL', 9);
}

// Iniciar o servidor (Nginx e PHP-FPM) em background
$pidFile = '/tmp/test-server.pid';
$logFile = '/tmp/test-server.log';

if (is_file($pidFile)) {
    unlink($pidFile);
}

if (is_file($logFile)) {
    unlink($logFile);
}

// Iniciar PHP-FPM e Nginx usando tini
$command = "tini -s -- sh -c 'php-fpm -D && nginx -g \"daemon off;\"' > $logFile 2>&1 & echo $! > $pidFile";
exec($command);

// Aguardar até que o servidor esteja pronto (porta 8080)
$maxAttempts = 60;
$attempt = 0;
while ($attempt < $maxAttempts) {
    if (@fsockopen('127.0.0.1', 8080)) {
        break;
    }
    $attempt++;
    sleep(1);
}

if ($attempt === $maxAttempts) {
    echo "Erro: Não foi possível iniciar o servidor na porta 8080 após $maxAttempts tentativas.\n";
    // Mostrar logs finais para depuração
    if (is_file($logFile)) {
        echo "Logs finais do servidor:\n";
        echo file_get_contents($logFile) . "\n";
    }
    // Mostrar logs adicionais do Nginx e PHP-FPM
    if (is_file('/var/log/nginx/error.log')) {
        echo "Logs do Nginx (error.log):\n";
        echo file_get_contents('/var/log/nginx/error.log') . "\n";
    }
    if (is_file('/var/log/php-fpm.log')) {
        echo "Logs do PHP-FPM:\n";
        echo file_get_contents('/var/log/php-fpm.log') . "\n";
    }
    exit(1);
}

// Registrar um shutdown para encerrar o servidor
register_shutdown_function(function () use ($pidFile) {
    if (is_file($pidFile)) {
        $pid = (int)file_get_contents($pidFile);
        if ($pid) {
            // Enviar SIGTERM para o processo tini
            posix_kill($pid, SIGTERM);
            // Aguardar o processo terminar
            $start = time();
            while (posix_getpgid($pid) !== false) {
                if (time() - $start > 5) {
                    // Forçar a parada se não terminar em 5 segundos
                    posix_kill($pid, SIGKILL);
                    break;
                }
                usleep(100000); // 0.1 segundos
            }
        }
        unlink($pidFile);
    }
});
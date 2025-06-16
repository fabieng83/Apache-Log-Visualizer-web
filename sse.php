<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Désactiver le buffering pour Nginx, si utilisé

set_time_limit(0); // Pas de timeout
ob_end_flush(); // Vider le buffer de sortie
flush();

function parseLogLine($line) {
    $pattern = '/^(\S+:\d+)\s+(\S+)\s+-\s+-\s+\[([^\]]+)\]\s+"(\S+)\s+(\S+)\s+[^"]+"\s+(\d+)\s+(\d+|-)/';
    if (preg_match($pattern, $line, $matches)) {
        $vhost = $matches[1];
        $ip = $matches[2];
        $method = $matches[4];
        $url = strtok($matches[5], '?');
        $status = intval($matches[6]);
        $size = $matches[7] === '-' ? 0 : intval($matches[7]);
        $dateStr = str_replace(':', ' ', $matches[3]);
        $timestamp = strtotime($dateStr) ?: time();
        return [
            'id' => uniqid(),
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'size' => $size,
            'timestamp' => $timestamp
        ];
    }
    return null;
}

// Surveiller le fichier de log avec tail -f
$logFile = '/var/log/apache2/other_vhosts_access.log';
if (!is_readable($logFile)) {
    error_log("Cannot read $logFile: Permission denied or file does not exist");
    echo "data: {\"error\": \"Cannot read log file\"}\n\n";
    flush();
    exit;
}

$handle = popen("tail -f $logFile 2>&1", 'r');
if ($handle === false) {
    error_log("Failed to execute tail -f $logFile");
    echo "data: {\"error\": \"Failed to tail log file\"}\n\n";
    flush();
    exit;
}

while (!feof($handle)) {
    $line = fgets($handle);
    if ($line) {
        $logEntry = parseLogLine($line);
        if ($logEntry) {
            error_log("Sending log: " . json_encode($logEntry)); // Debug
            echo "data: " . json_encode([$logEntry]) . "\n\n"; // Envoyer comme tableau pour compatibilité
            flush();
        }
    }
}

pclose($handle);
?>

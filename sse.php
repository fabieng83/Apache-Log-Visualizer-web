<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for Nginx, if used

set_time_limit(0); // No timeout
ob_end_flush(); // Clear output buffer
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

// Path to the bash script
$bashScriptPath = 'tail_log.sh'; // Adjust to the actual path
if (!is_readable($bashScriptPath)) {
    error_log("Cannot read $bashScriptPath: Permission denied or file does not exist");
    echo "data: {\"error\": \"Cannot read bash script\"}\n\n";
    flush();
    exit;
}

// Ensure the bash script is executable
if (!is_executable($bashScriptPath)) {
    chmod($bashScriptPath, 0755);
}

// Start the bash script with popen
$command = "bash $bashScriptPath 2>&1";
$handle = popen($command, 'r');
if ($handle === false) {
    error_log("Failed to execute $command");
    echo "data: {\"error\": \"Failed to execute bash script\"}\n\n";
    flush();
    exit;
}

while (!feof($handle)) {
    $line = fgets($handle);
    if ($line) {
        // Check for error messages from the bash script
        if (strpos($line, "Error: ") === 0) {
            error_log($line);
            echo "data: {\"error\": \"" . trim($line) . "\"}\n\n";
            pclose($handle);
            flush();
            exit;
        }
        $logEntry = parseLogLine($line);
        if ($logEntry) {
            error_log("Sending log: " . json_encode($logEntry)); // Debug
            echo "data: " . json_encode([$logEntry]) . "\n\n"; // Send as array for compatibility
            flush();
        }
    }
}

pclose($handle);
?>

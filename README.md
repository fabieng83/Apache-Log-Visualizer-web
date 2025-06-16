# Apache-Log-Visualizer-web
This project provides a Server-Sent Events (SSE) implementation to stream Apache access logs in real-time to a web client for visualization. The backend is written in PHP and monitors an Apache log file, parsing and streaming all log entries to the client.<br>
<img src="screenshot.png" width="80%" align="center">
## Features
- Real-time streaming of Apache access logs using Server-Sent Events (SSE).
- `sse.php` parses log entries to extract meaningful data (e.g., method, URL, status code, size, timestamp).
- `tail_log.sh` monitors the `tail -f` process for excessive CPU usage and terminates it if a threshold is breached, reporting an error.
## Project Structure
- `sse.php`: The PHP script that handles log file monitoring and streams parsed log entries to the client via SSE.
- `tail_log.sh`: A bash script executed by `sse.php` to tail the Apache log file. It includes CPU usage monitoring for the `tail` process.
- `index.html`: The frontend HTML file (currently empty) intended to display the streamed log data.
## Prerequisites
- Read permissions for the Apache log file (`/var/log/apache2/other_vhosts_access.log`) on the local server.
- `bash` interpreter available on the server.
- Standard Unix utilities `ps`, `awk`, `tail`, `kill` (used by `tail_log.sh`).
## How it Works
1.  The client (`index.html`) connects to `sse.php` to initiate a Server-Sent Events (SSE) stream.
2.  `sse.php` executes `tail_log.sh`.
3.  `tail_log.sh` performs the following:
    *   Verifies read access to the Apache log file (configurable via `LOG_FILE`).
    *   Starts `tail -f` on the log file, outputting new lines.
    *   Monitors the CPU usage of the `tail -f` process. If `CPU_THRESHOLD` is exceeded, it terminates `tail -f` and outputs an error.
4.  `sse.php` reads output from `tail_log.sh`:
    *   Lines starting with "Error: " are sent to the client as SSE error events.
    *   Other lines are parsed as Apache log entries.
5.  Successfully parsed log entries are converted to JSON and streamed to the client via SSE.
6.  `index.html` receives and visualizes these SSE events (log data or errors).
## Configuration
The `tail_log.sh` script contains the following configurable variables at the top of the file:
- `LOG_FILE`: Path to the Apache access log file (default: `"/var/log/apache2/other_vhosts_access.log"`).
- `CPU_THRESHOLD`: CPU usage percentage (integer) above which the `tail -f` process will be terminated (default: `50`).
- `CHECK_INTERVAL`: Interval in seconds at which CPU usage is checked (default: `10`).
## Log Parsing
The `sse.php` script parses lines from `tail_log.sh` (which outputs lines from the Apache log file) if they are not error messages. Each successfully parsed log entry is transformed into a JSON object with the following fields:
- `id`: Unique identifier for the log entry.
- `method`: HTTP method (e.g., GET, POST).
- `url`: Requested URL (without query parameters).
- `status`: HTTP status code (e.g., 200, 404).
- `size`: Response size in bytes.
- `timestamp`: Unix timestamp of the log entry.
## Troubleshooting
- Ensure the Apache log file (specified by `LOG_FILE` in `tail_log.sh`) is readable by the user executing the PHP script (e.g., `www-data`). For default log locations, you might need to add the PHP user to a group like `adm`:
```bash
sudo usermod -a -G adm www-data
```

#!/bin/bash
LOG_FILE="/var/log/apache2/other_vhosts_access.log"
CPU_THRESHOLD=10  # CPU usage threshold in percentage
CHECK_INTERVAL=2  # Period to check CPU usage (seconds)
PID=$$  # PID of this bash script

# Check if log file is readable
if [ ! -r "$LOG_FILE" ]; then
    echo "Error: Cannot read $LOG_FILE" >&2
    exit 1
fi

# Function to get CPU usage of the current process
get_cpu_usage() {
    local pid=$1
    # Use ps to get CPU usage for the given PID
    ps -p "$pid" -o %cpu --no-headers | awk '{print $1}'
}

# Run tail -f in the background
tail -f "$LOG_FILE" &
TAIL_PID=$!

# Monitor CPU usage
while true; do
    # Get CPU usage of the tail process
    cpu_usage=$(get_cpu_usage "$TAIL_PID")
    if [ -n "$cpu_usage" ]; then
        cpu_usage_int=${cpu_usage%.*}  # Convert to integer for comparison
        if [ "$cpu_usage_int" -gt "$CPU_THRESHOLD" ]; then
            echo "Error: CPU usage ($cpu_usage%) exceeds threshold ($CPU_THRESHOLD%)" >&2
            kill -9 "$TAIL_PID"  # Kill the tail process
            exit 1
        fi
    else
        echo "Error: Tail process ($TAIL_PID) not found" >&2
        exit 1
    fi
    sleep "$CHECK_INTERVAL"
done &

# Wait for the tail process to exit
wait "$TAIL_PID"

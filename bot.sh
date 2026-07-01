#!/bin/bash

PID_FILE="bot.pid"
LOG_FILE="bot.log"

start() {
    if [ -f "$PID_FILE" ] && kill -0 "$(cat $PID_FILE)" 2>/dev/null; then
        echo "Bot allaqachon ishlamoqda (PID: $(cat $PID_FILE))"
        exit 1
    fi
    nohup python bot.py >> "$LOG_FILE" 2>&1 &
    echo $! > "$PID_FILE"
    echo "Bot ishga tushdi (PID: $(cat $PID_FILE))"
}

stop() {
    if [ ! -f "$PID_FILE" ]; then
        echo "Bot ishlamayapti"
        exit 1
    fi
    kill "$(cat $PID_FILE)" && rm -f "$PID_FILE"
    echo "Bot to'xtatildi"
}

status() {
    if [ -f "$PID_FILE" ] && kill -0 "$(cat $PID_FILE)" 2>/dev/null; then
        echo "Bot ishlamoqda (PID: $(cat $PID_FILE))"
    else
        echo "Bot ishlamayapti"
    fi
}

case "$1" in
    start)   start ;;
    stop)    stop ;;
    restart) stop; sleep 1; start ;;
    status)  status ;;
    logs)    tail -f "$LOG_FILE" ;;
    *)       echo "Ishlatish: $0 {start|stop|restart|status|logs}" ;;
esac

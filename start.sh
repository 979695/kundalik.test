#!/bin/sh
set -e

echo "=================================================="
echo "  Smart Life Assistant - Ishga tushmoqda..."
echo "=================================================="

echo "[1/3] Migratsiyalar bajarilmoqda..."
php artisan migrate --force
echo "      Migratsiyalar tayyor!"

echo "[2/3] Bildirishnoma scheduleri ishga tushmoqda..."
php artisan schedule:work >> /tmp/schedule.log 2>&1 &
SCHEDULE_PID=$!
echo "      Scheduler PID: $SCHEDULE_PID"

echo "[3/3] Web server ishga tushmoqda (port: ${PORT:-8000})..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

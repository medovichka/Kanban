set -u

cd "$(dirname "$0")/.."

while :; do
    php bin/cron.php --tick || echo "cron tick завершился с ошибкой, продолжаю" >&2
    sleep 60
done

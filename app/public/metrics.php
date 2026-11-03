<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Metrics;
use Prometheus\RenderTextFormat;

Config::load(__DIR__ . '/../config/config.php');

if (!Metrics::enabled()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Метрики выключены: включите их (APP_METRICS_ENABLED=1, расширение apcu) вместе с сервисом prometheus из compose.yaml\n";
    exit;
}

try {
    $output = (new RenderTextFormat())->render(Metrics::registry()->getMetricFamilySamples());
} catch (\Throwable $e) {
    error_log('metrics: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Не удалось собрать метрики: " . $e->getMessage() . "\n";
    exit;
}

header('Content-Type: ' . RenderTextFormat::MIME_TYPE);
echo $output;

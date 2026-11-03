<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Config;

$command = $argv[1] ?? null;

switch ($command) {
    case 'key':
        echo base64_encode(random_bytes(32)) . PHP_EOL;
        exit(0);

    case 'encrypt':
        $value = $argv[2] ?? null;

        if ($value === null) {
            fwrite(STDERR, 'Использование: php config/secret.php encrypt "значение"' . PHP_EOL);
            exit(1);
        }

        echo Config::encrypt($value) . PHP_EOL;
        exit(0);

    case 'decrypt':
        $payload = $argv[2] ?? null;

        if ($payload === null) {
            fwrite(STDERR, 'Использование: php config/secret.php decrypt "enc:..."' . PHP_EOL);
            exit(1);
        }

        echo Config::decrypt($payload) . PHP_EOL;
        exit(0);

    case 'check':
        Config::load(__DIR__ . '/config.php');
        echo 'OK: APP_KEY корректен, конфиг загружен и зашифрованные значения расшифрованы.' . PHP_EOL;
        exit(0);

    default:
        fwrite(STDERR, implode(PHP_EOL, [
            'Утилита для работы с зашифрованными значениями конфига.',
            '',
            'Команды:',
            '  key                 сгенерировать новый APP_KEY',
            '  encrypt "<значение>" зашифровать значение для конфига',
            '  decrypt "enc:..."   расшифровать значение',
            '  check               проверить APP_KEY и загрузку конфига',
        ]) . PHP_EOL);
        exit(1);
}

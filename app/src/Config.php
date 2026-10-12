<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Загрузчик статического конфига с поддержкой зашифрованных значений.
 *
 * Любая строка с префиксом "enc:" при загрузке прозрачно расшифровывается
 * (AES-256-GCM). Ключ берётся из переменной окружения APP_KEY (base64, 32 байта).
 */
final class Config
{
    private const PREFIX = 'enc:';
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /** @var array<string, mixed>|null */
    private static ?array $items = null;

    /**
     * Загружает файл конфига и расшифровывает все значения с префиксом "enc:".
     *
     * @param string $path Путь до файла конфига, возвращающего массив.
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Config file not found: ' . $path);
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new RuntimeException('Config file must return an array: ' . $path);
        }

        self::$items = self::decryptRecursive($data);
    }

    /**
     * Возвращает значение по ключу в точечной нотации ("db.database").
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$items === null) {
            throw new RuntimeException('Config is not loaded. Call App\Config::load() first.');
        }

        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$items === null) {
            throw new RuntimeException('Config is not loaded. Call App\Config::load() first.');
        }

        return self::$items;
    }

    /**
     * Шифрует значение и возвращает строку "enc:<base64>", пригодную для конфига.
     */
    public static function encrypt(string $plaintext, ?string $key = null): string
    {
        $key ??= self::keyFromEnv();

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt value.');
        }

        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Расшифровывает значение вида "enc:<base64>".
     */
    public static function decrypt(string $payload, ?string $key = null): string
    {
        $key ??= self::keyFromEnv();

        if (!str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Encrypted value must start with "' . self::PREFIX . '".');
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Malformed encrypted value.');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt value. Check that APP_KEY matches the one used for encryption.');
        }

        return $plaintext;
    }

    private static function keyFromEnv(): string
    {
        $encoded = getenv('APP_KEY');

        if (!is_string($encoded) || $encoded === '') {
            throw new RuntimeException('APP_KEY environment variable is not set.');
        }

        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function decryptRecursive(array $data): array
    {
        $result = [];

        foreach ($data as $name => $value) {
            if (is_array($value)) {
                $result[$name] = self::decryptRecursive($value);
            } elseif (is_string($value) && str_starts_with($value, self::PREFIX)) {
                $result[$name] = self::decrypt($value);
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}

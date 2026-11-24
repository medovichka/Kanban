<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

final class DatabaseSessionDriver implements SessionDriver
{
    public function __construct(
        private readonly int $defaultLifetime = 86400,
    ) {
    }

    public function read(string $id): ?SessionData
    {
        $row = self::table('sessions')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $data = self::table('session_data')
            ->where('session_id', $id)
            ->where('data_key', 'session')
            ->value('data_value');

        $payload = is_string($data) && $data !== '' ? @unserialize($data, ['allowed_classes' => false]) : [];

        return new SessionData(
            (string) $row->id,
            (int) $row->user_id,
            is_array($payload) ? $payload : [],
            (int) $row->expires_at,
            (int) $row->created_at,
        );
    }

    public function write(string $id, int $userId, array $payload, int $lifetime): void
    {
        $now = time();
        $lifetime = max(60, $lifetime > 0 ? $lifetime : $this->defaultLifetime);
        $expiresAt = $now + $lifetime;

        $exists = self::table('sessions')->where('id', $id)->exists();

        if (!$exists) {
            self::table('sessions')->insert([
                'id' => $id,
                'user_id' => $userId,
                'ip_address' => self::clientIp(),
                'user_agent' => self::userAgent(),
                'created_at' => $now,
                'last_activity_at' => $now,
                'expires_at' => $expiresAt,
            ]);
        } else {
            self::table('sessions')->where('id', $id)->update([
                'last_activity_at' => $now,
                'expires_at' => $expiresAt,
            ]);
        }

        $rows = [];
        foreach ($payload as $key => $value) {
            $rows[] = [
                'session_id' => $id,
                'data_key' => (string) $key,
                'data_value' => serialize($value),
            ];
        }

        self::table('session_data')->where('session_id', $id)->delete();

        if ($rows !== []) {
            self::table('session_data')->insert($rows);
        }
    }

    public function destroy(string $id): void
    {
        self::table('session_data')->where('session_id', $id)->delete();
        self::table('sessions')->where('id', $id)->delete();
    }

    public function destroyAllForUser(int $userId): int
    {
        $ids = self::table('sessions')->where('user_id', $userId)->pluck('id')->all();

        foreach ($ids as $id) {
            $this->destroy((string) $id);
        }

        return count($ids);
    }

    public function gc(int $now): int
    {
        $expiredIds = self::table('sessions')->where('expires_at', '<=', $now)->pluck('id')->all();

        if ($expiredIds === []) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($expiredIds, 500) as $chunk) {
            self::table('session_data')->whereIn('session_id', $chunk)->delete();
            $deleted += self::table('sessions')->whereIn('id', $chunk)->delete();
        }

        return $deleted;
    }

    public function generateId(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function healthy(): bool
    {
        try {
            self::table('sessions')->limit(1)->get();

            return true;
        } catch (\Throwable $e) {
            error_log('session: ' . $e->getMessage());

            return false;
        }
    }

    public function name(): string
    {
        return 'database';
    }

    private static function table(string $name): Builder
    {
        return Capsule::connection()->table($name);
    }

    private static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) ? substr($ip, 0, 45) : null;
    }

    private static function userAgent(): ?string
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return is_string($agent) ? substr($agent, 0, 255) : null;
    }
}

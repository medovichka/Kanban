<?php

declare(strict_types=1);

namespace App\Cron;

final class CronResult
{
    public const OK = 'ok';
    public const SKIP = 'skip';
    public const ERROR = 'error';

    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        public readonly float $duration = 0.0,
    ) {
    }

    public static function ok(string $message = '', float $duration = 0.0): self
    {
        return new self(self::OK, $message, $duration);
    }

    public static function skip(string $message = ''): self
    {
        return new self(self::SKIP, $message);
    }

    public static function error(string $message, float $duration = 0.0): self
    {
        return new self(self::ERROR, $message, $duration);
    }

    public function toRow(string $job, int $startedAt): array
    {
        return [
            'job' => $job,
            'status' => $this->status,
            'message' => mb_substr($this->message, 0, 1000),
            'duration_ms' => (int) round($this->duration * 1000),
            'started_at' => date('Y-m-d H:i:s', $startedAt),
            'finished_at' => date('Y-m-d H:i:s'),
        ];
    }
}

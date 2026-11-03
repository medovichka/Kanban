<?php

declare(strict_types=1);

namespace App\Cron;

use DateTimeImmutable;

final class CronExpression
{
    private const FIELDS = [
        'minute' => [0, 59],
        'hour' => [0, 23],
        'dayOfMonth' => [1, 31],
        'month' => [1, 12],
        'dayOfWeek' => [0, 7],
    ];

    private array $values = [];

    public function __construct(public readonly string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($parts) !== 5) {
            throw new \InvalidArgumentException(sprintf('Cron выражение "%s" должно состоять из 5 полей.', $expression));
        }

        $names = array_keys(self::FIELDS);
        foreach ($names as $index => $name) {
            [$min, $max] = self::FIELDS[$name];
            $this->values[$name] = self::parseField($parts[$index], $min, $max, $expression);
        }

        if (in_array(7, $this->values['dayOfWeek'], true) && !in_array(0, $this->values['dayOfWeek'], true)) {
            $this->values['dayOfWeek'][] = 0;
            sort($this->values['dayOfWeek']);
        }
    }

    public function isDue(DateTimeImmutable $moment): bool
    {
        $zone = new \DateTimeZone(date_default_timezone_get());
        $local = $moment->setTimezone($zone);

        return in_array((int) $local->format('i'), $this->values['minute'], true)
            && in_array((int) $local->format('G'), $this->values['hour'], true)
            && in_array((int) $local->format('n'), $this->values['month'], true)
            && $this->dayMatches($local);
    }

    public function values(string $field): array
    {
        if (!isset($this->values[$field])) {
            throw new \InvalidArgumentException('Неизвестное cron-поле: ' . $field);
        }

        return $this->values[$field];
    }

    public function nextRun(DateTimeImmutable $after): DateTimeImmutable
    {
        $moment = $after->setTime((int) $after->format('G'), (int) $after->format('i'), 0);

        for ($i = 0; $i < 527_040; $i++) {
            $moment = $moment->modify('+1 minute');
            if ($this->isDue($moment)) {
                return $moment;
            }
        }

        throw new \InvalidArgumentException(sprintf('Cron выражение "%s" не срабатывает никогда.', $this->expression));
    }

    private function dayMatches(DateTimeImmutable $moment): bool
    {
        $dayOfMonth = $this->values['dayOfMonth'];
        $dayOfWeek = $this->values['dayOfWeek'];

        $domRestricted = count($dayOfMonth) !== 31;
        $dowRestricted = $dayOfWeek !== range(0, 7);

        $domMatches = in_array((int) $moment->format('j'), $dayOfMonth, true);
        $dowMatches = in_array((int) $moment->format('w'), $dayOfWeek, true);

        if ($domRestricted && $dowRestricted) {
            return $domMatches || $dowMatches;
        }

        return $domMatches && $dowMatches;
    }

    private static function parseField(string $field, int $min, int $max, string $expression): array
    {
        $values = [];

        foreach (explode(',', $field) as $piece) {
            $step = 1;
            $range = $piece;

            if (str_contains($piece, '/')) {
                [$range, $stepString] = explode('/', $piece, 2);
                if ($stepString === '' || !ctype_digit($stepString) || (int) $stepString < 1) {
                    throw new \InvalidArgumentException(sprintf('Некорректный шаг "%s" в cron "%s".', $piece, $expression));
                }
                $step = (int) $stepString;
            }

            if ($range === '*') {
                $from = $min;
                $to = $max;
            } elseif (str_contains($range, '-')) {
                [$fromString, $toString] = explode('-', $range, 2);
                $from = self::toInt($fromString, $expression);
                $to = self::toInt($toString, $expression);
            } else {
                $from = self::toInt($range, $expression);
                $to = $from;
            }

            if ($from < $min || $to > $max || $from > $to) {
                throw new \InvalidArgumentException(sprintf('Значение вне диапазона [%d..%d] в "%s" (cron "%s").', $min, $max, $piece, $expression));
            }

            for ($value = $from; $value <= $to; $value += $step) {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private static function toInt(string $value, string $expression): int
    {
        if ($value === '' || !ctype_digit($value)) {
            throw new \InvalidArgumentException(sprintf('Некорректное значение "%s" в cron "%s".', $value, $expression));
        }

        return (int) $value;
    }
}

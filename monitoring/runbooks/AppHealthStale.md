# AppHealthStale

**Что значит.** Prometheus дольше 2 минут не получает `/metrics.php` приложения
(`up{job="app"} == 0`). Последние значения `app_health_check{...}` остаются
в TSDB и продолжают выглядеть зелёными, хотя данные уже неактуальны. Это и
есть пробел, который раньше не был виден: «последнее известное состояние = 1»
не равно «жив».

**Как проверить.**
- Prometheus → Status → Targets → `app`: ошибка скрейпа (500, таймаут, текст
  «Метрики выключены»).
- `docker compose -f compose.yaml -f compose.monitoring.yaml exec nginx sh -c 'wget -qO- http://php:9000/status'`
  — жив ли php-fpm (FastCGI-статус).
- `docker compose ... logs php` — ошибки PHP, права на `app/logs`.
- `docker compose ... exec php sh -c 'wget -qO- http://127.0.0.1:8080/metrics.php'` — ответ сам по себе.

**Что делать.**
- 503 «Метрики выключены» — убедитесь, что стенд поднят с `compose.monitoring.yaml`
  и `APP_METRICS_ENABLED=1` есть в окружении php, а расширение apcu загружено
  (`php -m | grep apcu`).
- Ошибка PHP в логах — чинить код; на один неудачный скрейп алерт не сработает
  (for: 2m).
- Параллельно смотрите `AppHealthStale` вместе с `ContainerRestartLoop`: если
  контейнер php рестартует, метрики в APCu теряются даже при живом здоровье.

**Воспроизвести.**
```bash
docker compose -f compose.yaml -f compose.monitoring.yaml pause php
```
Prometheus перестаёт получать ответ (`up{job="app"}` = 0), а панель
`app_health_check` на графике остаётся с последним значением 1 — показывать
ей нечего, хотя ещё минуту назад всё было видно. Вернуть:
`docker compose ... unpause php`.

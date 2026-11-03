# ContainerRestartLoop / ContainerOOMKilled

**Что значит.** Сервис перезапускается сам (restart: unless-stopped) больше трёх
раз за 10 минут — по метрике cAdvisor `container_start_time_seconds`, — или
контейнер был убит из-за лимита памяти (`container_oom_events_total`).
Между падениями контейнер успевает ответить на health-check, поэтому
`HealthCheckFailing` и `up` могут оставаться зелёными: старая схема видела
«сейчас здоров», но не видела «падает каждые 20 секунд».

**Как проверить.**
- `docker compose ps` — колонка `Status` (`Restarting (255) 3 seconds ago`).
- `docker compose events --since 10m` — история start/die.
- `docker compose logs --since 10m <сервис>` — причина выхода.
- `docker stats --no-stream` — память рядом с лимитом.
- В Prometheus: `container_memory_working_set_bytes{name="<имя>"}` рядом с
  `container_spec_memory_limit_bytes{name="<имя>"}`.

**Что делать.**
- OOM: увеличить лимит памяти контейнера/хоста (`docker stats` покажет
  пиковое потребление), для php — `pm.max_children`, для JVM —
  `JAVA_OPTS`.
- Код выходит с ошибкой — смотреть логи; если это php-fpm и он падает
  из-за `max_children`, сработает и `PhpFpmPoolSaturated`.
- Забытый `entrypoint` без цикла — чинить команду запуска: `sleep 60` на
  последней строке скрипта завершает контейнер и превращает его в петлю.

**Воспроизвести.**
```bash
docker compose exec php sh -c 'kill -9 1'
```
(контейнер перезапустится, `container_start_time_seconds` вырастет; за 10 минут
повторить 4 раза, чтобы алерт стал firing). Для OOM — `docker update --memory 64m php`
и любой запрос к приложению.

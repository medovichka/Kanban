# ExternalPortDown

**Что значит.** Проба через проброшенный на хост порт 8080
(`blackbox-external`, адрес `http://host.docker.internal:8080/health.php`)
не проходит дольше минуты. При этом внутренняя проба `blackbox-health`
(на `http://nginx/health.php` из docker-сети) может оставаться зелёной, и
`up` у всех экспортеров равен 1.

Это ровно тот пробел, который был в исходном стенде: мониторинг смотрел на
приложение только изнутри docker-сети и в принципе не мог увидеть, что
опубликованный порт не работает или занят.

**Как проверить.**
- С хоста: `curl -i http://127.0.0.1:8080/health.php` — refused / timeout /
  чужой ответ.
- `docker compose ps` — у nginx есть `0.0.0.0:8080->80/tcp` / `127.0.0.1:8080->80/tcp`?
- `docker compose logs nginx` — ошибка `bind() to 0.0.0.0:80 failed` или
  `address already in use`.
- `docker compose exec prometheus wget -qO- 'http://host.docker.internal:8080/nginx-health'`.
- На Linux: в `compose.monitoring.yaml` у prometheus должен быть
  `extra_hosts: ["host.docker.internal:host-gateway"]`, иначе проба сама
  падает с `no such host` и будет ложной.

**Что делать.**
- Порт потерян в compose.yaml — вернуть `ports:` у nginx.
- Порт занят — найти владельца (`ss -ltnp | grep 8080`) и либо освободить,
  либо поменять `NGINX_PORT` в `.env`.
- Порт есть, но не отвечает — `docker compose up -d --force-recreate nginx`.

**Воспроизвести.**
```bash
NGINX_PORT=18080 docker compose -f compose.yaml -f compose.monitoring.yaml up -d nginx
```
Через ~1 минуту в Mailpit придёт `[FIRING] ExternalPortDown`, при этом
`probe_success{job="blackbox-health"}` и все `up` останутся 1 — старые алерты
не сработают. Вернуть: `docker compose ... up -d --force-recreate nginx`.

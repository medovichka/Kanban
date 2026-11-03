# Задание 2: форк local_zoo_for_vyatgu — мониторинг

Всё по второму домашнему заданию собрано здесь, в `monitoring/`, и не смешивается
с заданием 1 (Kanban): тот код живёт в `app/`, этот — отдельный стек
(compose-оверлей + конфиги), который поднимается поверх базового `compose.yaml`.

Сам форк: <https://github.com/killo/local_zoo_for_vyatgu> (пуш не выполнялся,
изменения в этом репозитории готовы к переносу — см. «Как перенести в форк»).

---

## 1. Как устроен мониторинг в форке (что есть «из коробки»)

Стенд поднимается слоями: `compose.yaml` (nginx + php-fpm + PostgreSQL) и
`compose.monitoring.yaml` поверх него.

```
            ┌──────────────┐        ┌──────────────────────────────┐
  браузер → │ nginx :8080  │ →php→  │ php-fpm :9000 /status        │
            └──────┬───────┘        └──────────────┬───────────────┘
                   │  :8081 stub_status            │ чтение /metrics.php в APCu
   nginx-exporter ─┘                               └──── app (белый ящик)
   php-fpm-exporter ────────────────────────────────────────────────┐
   postgres-exporter ─────→ PostgreSQL                              │
   blackbox-exporter ─────→ http://nginx/health.php, /nginx-health  │
                   ┌──────────────────────────────────────────────▼──┴───┐
                   │ Prometheus (pull раз в 15 с) → TSDB на 7 дней      │
                   │   правила алертов → Alertmanager → Mailpit (письма)│
                   └──────────────────┬─────────────────────────────────┘
                                      │ PromQL
                                  Grafana (provisioning из JSON)
```

- **Prometheus** опрашивает 9 job'ов: сам себя, nginx (stub_status через
  nginx-exporter), php-fpm (`/status` по FastCGI через php-fpm-exporter),
  PostgreSQL (postgres-exporter + `pg_stat_statements`), приложение (`/metrics.php`),
  Alertmanager, blackbox-exporter и две black-box пробы.
- **Приложение отдаёт** (`app/public/metrics.php`, white-box): `http_requests_total{method,route,status}`,
  гистограмму `http_request_duration_seconds{method,route}`, бизнес-счётчик
  `app_notes_created_total`. Счётчики лежат в APCu, потому что PHP shared-nothing.
- **Black-box** проба смотрит снаружи, как пользователь: `probe_success` по
  `/health.php` (код 200 + тело `"status":"ok"`) и `/nginx-health`.
- **Алерты** (`prometheus/rules/alerts.yml`): TargetDown, HealthCheckFailing,
  HighErrorRate (5xx > 5% при трафике > 0.2 RPS), HighLatencyP95 (> 1 c),
  PhpFpmPoolSaturated, PostgresDown, PostgresTooManyConnections. У каждого —
  runbook в `runbooks/`, письма — в Mailpit (http://127.0.0.1:8025).
- **Дашборды** в Grafana: «Обзор сервиса» (доступность, RED, насыщение
  php-fpm, PostgreSQL, бизнес-метрика) и два community-дашборда (NGINX,
  PostgreSQL) — всё через provisioning из JSON, ничего не настраивается мышкой.

## 2. Чего в нём не хватало (найденный пробел)

Стенд отвечает на вопрос «приложение выглядит здоровым **изнутри docker-сети**»,
но не на вопрос «**пользователь** может открыть сайт». Все сетевые проверки —
и white-box, и black-box — идут через `http://nginx/…`, то есть по внутренней
docker-сети; публикация порта (`ports: 127.0.0.1:8080:80` в compose) нигде не
проверяется.

Конкретные сценарии, которые стенд **не заметит** (проверено рассуждением по
конфигам; на живом контейнере не воспроизводилось — поднимать не требовалось):

| Сценарий | Что видит старый стенд | Что видит пользователь |
|---|---|---|
| из compose.yaml убрали `ports:` у nginx (например, при реорге) | всё зелёное: `up`=1 у всех, `probe_success`=1, TargetDown/HealthCheckFailing молчат | connection refused |
| порт 8080 на хосте занят другим процессом | то же самое (и даже `docker compose ps` не показывает ошибку, если проброс слушает кто-то другой) | чужой сервис или отказ |
| nginx слушает только 80 внутри, публикация на другой порт | всё зелёное | пустая страница |
| контейнер молча рестартует каждые 20 с (`restart: unless-stopped`) между скрейпами | `up`=1 — успевает ответить; health-чек тоже зелёный | часть запросов падает, сессии/счётчики теряются |
| `/metrics.php` отдаёт 500, а `/health.php` — 200 | логи RED-панелей просто «замирают» на последнем значении; `health_check` остаётся 1 | не видно вообще ничего, пока не упадут |

Другими словами: **окно снаружи** и **доступность контейнеров/приложения во
времени** не наблюдались совсем.

Ещё замечания по ходу разбора (не чинил, чтобы не раздувать задание):

- `runbook_url` во всех алертах указывал на `docs/runbooks/` своим путём в
  исходном репозитории — в форке ссылки вели бы не туда.
- `HighErrorRate` считает долю по 1 минуте при `for: 2m` и минимальном трафике
  0.2 RPS — при 15-секундном скрейпе 2 «ошибочных» запроса из 3 дают > 5%:
  получится минимум 4 ошибки, окей. Но однофайловый всплеск короче минуты
  алерт не поднимет — это осознанный выбор, он описан в runbook.
- В `prometheus.yml` нет `nginx`-метрик в черных пробах внешнего порта — см. ниже.

## 3. Что добавлено

### 3.1 Новая проба снаружи — `blackbox-external`

- В `prometheus.yml`: job `blackbox-external` ходит на
  `http://host.docker.internal:8080/health.php` — **через опубликованный на хост
  порт**, а не через docker-сеть. На Linux у Prometheus в `compose.monitoring.yaml`
  добавлен `extra_hosts: host.docker.internal:host-gateway`.
- Новый модуль `http_health_strict` в `blackbox/blackbox.yml`: 200, непустой JSON
  с `"status":"ok"`, без HTML — ловит «живой прокси, который отдаёт страницу ошибки».
- **Метрики**: `probe_success{job="blackbox-external"}`, `probe_duration_seconds`,
  `probe_http_status_code`.

### 3.2 Метрики контейнеров — cAdvisor

- В `compose.monitoring.yaml` добавлен cAdvisor (только профильные метрики:
  cpu, memory, network, oom_event).
- **Метрики**: `container_memory_working_set_bytes`, `container_cpu_usage_seconds_total`,
  `container_start_time_seconds`, `container_oom_events_total` с меткой `container`.

### 3.3 Новые алерты (`prometheus/rules/alerts.yml`, группа `gaps`)

| Алерт | Условие | Почему закрывает пробел |
|---|---|---|
| **ExternalPortDown** | `probe_success{job="blackbox-external"} == 0` (1 m) | ловит «внутри зелёное — снаружи не открыть» |
| ExternalProbe5xx | `probe_http_status_code{job=~"blackbox-.*"} >= 500` (2 m) | ловит живой, но отвечающий мусором вход |
| **AppHealthStale** | `up{job="app"} == 0` (2 m) | «последнее известное 1» не значит «жив»: метрики приложения устарели |
| **ContainerRestartLoop** | `sum by (container) (changes(container_start_time_seconds[10m])) > 3` (5 m) | петля рестартов между скрейпами |
| ContainerOOMKilled | `sum by (container) (increase(container_oom_events_total[10m])) > 0` | контейнер убит OOM — запросы падали |

У новых алертов — runbooks: `runbooks/ExternalPortDown.md`, `runbooks/AppHealthStale.md`,
`runbooks/ContainerRestartLoop.md`. В Alertmanager добавлено подавление:
`ExternalPortDown` гасит `HealthCheckFailing` (причина важнее симптома).
Ссылки `runbook_url` переведены на `https://github.com/killo/local_zoo_for_vyatgu/blob/main/runbooks/`.

### 3.4 Дашборд Grafana (`monitoring/grafana/dashboards/service-overview.json`)

Это пришедший в `monitoring/` из форка дашборд «Обзор сервиса», дополненный
тремя новыми рядами сверху (старые панели сохранены):

**Ряд «Внешний вход — новый взгляд снаружи»**
- «Вход снаружи (host.docker.internal:8080)» — `probe_success{job="blackbox-external"}` (stat, красный = пользователь не откроет сайт);
- «Внутренняя проба (для сравнения)» — `probe_success{job="blackbox-health"}`;
- «Время ответа проб: снаружи vs внутри» — две линии `probe_duration_seconds`;
- «Метрики приложения получаются?» — `up{job="app"}`;
- «Контейнеры: стартов за 15 мин» — `sum by (name) (changes(container_start_time_seconds[15m]))`;
- «OOM-убийства за 1ч» — `sum by (name) (increase(container_oom_events_total[1h]))`.

**Ряд «Мои метрики: контейнеры и health приложения»**
- Память и CPU контейнеров, график рестартов;
- «Health-проверки приложения» (stat) и «во времени» (`app_health_check{check}` для всех вариантов приложения).

У каждой панели заполнено описание (i) — что показывает и что означает красное.

## 4. Как проверить у себя

```bash
# 1) стенд + мониторинг
docker compose -f compose.yaml -f compose.monitoring.yaml up -d

# 2) конфиги валидны (как в make mon-check)
docker compose -f compose.yaml -f compose.monitoring.yaml run --rm --no-deps \
  --entrypoint promtool prometheus check rules /etc/prometheus/rules/alerts.yml

# 3) все target'ы зелёные, включая новый blackbox-external
#    http://127.0.0.1:9090/targets

# 4) новая метрика есть и равна 1
curl -sG http://127.0.0.1:9090/api/v1/query \
  --data-urlencode 'query=probe_success{job="blackbox-external"}'

# 5) дашборд: http://127.0.0.1:3000/d/service-overview → верхний ряд «Внешний вход»
#    (Grafana поднимается с provisioning, дашборд — home dashboard)

# 6) воспроизвести пробел и увидеть, что теперь он виден:
#    (в старом стенде этого не увидел бы никто)
docker compose up -d --force-recreate nginx -P 0     # забрали публикацию порта
#    → через ~1 мин: probe_success{job="blackbox-external"} = 0,
#      app_alerts: ExternalPortDown = firing, письмо в http://127.0.0.1:8025
#      probe_success{job="blackbox-health"} остаётся 1 — старые проверки молчат
```

Приёмка: на дашборде при полностью рабочем стенде — все панели верхних двух
рядов зелёные; внешняя проба в Prometheus в статусе UP; в
`/api/v1/rules` группа `gaps` присутствует.

> Подъём контейнеров в рамках задания не выполнялся (нужен только код, по
> условию); шаги выше — инструкция, а не лог запуска.

## 5. Как перенести в форк

Всё для форка — в `monitoring/`; при переносе:

1. `monitoring/prometheus/{prometheus.yml,rules}` → `docker/prometheus/`;
2. `monitoring/blackbox/blackbox.yml` → `docker/blackbox/blackbox.yml`;
3. `monitoring/grafana/dashboards/service-overview.json` →
   `docker/grafana/dashboards/service/service-overview.json`;
4. `monitoring/grafana/provisioning/*` → `docker/grafana/provisioning/*`;
5. `monitoring/nginx/status.conf` → `docker/nginx/conf.d/status.conf`;
6. `monitoring/alertmanager/alertmanager.yml` → `docker/alertmanager/alertmanager.yml`;
7. `monitoring/runbooks/*` → `docs/runbooks/*`;
8. сервисы `nginx`, `prometheus` (extra_hosts), `cadvisor` — из
   `compose.monitoring.yaml` в форковый `compose.monitoring.yaml`.

Пути в конфигах после переноса поменяются, остальное — как есть.

# Kanban

Учебный проект: kanban-борд на чистом PHP 8.5 (без фреймворка), SQLite, php-fpm
за nginx, всё в Docker Compose. Состоит из двух частей:

- **`app/`** — приложение (домашнее задание 1: сессии в БД, cron-задачи,
  абстрактный контроллер и маршрутизация);
- **`monitoring/`** — мониторинг учебного стенда (домашнее задание 2, форк
  [local_zoo_for_vyatgu](https://github.com/romicharroyo/local_zoo_for_vyatgu)):
  Prometheus, Grafana, Alertmanager, экспортеры, новые метрики/алерты/дашборд.
  Подробный разбор — в [README-monitoring.md](README-monitoring.md).

---

# Домашнее задание 1

Три требования и то, где они реализованы:

| Требование | Реализация |
|---|---|
| Кастомный механизм сессий не на файлах | `app/src/Session/` — сессии в БД (`sessions`, `session_data`), cookie, TTL, ротация id, CSRF |
| Механизм крон-задач | `app/src/Cron/` + `app/bin/cron.php` + сервис `scheduler` в compose |
| Абстрактный контроллер и маршрутизация | `app/src/Http/Controller.php`, `app/src/Routing/`, таблица `app/config/routes.php` |

## Сессии в БД

```
app/src/Session/
  SessionDriver.php          интерфейс хранилища (read/write/destroy/gc/generateId/healthy)
  DatabaseSessionDriver.php  реализация на БД: sessions + session_data, очистка по expires_at
  ArraySessionDriver.php     реализация в памяти — для тестов и локального «потыкать»
  SessionManager.php         cookie, TTL со скользящим продлением, CSRF-токен
  Session.php                неизменяемый объект сессии, write-through в БД
  SessionData.php            прочитанное состояние
app/src/Http/InteractsWithSession.php   trait для контроллеров: session(), user(), flash(), cookie
app/src/Http/Middleware/Authenticate.php  «только с сессией» (401 для /api, редирект для HTML)
```

Почему не `session_start()` с файлами:

- одно хранилище для всех php-fpm реплик — не нужно привязывать пользователя к
  инстансу (`sticky sessions`);
- сессию можно посчитать и почистить SQL-запросом: cron-задача `sessions.gc`
  удаляет просроченные по `expires_at`, а не ждёт случайного срабатывания
  сборщика мусора PHP;
- сессии переживают деплой и пересоздание контейнера, их можно отозвать
  (`/dashboard` → «закрыть сессии на других устройствах», `DELETE /api/sessions/{id}`);
- в шапке `sessions` уже есть ip и user-agent — видно, «откуда я залогинен».

Безопасность: в cookie лежит только 43-символьный id (32 случайных байта
base64url), cookie — `HttpOnly; SameSite=Lax`, `Secure` при HTTPS; id
проверяется регуляркой и по таблице; при входе гостевая сессия удаляется и
создаётся новая (защита от session fixation); изменяющие запросы требуют
CSRF-токен (поле `_csrf` или заголовок `X-CSRF-Token`).

Таблицы (`app/db/migrations/20261103100000_create_sessions_tables.php`):

```
sessions(id PK, user_id→users ON DELETE CASCADE, ip_address, user_agent,
         created_at, last_activity_at, expires_at, индексы user_id, expires_at)
session_data(session_id→sessions, data_key, data_value, PK(session_id, data_key))
```

Драйвер выбирается в `app/config/config.php` (`session.driver` = `database` | `array`),
TTL — `session.lifetime`.

## Cron-задачи

```
app/src/Cron/
  JobInterface.php        name() + schedule() (cron-выражение) + run(CronContext): CronResult
  CronExpression.php      свой парсер 5-полевых выражений (*, */n, a-b, a,b) и nextRun()
  CronScheduler.php       тик: каким задачам пора → блокировка → запуск → журнал → метрики
  CronLock.php            не больше одного выполнения задачи одновременно (таблица cron_locks)
  CronHistory.php         журнал cron_runs: статус, длительность, время
  CronJobRegistry.php     реестр задач по имени
  Jobs/CleanupJobs.php    sessions.gc (*/10), cron.history_prune (0 3 * * *), app.health_probe (*/5)
  Jobs/HeartbeatJob.php   демо-задача * * * * *
app/config/cron.php       реестр задач и фабрики
app/bin/cron.php          CLI: --tick, --list, --job <name> [--now=...]
```

Как это работает:

- **Тик раз в минуту** запускает `php bin/cron.php --tick` (сервис `scheduler`
  в `compose.yaml` или строка в системном crontab). Расписание задач живёт в
  коде (`config/cron.php` + `schedule()` у класса), поэтому набор задач
  ревьюится вместе с кодом и одинаков на всех машинах.
- Каждый запуск: блокировка (`cron_locks`, SQLite — `BEGIN IMMEDIATE`), замер
  времени, запись в `cron_runs`, метрики `app_cron_runs_total{job,status}` и
  `app_cron_last_success_timestamp_seconds{job}`.
- Панель `GET /cron/status` и `GET /api/cron` показывают расписание, ближайший
  запуск, последний успех и журнал.

Проверка:

```bash
docker compose exec php php bin/cron.php --list
docker compose exec php php bin/cron.php --job sessions.gc
docker compose exec php php bin/cron.php --job demo.heartbeat --now="$(date '+%Y-%m-%d %H:%M'):00"
curl -s localhost:8080/api/cron
```

## Абстрактный контроллер и маршрутизация

```
app/src/Http/Controller.php        abstract handle(Request): Response + хуки before/after/teardown,
                                   service() (контейнер), хелперы view/json/redirect/error
app/src/Http/Request.php           запрос как объект (path, method, query, body, cookies, headers)
                                   + normalizePath() — /a//b/ == /a/b
app/src/Http/Response.php          ответ как объект, cookie/Set-Cookie, отправка
app/src/Routing/Route.php          шаблон с {параметрами}, только один сегмент на параметр
app/src/Routing/Router.php         группы (prefix + middleware + общий префикс имён), 404/405,
                                   генерация URL по имени, имя маршрута для метрик
app/src/Routing/MatchResult.php    найденный маршрут + middleware-конвейер
app/src/Routing/Exceptions/        404 / 405 (с Allow) / ошибка конфигурации маршрутов
app/src/Http/Middleware/           VerifyCsrfToken, Authenticate — классы с handle(Request, next, ...deps)
app/config/routes.php              таблица маршрутов
app/src/Container.php              контейнер зависимостей (контроллеры/ middleware не знают про глобалы)
app/src/Bootstrap/Application.php  инициализация: конфиг → БД → контейнер
app/public/index.php               ядро: маршрутизация, обработка исключений, метрики, ответ
```

Маршрут регистрируется на класс контроллера, действие выбирается по HTTP-методу
внутри `handle()` — так у URL один контроллер и общая логика не копируется.
Группа задаёт префикс и middleware сразу для пачки маршрутов:

```php
$router->group(['prefix' => '/api', 'middleware' => [Authenticate::class], 'name' => 'api.'], function (Router $r) {
    $r->add(['GET'], '/sessions', SessionController::class, ['name' => 'sessions.index']);
    $r->add(['DELETE'], '/sessions/{id}', SessionController::class, ['name' => 'sessions.destroy']);
});
```

| Маршрут | Что делает |
|---|---|
| `GET /` | Главная: диагностика, пользователи, состояние сессии |
| `GET/POST /register` | Регистрация (POST с CSRF) |
| `GET/POST /login` | Вход: проверка пароля, новая сессия в БД |
| `POST /logout` | Выход: сессия удаляется из БД, cookie стирается |
| `GET/POST /dashboard` | Кабинет: мои сессии, «закрыть остальные» |
| `GET /api/status` | JSON-статус: PHP, БД, таблица сессий (200/503) |
| `GET /api/me` | JSON: кто я + CSRF-токен (401 без сессии) |
| `GET /api/sessions` | JSON: мои активные сессии |
| `DELETE /api/sessions/{id}` | отозвать сессию по префиксу id |
| `POST /api/sessions/destroy-all` | закрыть все, кроме текущей |
| `GET /api/cron`, `GET /cron/status` | расписание и журнал cron |
| `GET /health.php` | health для compose/blackbox |

Проверить маршрутизацию без nginx и без БД: `php tests/smoke.php`
(маршруты, 405/404, cron-выражения, Response).

## Запуск

```bash
cp .env.example .env          # заполнить APP_KEY: php config/secret.php key (внутри контейнера)
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console migrate      # sessions, session_data, cron_runs, cron_locks
```

Приложение — http://127.0.0.1:8080/, метрики (только вместе с
`compose.monitoring.yaml`) — http://127.0.0.1:3000/ и http://127.0.0.1:9090/.

Кроме этого в `compose.yaml` есть сервис `scheduler` — тот же образ php, который
раз в минуту запускает `php bin/cron.php --tick`. Метрики приложения (`/metrics.php`,
`APP_METRICS_ENABLED`) включаются только вместе с `compose.monitoring.yaml`
(задание 2); без него приложение работает как раньше, а `/metrics.php` отвечает 503.

## Что проверено без запуска контейнеров

Подъём стенда в рамках задания не выполнялся (по условию). Что можно и что нельзя
проверить человеку, который будет сдавать:

- `docker compose -f compose.yaml -f compose.monitoring.yaml config` — проходит
  (сервисы, тома, `APP_METRICS_ENABLED`, `extra_hosts` сливаются правильно);
- `promtool check rules monitoring/prometheus/rules/alerts.yml` — запускается
  в `make mon-check`;
- `php tests/smoke.php` (или `php bin/console test`) — маршруты, 404/405,
  cron-выражения, Response — проверка без БД и без nginx;
- `php scripts/lint.sh` — `php -l` по всем файлам.

Ограничение по времени: если `php -l` в вашем окружении покажет ошибку — это
единственное, чего здесь не было возможности проверить автоматически (PHP на
машине, где готовился код, не установлен).

## Структура

```
app/                 приложение (PSR-4 App\ → src/)
  bin/               cron.php (планировщик), console.php (migrate, cron:*, routes, test)
  config/            config.php (+сессии), routes.php (маршруты), cron.php (задачи), secret.php
  db/migrations/     phinx-миграции (пользователи, борды… + sessions/session_data + cron_*)
  instrumentation/   метрики для Prometheus (no-op, если APP_METRICS_ENABLED != 1)
  public/            index.php (ядро), health.php, metrics.php (внутренний порт 8081)
  src/               Bootstrap, Container, Cron, Database, Http, Models, Routing, Session
  tests/smoke.php    самопроверка без зависимостей
docker/              php/ (Dockerfile + apcu), nginx/ (в т. ч. status.conf :8081), scheduler.sh
monitoring/          задание 2: Prometheus, rules, Alertmanager, blackbox, Grafana, runbooks
compose.yaml         nginx, php, scheduler
compose.monitoring.yaml  задание 2: Prometheus, Grafana, Alertmanager, Mailpit, экспортеры, cAdvisor
README-monitoring.md задание 2: что нашёл, что добавил, как проверить
```

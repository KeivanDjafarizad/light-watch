# Light Watch — Sanverano Public Lighting (Part 2: Real-Time Dashboard)

Laravel 13 + Inertia/Vue 3 + Reverb application implementing the real-time
operations dashboard for the Sanverano public lighting telecontrol plant
(Lot A — Lumina P2P, 640 points via MQTT/JSON with acks; Lot C — CP-3000,
11 cabinets via MQTT/CSV, fire-and-forget).

Architecture decisions, trade-offs and the ops runbook live in
[NOTES.md](NOTES.md).

## What runs where

| Process                           | Purpose                                                                           |
| --------------------------------- | --------------------------------------------------------------------------------- |
| `php artisan serve` (container)   | app + REST API (`/api/*`) + Inertia pages                                         |
| `php artisan reverb:start`        | WebSocket server (port 8080) for live dashboard events                            |
| `php artisan dashboard:broadcast` | supervised 5s loop: fleet/cabinet snapshots, online/offline flips, command expiry |
| `php artisan mqtt:listen`         | telemetry ingestion (+ Lot A ack consumption)                                     |
| `php artisan queue:work`          | normalization + command publish/ack jobs                                          |
| `npm run dev` / `npm run build`   | Vite frontend                                                                     |

In development all of them come up with a single command (see below); in
production run the long-running ones under Supervisor, restart-on-crash.

## Requirements

- Docker (+ Docker Compose v2)
- The field simulator from the starter kit (the `starter-kit/` folder this
  repo lives in: `docker-compose.yml`, `simulator.py`, `mosquitto/`,
  `assets/plant.csv`) — it provides the MQTT broker on `localhost:1883`

## First setup

### 1. Install PHP dependencies (no local PHP needed)

`vendor/` is not committed, and Sail itself lives there — so bootstrap it
with a throwaway Composer container:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/opt" -w /opt composer:2 install
```

### 2. Environment

```bash
cp .env.example .env
```

Then point the database at the Sail containers (the `.env.example`
defaults assume a local MySQL):

```dotenv
DB_HOST=mysql
DB_USERNAME=sail
DB_PASSWORD=password
```

`MQTT_HOST=host.docker.internal` and `MQTT_PORT=1883` are already set so
the app container reaches the broker published by the simulator on your
host.

### 3. Start Sail and initialize

```bash
vendor/bin/sail up -d          # builds the app image, starts app + MySQL

vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate          # wait for MySQL to be healthy first

vendor/bin/sail npm install              # frontend deps
vendor/bin/sail npm run build            # production assets
```

> On Linux, export `WWWUSER=$(id -u) WWWGROUP=$(id -g)` before `sail`
> commands if the container complains about file ownership. On Docker
> Desktop (macOS/Windows) nothing is needed.

### 4. Start the field simulator

From the parent `starter-kit/` folder:

```bash
docker compose up -d
docker compose logs -f field   # wait for "simulator connected to mqtt://broker:1883"
```

The simulator generates telemetry around the clock; the plant is ON only
from dusk to dawn, so during the day the dashboard legitimately shows
0 W (the chart displays an explicit "Plant is off" overlay).

### 5. Optional: import plant metadata

```bash
vendor/bin/sail artisan plant:import assets/plant.csv
```

Idempotent upsert of lat/lng and cabinet metadata onto devices. Without
it the dashboard still works: Lot C cabinets always group (the reporting
device _is_ the cabinet), Lot A points stay ungrouped and the map shows
its empty state.

### 6. Log in

Register a user at `http://localhost/register` (registration is enabled)
and open **`http://localhost/dashboard`**.

## Running the realtime stack

Everything (web server, queue, Vite, Reverb, broadcast loop, MQTT
listener) in one supervised TUI:

```bash
vendor/bin/sail composer run dev
```

or start the long-running pieces detached:

```bash
vendor/bin/sail artisan reverb:start &
vendor/bin/sail artisan dashboard:broadcast &
vendor/bin/sail artisan mqtt:listen &
vendor/bin/sail artisan queue:work --tries=3 --timeout=60 &
```

## Ports

| Port               | Service                                                   |
| ------------------ | --------------------------------------------------------- |
| 80                 | app (`APP_PORT` env to change)                            |
| 3306               | MySQL (`FORWARD_DB_PORT`)                                 |
| 5173               | Vite dev server                                           |
| 8080               | Reverb WebSockets (mapped in `compose.yaml`)              |
| 1883 / 9001 / 8081 | simulator broker / WS / vendor B API (parent starter kit) |

## Tests and quality gates

```bash
vendor/bin/sail artisan test                    # PHPUnit (uses the `testing` DB, auto-created)
vendor/bin/sail bin pint --test                 # code style
vendor/bin/sail bin phpstan analyse             # static analysis
vendor/bin/sail npm run check                   # frontend lint + formatting
vendor/bin/sail npm run types:check             # vue-tsc
vendor/bin/sail composer ci:check               # all of the CI gates
```

## Troubleshooting

- **Everything offline right after starting** — online detection flips a
  device online when it hears from it (threshold = 3× the vendor's
  reporting interval: 3 min Lot A, 15 min Lot C). Give it a few minutes.
- **Chart shows "Plant is off — 0 W"** — that's daytime, the plant is
  legitimately off; the line rises at dusk.
- **Map empty** — run `plant:import` (see above).
- **Port already in use** — set `APP_PORT` / `FORWARD_DB_PORT` /
  `REVERB_PORT` in `.env` and `sail up -d` again.
- **No telemetry at all** — check the simulator is running
  (`docker compose ps` in the starter kit) and `MQTT_HOST` resolution
  from inside the app container.


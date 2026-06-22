# Pickup Points Importer

A small Symfony application that imports carrier pickup points (parcel shops and
parcel lockers) into a local database. The import is built as a generic pipeline;
**GLS is the implemented carrier**.

Each run fetches the current set of pickup points for a chosen carrier and country,
upserts them into the database, and marks points that are no longer offered as
terminated — so the table always reflects the carrier's latest state.

## Requirements

- PHP **8.4+** with `ext-ctype`, `ext-iconv`, `ext-pdo_mysql`
- [Composer](https://getcomposer.org/)
- MySQL / MariaDB
- Docker + Docker Compose *(optional — provides a ready-to-use MariaDB instance)*

## Getting started

```bash
# 1. Install dependencies
composer install

# 2. Start the bundled database (MariaDB 10.11 + Adminer)
docker compose up -d
```

The database container automatically creates the `import_db` schema, and the
default `DATABASE_URL` in `.env` already points at it
(`app_user:app_password@127.0.0.1:3306/import_db`), so no further configuration is
needed for local development. If you use your own database, override `DATABASE_URL`
in `.env.local`.

```bash
# 3. Create the schema
php bin/console doctrine:migrations:migrate
```

Adminer is available at <http://localhost:8002> for inspecting the data.

## Usage

```bash
php bin/console app:pickup-points:fetch
```

The command is interactive: it asks which carrier to import (only carriers with a
working implementation are offered, plus *All*) and for which country. It can be
run repeatedly — subsequent runs reconcile the database with the freshly fetched
data.

## How it's built

The core idea is a single generic synchronization flow that delegates the
carrier-specific part (where and how to fetch points) behind a narrow interface.

```
PickupPointsFetchCommand
        │  (carrier, country)
        ▼
SynchronizePickupPoints ──► FetcherLocator ──► PickupPointFetcher (per carrier)
        │                                              │
        │                                              ▼
        │                                       PickupPointData (DTO)
        ▼
PickupPointRepository  ──►  pickup_points table
```

- **`PickupPointFetcher`** — the carrier abstraction. An implementation knows how
  to retrieve points for a carrier and yields them as `PickupPointData` DTOs.
  Implementations are auto-registered via a service tag, so the rest of the system
  never references a concrete carrier.
- **`FetcherLocator`** — resolves the right fetcher for a given `Carrier`.
- **`GlsPickupPointFetcher`** — the GLS implementation. Calls the GLS dropoff-points
  endpoint per country, decodes the XML response, and streams the points as a
  generator to keep memory flat for large result sets.
- **`SynchronizePickupPoints`** — the generic orchestration: snapshots the points
  already stored for the carrier/country, upserts the fetched points, and
  terminates the ones that disappeared from the feed.
- **`PickupPointRepository`** — persistence. New and changed points are written with
  a single batched `INSERT ... ON DUPLICATE KEY UPDATE`, matched on the
  `(carrier, externalId, country)` unique key.
- **Domain types** — a `Country` value object (validated against ISO country codes)
  and the `Carrier`, `PickupPointType` (`box` / `point`) and `PickupPointStatus`
  enums.

### Synchronization semantics

For a given carrier and country, a run:

1. **inserts** points that are new,
2. **updates** points that already exist (including reviving previously terminated
   ones), and
3. **terminates** points that are in the database but no longer in the feed.

The unique key `(carrier, externalId, country)` guarantees a point is identified
consistently across runs and across countries.

## Adding another carrier

The system is designed so that a new carrier requires no changes to the import flow:

1. Implement `PickupPointFetcher` — `fetch()` returns `PickupPointData` items and
   `carrier()` returns the matching `Carrier`. The `app.pickup_point_fetcher` tag is
   applied automatically.
2. On the `Carrier` enum, mark the carrier as `supported()` and list the countries
   it serves in `countries()` (add a new case if needed).

That's it — the command, locator and synchronization logic pick it up as-is.

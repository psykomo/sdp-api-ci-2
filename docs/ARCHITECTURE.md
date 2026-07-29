# IMS API Architecture

Inmate Management System (SDP) API — CodeIgniter 4, **hybrid + multi-org**.

## House style (pragmatic modular API)

This is **not** strict DDD or Clean Architecture. It is a modular layered API that
stays simple to copy, extend, and maintain.

### Principles

1. **Modular by feature** — domain code lives under `app/Modules/{Name}/`. Shared core
   (`app/Services`, filters, auth, users, RBAC, audit) stays outside modules.
2. **Thin controllers** — parse input, call one service/action, map result to JSON.
   Use `ApiResponse` envelopes. Prefer the `MapsApiExceptions` trait for consistent
   401/404/422 mapping.
3. **Services hold rules** — org scoping, business guards, multi-write orchestration,
   audit. Never put org filters or business rules in controllers.
4. **Models are persistence** — table mapping, `$allowedFields`, field validation only.
5. **Always scope with `OrgContext`** — never trust client-supplied org ids for
   authorization; use active/scoped org ids from filters.
6. **Multi-write → `UnitOfWork`** — nest-safe; module-local and cross-module.
7. **Cross-module via public service only** — no reaching into another module’s Models
   or Actions. No dependency cycles.
8. **Grow structure only when needed** — a small module is one service; add
   `Actions/`, `*QueryService`, and `Shared/` when the facade would otherwise bloat.
9. **Permissions on routes** — `permission:resource.action` filters, not ad-hoc checks
   inside controller methods.
10. **Same shape every feature** — copy Inmate (or the thin-module template), do not invent layers.

### Exception contract

| Exception | HTTP | When |
|-----------|------|------|
| `PageNotFoundException` | 404 | Missing or out-of-scope resource |
| `App\Exceptions\ValidationException` | 422 | Field validation (`errors` payload) |
| `DomainException` | 422 | Business rule violation |
| `App\Exceptions\AuthenticationException` | 401 | Login / credentials failure |
| Anything else | 500 | Unexpected; let CI handle |

Services and actions **throw**; controllers **map**. Do not return `false` for domain failures.

### Feature tests

HTTP feature tests live under `tests/feature/` and extend
`Tests\Support\Feature\ApiFeatureTestCase` (in-memory SQLite, App + module
migrations, `ApiFeatureSeeder`). Run:

```bash
composer test -- --filter Feature
# or
./vendor/bin/phpunit --filter Feature
```

### Implementation references

| Kind | Module | When to copy |
|------|--------|--------------|
| **Thin module** (default start) | [`app/Modules/Visit/`](../app/Modules/Visit/) | New feature with a few endpoints and one service |
| **Grown module** | [`app/Modules/Inmate/`](../app/Modules/Inmate/) | Many processes; need Actions / QueryService / Shared |
| **Cross-module orchestrator** | [`app/Modules/Transfer/`](../app/Modules/Transfer/) | One use case that coordinates other modules via facades |

**Always start thin (Visit).** Grow toward Inmate only when the single service becomes a god class.
Empty module folders (Facility, Medical, …) are placeholders — do not copy them.

### Thin module skeleton (Visit)

```
Config/Routes.php
Controllers/Api/{Things}.php   # MapsApiExceptions + Config\Services::…
Services/{Thing}Service.php    # org scope, rules, throw on failure
Models/ + Entities/
Database/Migrations/
```

No `Actions/`, no `*QueryService`, no `Shared/` until you need them.

Checklist when adding a thin module:

1. Folder under `app/Modules/{Name}/` with the files above.
2. PSR-4 entry in [`app/Config/Autoload.php`](../app/Config/Autoload.php) (Visit already registered).
3. Factory method in [`app/Config/Services.php`](../app/Config/Services.php) — default `$getShared = false`.
4. `Config/Routes.php` with `apiAuth`, `orgScope`, and `permission:{resource}.{action}`.
5. Domain table includes `organization_id`; creates bind to active org; reads use `getScopedOrgIds()`.
6. Seed permissions (`{resource}.read`, `{resource}.write`, …) in RBAC seeder.
7. Feature tests extending `ApiFeatureTestCase` (add module namespace to `$namespace`).

### Grown module extras (Inmate only when needed)

```
Services/{Name}QueryService.php
Actions/{Process}.php
Shared/{Helper}.php
```

## Layout

| Area | Location | Responsibility |
|------|----------|----------------|
| Shared core | `app/Controllers`, `Services`, `Models`, `Entities`, `Filters`, `Libraries` | Auth, users, org context, RBAC, cross-cutting helpers |
| Feature domains | `app/Modules/{Name}/` | Inmate, Visit, Remission, Transfer, Medical, Legal, Facility, MasterData, Report |

Each module owns:

```
Controllers/Api/   # thin ResourceControllers
Services/          # business rules + org scoping
Models/ + Entities/
Database/Migrations/
Config/Routes.php  # auto-discovered (module namespace in Config\Autoload)
```

## Request pipeline

```
Client
  → ForceJson
  → ApiAuth          # Bearer token → user + allowed org IDs
  → OrgScope         # X-Org-Id → active org + scoped org IDs + permissions
  → permission:*     # optional route filter
  → Controller       # parse input / shape response
  → Service          # rules; always filter by OrgContext
  → Model / Entity   # persistence only
```

### Required headers (protected routes)

- `Authorization: Bearer <token>`
- `X-Org-Id: <organization_id>` — must be one of the user’s assigned orgs (**single** topology)
- `X-Org-Code: <organization_code>` — routes the tenant database (**multi** topology; optional `X-Org-Id` must match the local row)
- `Accept: application/json`

### Org scoping rules

- Domain rows (e.g. `inmates`) always store **`organization_id`** (owning Lapas/Rutan).
- Active **Lapas/Rutan**: queries use that id only; creates bind to it.
- Active **Kanwil**: reads expand to descendant units via `OrgContext::getScopedOrgIds()`; writes still require a unit context (prefer selecting a Lapas/Rutan as `X-Org-Id` for mutations).

## Layers

1. **Controller** — HTTP only. Use `ApiResponse` + `MapsApiExceptions`.
2. **Service / Action** — rules, org scoping, UnitOfWork, audit. Throw on failure.
3. **Model** — table mapping, `$allowedFields`, field validation rules.
4. **Entity** — casts, mutators, hidden fields (`password_hash`).

Do **not** put business rules or org filters in controllers.
Permissions belong on routes (`permission:…`), not inside controller methods.

## Adding a new module

Prefer copying the **Visit** thin module, not Inmate.

1. Create `app/Modules/{Name}/` with the thin skeleton (see Visit).
2. Register PSR-4 in [`app/Config/Autoload.php`](../app/Config/Autoload.php) if missing:
   `'App\Modules\{Name}' => APPPATH . 'Modules/{Name}'`
3. Add `Services::{name}Service()` in [`app/Config/Services.php`](../app/Config/Services.php) with `$getShared = false`.
4. Add `Config/Routes.php` with `apiAuth`, `orgScope`, and `permission:{resource}.{action}`.
5. Migrations in the module; run:
   `php spark migrate -n App\\Modules\\{Name}`
6. Scope every query/write with `OrgContext`; bind creates to the active org.
7. Seed permissions and add feature tests.

## Module internals (scaling to many processes)

A small module can live in one `*Service`. As it grows to dozens/hundreds of
business processes, split it so the facade never becomes a god class. The Inmate
module is the reference:

```
Controllers/Api/     # one controller per process family (thin HTTP only)
Services/
  {Name}Service.php       # FACADE / composition root — the module's public surface
  {Name}QueryService.php  # all READS (list/search/find); no UnitOfWork
Actions/             # one class == one WRITE business process, single execute()
Shared/              # helpers reused by every process (finder, audit writer, ...)
Models/ + Entities/
Database/Migrations/
Config/Routes.php
```

Guidelines:

- **Start with one service.** Extract an Action when a write process is multi-step,
  reused, or would bloat the facade.
- **Reads can stay on the service** until list/search logic gets large; then
  split `*QueryService`.
- **Shared helpers** only after the second copy-paste (e.g. `InmateFinder`).
- **Facade = public surface** for other modules and simple CRUD. Process-only
  HTTP endpoints may call an Action directly (see `InmateReleases`).

## Module dependency rules

Actions and services **may** call another module — that is how cross-module
use cases work (e.g. `Transfer` → `Inmate`). Keep it disciplined:

1. **Depend on the facade, never the internals.** Inject the other module's
   `*Service` (its public surface). Never reach into another module's
   `Models/`, `Entities/`, or `Actions/` directly.
2. **Dependencies flow one way — no cycles.** `Transfer → Inmate` is allowed;
   `Inmate → Transfer` on top of it creates `Inmate ⇄ Transfer` (constructor
   recursion, untestable). Rough layering:
   `MasterData → Inmate → {Visit, Medical, Legal, Remission} → Transfer/Report`.
   A module never calls back into a module that already depends on it.
3. **Atomicity is automatic.** A cross-module call inside a `UnitOfWork` joins
   the outer transaction (see below); no extra work to stay atomic.
4. **Coordinating 3+ modules ⇒ it's an orchestrator, not an Action.** One
   outbound call (e.g. `ReleaseInmate` asking Legal to verify no pending case)
   is fine. When a process must coordinate several modules, promote it to its
   own module that calls down into all of them — the way `Transfer` does —
   so no domain module silently becomes the hub everyone depends on.

## Auth tokens

`AuthService` issues opaque Bearer tokens stored as SHA-256 hashes in `api_tokens`. Swap the internals for JWT/Shield/SSO later without changing filters.

Login: `POST /api/v1/auth/login` with `{ "email", "password" }` (public).

In **multi** topology also send `organization_code` (e.g. `LP-CIPINANG`) so the
token is issued against that unit’s isolated database. Protected requests then
send `X-Org-Code` (and optionally `X-Org-Id` for the local id inside that DB).

## Core schema

- `organizations` — `kanwil` | `lapas` | `rutan`, optional `parent_id`
- `roles` / `permissions` / `role_permissions`
- `user_organization_roles` — user ↔ org ↔ role
- `api_tokens`, `audit_logs`
- Domain tables: include `organization_id`, soft deletes, timestamps

## Response envelope

```json
{
  "status": "success",
  "code": 200,
  "message": "OK",
  "data": {},
  "meta": { "page": 1, "perPage": 10, "total": 0, "pageCount": 0 }
}
```

## Database (SQLite local + MariaDB production)

| Environment | Connection group | Driver |
|-------------|------------------|--------|
| `development` (default) | `default` | CI4 `SQLite3` → `writable/db/sdp_api.sqlite` |
| `production` | `mariadb` | CI4 `MySQLi` (MariaDB) |
| `testing` | `tests` | in-memory SQLite3 |

Production `.env`:

```env
CI_ENVIRONMENT = production
database.mariadb.hostname = 127.0.0.1
database.mariadb.database = sdp_api
database.mariadb.username = sdp_user
database.mariadb.password = secret
database.mariadb.port = 3306
```

Models, migrations, and `UnitOfWork` always use `db_connect()` / the active default group — no driver-specific code in services.

Optional fallbacks:

- `db_connect('sqlite')` — local file even in production tooling
- `db_connect('mariadb')` — MariaDB explicitly in any environment

## Database topology (single vs per-unit DB)

One codebase supports two deployments. Switch with `database.topology`.

| Mode | Config | Behavior |
|------|--------|----------|
| `single` (default) | one shared DB | All orgs in one database. Isolation via `organization_id` + `OrgContext` (current design). |
| `multi` | one DB/schema per org code | Identical schema in every database (`lp_cipinang`, `rt_salemba`, `kw_dki`, …). Each request binds to **exactly one** DB. |

Topology **C** (fully isolated): every unit DB includes auth + domain tables.
Kanwil/Pusat also has its own DB with the **same** structure. An **external**
process aggregates unit data into the Kanwil/Pusat database — this API never
fans out across unit databases.

```text
Client
  → X-Org-Code: LP-CIPINANG   (multi only; routes the DB)
  → ConnectionResolver.activateForOrgCode()
  → ApiAuth (token in that DB's api_tokens)
  → OrgScope (resolve local organizations.id by code)
  → Controller / Service / Model   (all on that one connection)
```

### Config

```env
database.topology = single   # or multi

# multi only — org code → MariaDB database name
# (SQLite local: value becomes writable/db/{value}.sqlite)
database.tenants.LP-CIPINANG = lp_cipinang
database.tenants.RT-SALEMBA  = rt_salemba
database.tenants.KW-DKI      = kw_dki
```

Routing key is organization **`code`**, not numeric `id` — each isolated DB can
use its own auto-increment ids (Cipinang and Salemba may both be local `id = 1`).

### Headers / login

| Topology | Login body | Protected headers |
|----------|------------|-------------------|
| `single` | `email`, `password` | `Authorization`, `X-Org-Id` |
| `multi`  | `email`, `password`, `organization_code` | `Authorization`, `X-Org-Code` (optional `X-Org-Id` must match) |

`ConnectionResolver` is registered as `service('connectionResolver')`.
`UnitOfWork` resolves `db_connect()` lazily so it always uses the active tenant.

### Migrations

```bash
# single
php spark migrate --all

# multi — every mapped tenant DB
php spark migrate:tenants
php spark migrate:tenants --seed   # also RbacSeeder + DemoAuthSeeder per tenant
```

### Transfer limitation in `multi`

Same-DB transfers (e.g. two orgs mirrored inside a Kanwil DB) still use
`UnitOfWork`. Cross-unit moves between two tenant databases (Cipinang DB →
Salemba DB) are **out of scope for this API** under topology C — treat them as
an external/ops process. Do not assume a single ACID transaction across MariaDB
databases.

## Atomic cross-module operations

Use `service('unitOfWork')->run(...)` for any multi-write business process —
both module-local flows and cross-module orchestrators.

`UnitOfWork` is nest-safe:

| Call site | Behavior |
|-----------|----------|
| Module process called alone | Starts a real transaction and commits/rolls it back |
| Module process called inside an outer `UnitOfWork` | Joins the outer transaction; does **not** commit early |
| Outer process fails after nested success | Rolls back **all** writes, including nested module work |

```text
TransferService UnitOfWork          ← owns SQL BEGIN/COMMIT
  └─ InmateService::transferOwnership()
       └─ UnitOfWork                ← joins (no SQL COMMIT)
            └─ update inmates
  └─ insert inmate_transfers
  └─ audit_logs
```

Modules should wrap their own multi-write processes with `UnitOfWork`
(not raw `BEGIN`/`COMMIT`). That keeps local atomicity and still lets the
process participate in a larger use case.

```php
// Module-local process (InmateService)
return $this->unitOfWork->run(function () use ($data) {
    $id = $this->inmates->insert($data);
    $this->audit->record('inmate.created', /* ... */);
    return $this->inmates->find($id);
});

// Cross-module orchestrator (TransferService)
return $this->unitOfWork->run(function () {
    // Nested UnitOfWork inside transferOwnership() joins this outer boundary.
    $inmate = $this->inmates->transferOwnership($inmateId, $toOrgId);
    $transferId = $this->transfers->insert($transferData);
    $this->audit->record('inmate.transferred', /* ... */);

    return ['inmate' => $inmate, 'transfer_id' => $transferId];
});
```

Endpoint:

```http
POST /api/v1/inmates/{id}/transfers
Authorization: Bearer {token}
X-Org-Id: {source_org_id}
Content-Type: application/json

{
  "to_organization_id": 3,
  "reason": "Penyesuaian kapasitas hunian",
  "notes": "Approved by Kanwil"
}
```

Rules:

1. All participating Models must use the same default database connection.
2. Prefer `UnitOfWork` over raw SQL `BEGIN`/`COMMIT`/`ROLLBACK`.
3. Do not open a second connection group mid-use-case (keep one DB connection).

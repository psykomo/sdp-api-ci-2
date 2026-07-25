# IMS API Architecture

Inmate Management System (SDP) API — CodeIgniter 4, **hybrid + multi-org**.

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
- `X-Org-Id: <organization_id>` — must be one of the user’s assigned orgs
- `Accept: application/json`

### Org scoping rules

- Domain rows (e.g. `inmates`) always store **`organization_id`** (owning Lapas/Rutan).
- Active **Lapas/Rutan**: queries use that id only; creates bind to it.
- Active **Kanwil**: reads expand to descendant units via `OrgContext::getScopedOrgIds()`; writes still require a unit context (prefer selecting a Lapas/Rutan as `X-Org-Id` for mutations).

## Layers

1. **Controller** — HTTP only. Use `App\Libraries\ApiResponse` for envelopes.
2. **Service** — validation orchestration, authorization side-effects, audit logging.
3. **Model** — table mapping, `$allowedFields`, validation rules.
4. **Entity** — casts, mutators, hidden fields (`password_hash`).

Do **not** put business rules or org filters in controllers.

## Adding a new module

1. Create `app/Modules/{Name}/` with the folders above.
2. Register PSR-4 in [`app/Config/Autoload.php`](../app/Config/Autoload.php):
   `'App\Modules\{Name}' => APPPATH . 'Modules/{Name}'`
3. Add `Config/Routes.php` under the module; wrap routes with `['filter' => ['apiAuth', 'orgScope']]` and `permission:{resource}.{action}` as needed.
4. Put migrations in the module; run:
   `php spark migrate -n App\\Modules\\{Name}`
5. Mirror the Inmate pattern: `*Service` injects `service('orgContext')` and scopes every query.

## Auth tokens

`AuthService` issues opaque Bearer tokens stored as SHA-256 hashes in `api_tokens`. Swap the internals for JWT/Shield/SSO later without changing filters.

Login: `POST /api/v1/auth/login` with `{ "email", "password" }` (public).

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

# SDP Legacy → CI4 API Migration Strategy

| Field | Value |
|-------|--------|
| **Document** | SDP Legacy → CI4 Modular API Migration Strategy |
| **Author** | _TBD_ |
| **Date** | 2026-07-29 |
| **Status** | Draft (rev 3 — shared DB + legacy→API strangler) |
| **Audience** | Senior engineers, tech leads, product owners (Ditjenpas / SDP program) |
| **Source (legacy)** | `/Users/hap/Downloads/HTDOCS SDP` — CodeIgniter 2.1.3, app **3.6.2**, sample UPT Lapas Kelas I Cipinang |
| **Target (new)** | `/Users/hap/Documents/dev/sdp/sdp-api-ci-2` — CodeIgniter 4 modular IMS API |
| **House style** | [`docs/ARCHITECTURE.md`](./ARCHITECTURE.md) (modules, thin controllers, services) — **data layer adapted for shared legacy schema** |
| **Module names** | Indonesian — [`docs/MODULE_NAMING.md`](./MODULE_NAMING.md) (`Wbp`, `Kunjungan`, `Mutasi`, …) |
| **Supersedes** | rev 2 greenfield-schema + ETL + shadow/cutover-as-primary plan |

---

## Overview

Sistem Database Pemasyarakatan (SDP) is a large CodeIgniter 2 prison IMS (~612 controllers, ~361 models, ~2062 views, multi-10k-line remisi/integrasi libraries). The target is a modular CodeIgniter 4 API (`sdp-api-ci-2`) with thin controllers, services, RBAC, and org-aware filters.

**Hard product constraints (rev 3):**

1. **No domain database redesign.** The new API uses the **same database engine and the same table/column structure** as legacy (MySQL/MariaDB; tables such as `identitas`, `perkara`, `kunjungan`, `hukuman`, `remisi`, …).
2. **Transition runs two codebases** against that **one shared database**: legacy CI2 UI/app + new CI4 API.
3. **Strangler direction: legacy calls the new API.** When a capability is migrated, legacy controllers/libraries stop executing local business logic for that path and **HTTP-call the CI4 API** instead. The API owns writes (and usually reads) for that capability; both apps still see the same rows.

This is **not** a CI2→CI4 framework upgrade, not a greenfield schema rewrite, and not “ETL into new tables then cut over.” Value is delivered by **moving process logic** into the API while the **schema stays the system of record**.

**Implementability boundary:** Wave 0–1 (shared-DB models + auth bridge + first legacy→API proxy path) is executable from this document. Remisi/integrasi still need characterization fixtures and policy packages; Legal depth before remisi apply remains required.

---

## Background & Motivation

### Current state (legacy)

| Dimension | Reality |
|-----------|---------|
| Stack | CI 2.1.3, mysqli, session UI + jqGrid, REST digest under `controllers/api/` |
| Size | ~242 MB install; fat libs `Lib_remisi*` / `Lib_integrasi2*` (13–17k LOC each) |
| AuthZ | `MY_Controller` menu ACL R/W/N; UPT/Kanwil/Pusat modes |
| Data | Shared UPT DB (`sdp_upt` / `db_sdp`); soft deletes `IS_DELETED` / `IS_DELETE`; keys `NOMOR_INDUK`, `ID_PERKARA`, … |
| Outbound HTTP | Existing `Curl` library (Philip Sturgeon-style) — usable for legacy→API calls |

### Current state (target)

| Area | Status |
|------|--------|
| Core | Bearer auth, RBAC, `OrgContext`, `UnitOfWork`, hybrid topology concepts |
| Modules | Target names **Wbp / Kunjungan / Mutasi** (today still English folders `Inmate` / `Visit` / `Transfer` on greenfield tables) |
| Gap vs rev 3 | Greenfield migrations **conflict** with “same structure” — must be **rebased** onto legacy tables **and renamed** to Indonesian modules before production use |

### Pain points

1. Unmaintainable regulation forks in fat libraries.
2. No clean API for SPA/mobile; logic trapped in controllers + views.
3. Security/ops: session + digest, secrets in tree, weak modern audit patterns.
4. Dual-code risk if both stacks write the same process without a single owner.

### Why shared schema + legacy→API

| Driver | Effect |
|--------|--------|
| Legal continuity | Same rows, same PKs, no remapping risk for `NOMOR_INDUK` / hukuman / remisi history |
| Ops simplicity | One DB backup/restore story; no dual-SoR ETL lag |
| Incremental delivery | Migrate one process; point legacy at API; rest of UI keeps working |
| Existing skill | Legacy already has REST/Curl patterns for external calls |

---

## Goals & Non-Goals

### Goals

1. Move domain **capabilities** into CI4 modules per house style (thin controller → service → model).
2. **Keep legacy table/column structure** as the persistence contract for domain data.
3. Run **legacy + API together** on one DB during transition.
4. For each migrated capability, **legacy becomes a thin adapter** that calls the new API (session UX preserved).
5. Encode remisi/integrasi as **versioned policies** in the API (libs = specs, not paste).
6. Feature tests as the contract for each migrated process.
7. Preserve dependency discipline: facades only, `UnitOfWork` for multi-write **inside the API**.

### Non-Goals

1. **Not** redesigning or renaming domain tables/columns for “clean” CI4 schema.
2. **Not** bulk ETL into parallel `inmates` / `visits` tables as the production path.
3. **Not** porting views/jqGrid/CI2 controllers wholesale into CI4.
4. **Not** sharing a single PHP transaction across legacy HTTP → API (see Consistency).
5. **Not** early biometric, full PDF/surat, Bapas/Rupbasan, full DWH rewrite.
6. **Not** SPA work in this repo (API contracts only).
7. **Not** dual independent writers for the same capability (legacy DB write **and** API write for the same action).

---

## Key Constraints (normative)

| ID | Constraint |
|----|------------|
| C1 | **Same engine:** MySQL/MariaDB compatible with legacy (`mysqli` / CI4 MySQLi). |
| C2 | **Same structure:** Domain models map to **existing** tables and columns. No greenfield domain schema as SoR. |
| C3 | **Additive platform tables only with explicit approval** (e.g. `api_tokens` if `pengguna`/session cannot host API auth). Prefer reusing `pengguna` / existing ACL when feasible. |
| C4 | **Two codebases, one DB** during transition. |
| C5 | **Legacy → API** for migrated capabilities (HTTP). New SPA/clients may call API directly later. |
| C6 | **Single writer per capability:** once routed, **only the API** executes that process’s business writes. |
| C7 | **No fat-lib paste** into CI4. |

---

## Proposed Design

### Target architecture (transition)

```mermaid
flowchart TB
  User[User browser]
  Legacy[Legacy CI2 UI + controllers]
  API[CI4 sdp-api-ci-2]
  DB[(Shared MySQL/MariaDB\nlegacy schema)]

  User --> Legacy
  User -.->|future SPA / mobile| API
  Legacy -->|migrated capability\nHTTP + service token| API
  Legacy -->|not yet migrated\nlocal models/libs| DB
  API --> DB
```

### Capability states

| State | Legacy | API | DB writes for that process |
|-------|--------|-----|----------------------------|
| **L0 Local** | Full local logic | Absent or shadow-read only | Legacy only |
| **L1 Parallel build** | Full local logic | Implements + tests against **same tables** (dev/staging); **not** called from prod legacy | Legacy only (prod) |
| **L2 Routed** | Thin proxy (Curl) → API; no local business write | Owns process | **API only** |
| **L3 Direct** | Menu retired or UI replaced | Primary client is SPA/API consumers | API only |

Progression for each process family: **L0 → L1 → L2 → (optional) L3**.

### Strangler sequence (example: Visit / kunjungan)

```mermaid
sequenceDiagram
  participant U as User
  participant L as Legacy CI2
  participant A as CI4 Visit API
  participant DB as Shared DB

  Note over L,A: State L2 — routed
  U->>L: Submit kunjungan form
  L->>L: Validate session + menu ACL
  L->>A: POST /api/v1/kunjungan (Bearer service/user token, ID_UPT context)
  A->>A: permission + OrgContext (ID_UPT) + KunjunganService rules
  A->>DB: INSERT/UPDATE kunjungan (+ related rows) in UnitOfWork
  A-->>L: ApiResponse JSON
  L-->>U: Flash / redirect / JSON for jqGrid as today
```

### Control plane (routing, not schema cutover)

| Layer | Role |
|-------|------|
| **Legacy feature switch** | Per-process config (e.g. `sdp_api_routes['kunjungan'] = true` or preference row) — when on, controller uses proxy; when off, local logic |
| **API FeatureFlags** | Optional kill switch: reject writes if rollout must stop (`feature.kunjungan.write`) |
| **Rollback** | Flip legacy switch off → local logic resumes (code path still present until L3). **Do not** delete local path until L3 + soak |

No ETL “shadow database” is required for correctness: both stacks see the same rows. Optional **shadow** means: API implements L1 and ops compare API responses to legacy for the same inputs **without** routing traffic yet.

### Consistency & transactions (critical)

HTTP breaks shared DB transactions:

| Pattern | Guidance |
|---------|----------|
| **Preferred** | Entire business process (all related writes) runs **inside one API request** under `UnitOfWork`. Legacy only orchestrates HTTP + presentation. |
| **Forbidden** | Legacy `trans_begin` → partial local writes → HTTP to API → more local writes (split-brain, non-atomic). |
| **Multi-step UI** | Wizard steps that today share one legacy transaction must become **one API command** or **explicit multi-step API state** stored in DB (status columns that already exist). |
| **Idempotency** | Proxy sends `Idempotency-Key` (or natural key) so double-submit / retry does not double-insert. |

### Auth bridge (legacy → API)

Legacy users are session-authenticated (`pengguna`, NIP, roles). API expects Bearer tokens + org context.

**Recommended design:**

1. **Service principal** for legacy→API server-side calls (machine token stored in env on UPT host, not in git).
2. **On-behalf-of headers** from legacy session, e.g.:
   - `X-Actor-Nip` / `X-Actor-User-Id` — who clicked
   - `X-Org-Code` or `X-Id-Upt` — active UPT (must match session; API must not trust alone without binding to token scope)
3. API filter validates service token, then builds `OrgContext` from UPT and loads actor permissions (map menu R/W/N → `permission:*` or a transitional ACL adapter).
4. Audit logs record **actor** + **via=legacy-proxy**.

**Alternatives (later):** issue short-lived user tokens at legacy login (login once → API token in session); more moving parts.

### Org / multi-tenant mapping

| Legacy | API adaptation (shared schema) |
|--------|--------------------------------|
| `ID_UPT` on rows / session | `OrgContext` active unit = `ID_UPT` (or map to `organizations` **view/table only if already present**; do not invent parallel org IDs as SoR) |
| Kanwil / Pusat | Scope queries using existing legacy hierarchy tables (`upt`, `kanwil`) the same way legacy reports do |
| Topology `multi` in ARCHITECTURE.md | **Per-unit DB installs already match legacy.** ConnectionResolver may still select DB by org code; **schema inside each DB stays legacy.** Single shared national DB is a product choice, not required for pilot |

**Pilot:** one UPT DB (Cipinang-class), both codebases point at it.

### Data layer in CI4 (shared structure)

**Models bind to legacy tables:**

```php
// Illustrative — names follow legacy
class IdentitasModel extends Model
{
    protected $table         = 'identitas';
    protected $primaryKey    = 'NOMOR_INDUK'; // or auto-inc if that is the real PK — match DB
    protected $returnType    = \App\Modules\Wbp\Entities\Identitas::class;
    protected $allowedFields = [/* exact column names */];
    // Soft deletes: custom if column is IS_DELETED not deleted_at
}
```

| Concern | Approach |
|---------|----------|
| Column names | Keep DB columns; map to JSON `snake_case` in Entities / Resource transformers for API clients |
| Soft delete | Support `IS_DELETED` / `IS_DELETE` via model callbacks or raw conditions — do not require `deleted_at` unless column exists |
| Validation | Service-layer + model rules; mirror critical legacy checks |
| Surrogate keys | Use **existing** PKs/FKs; no `legacy_id_map` |
| New API-only tables | Only with approval (C3); never fork domain entities into parallel tables |

### Existing greenfield scaffolds in this repo

English scaffolds `app/Modules/Inmate` (`inmates`), `Visit` (`visits`), `Transfer` (`inmate_transfers`), and core `organizations` / RBAC tables **as currently migrated** are **provisional**.

| Action | When |
|--------|------|
| Freeze new greenfield domain migrations | Immediately |
| Rename modules to Indonesian ([`MODULE_NAMING.md`](./MODULE_NAMING.md)) | Wave 0 (or with first rebase PR) |
| Rebase modules onto legacy tables | Wave 0–1 (blocking for L1+) |
| Keep modular layout/services | Yes — names + persistence mapping change |
| Feature tests | Rewrite against legacy schema fixtures (SQL dump subset or anonymized seed) |

Platform tables (`users`, `api_tokens`, `roles`, …) need a **decision** (Open Questions): reuse `pengguna` + existing hak akses vs additive API tables alongside legacy.

### Business rules extraction (unchanged intent)

Fat libs remain **characterization sources**:

1. Identify process entrypoints (controller methods / `Lib_*` public methods).
2. Capture golden I/O fixtures against **shared DB** (anonymized).
3. Implement policy packages in CI4.
4. Parity tests: same inputs → same DB effects / DTO outputs.
5. At L2, legacy proxy calls API; local lib path disabled by switch.

### Remisi / integrasi

- Still **versioned strategies** (`SKEMAINT*` / `SKEMARMS*` → policy resolver).
- Still **no paste** of 16k-line files.
- Persist to **existing** `remisi` / integrasi tables and columns.
- Multi-stage usulan → TPP → SK: model as state on **existing** status fields; API owns transitions when L2.
- `applied` / mutasi package: entire package write inside API `UnitOfWork` when routed.

---

## Domain Decomposition & Dependency Graph

Legacy concepts → modules (logic ownership; **tables stay legacy names**):

| Legacy | Module (ID) | Tables (examples) | Wave |
|--------|-------------|-------------------|------|
| Identitas, registrasi, portir | **Wbp** | `identitas`, registrasi-related | 1 |
| Perkara, hukuman, kejahatan, grasi data | **Perkara** | `perkara`, `hukuman`, `kejahatan`, … | 2 |
| Kunjungan | **Kunjungan** | `kunjungan`, `kunjungan_*` | 1 |
| Mutasi UPT/sel | **Mutasi** (+ **Fasilitas**) | `mutasi_*`, blok/sel | 2 |
| Remisi / integrasi / PB | **Remisi** (+ **Integrasi** if split) | `remisi`, related | 3–4 |
| Keswat / obat / bama | **Keswat** | `keswat_*`, bama | 4+ |
| Parameter / referensi | **Referensi** | `daftar_referensi`, parameter tables | 0–1 |
| Laporan / DWH | **Laporan** | reads operational + optional DWH | 5 |
| Bapas / Rupbasan / biometric | Deferred (**Bapas**, **Rupbasan**, **Biometrik**) | … | 6+ |

Full naming rules: [`MODULE_NAMING.md`](./MODULE_NAMING.md).

Dependency DAG (logic): `Referensi → Wbp → {Kunjungan, Keswat, Perkara, Remisi} → Mutasi/Laporan`.

---

## Phased Roadmap

### Wave 0 — Shared-DB foundation & proxy kit (3–5 weeks)

**Scope:**

- Capability inventory (controller/lib → process → module → state L0–L3).
- **Schema contract pack:** document primary tables/PKs/soft-delete columns for pilot processes (no redesign).
- Rebase decision: stop greenfield domain migrations; plan model rewrite.
- Auth bridge: service token + on-behalf-of + OrgContext from `ID_UPT`.
- **Legacy API client kit:** thin CI2 library (wrap existing `Curl`) + config base URL, timeouts, idempotency, error mapping to flash/JSON.
- Feature switch convention in legacy config/preferences.
- Staging: both apps → same DB clone.
- Secrets hygiene: env-only credentials; rotate sample install passwords.

**Exit criteria:**

- [ ] Inventory ≥90% controllers clustered.
- [ ] One health path: legacy can call `GET /api/v1/ping` (or equivalent) with service auth.
- [ ] Documented mapping: session user + `ID_UPT` → API context.
- [ ] No production routing yet; no domain schema changes.

### Wave 1 — First routed capability + Wbp/Kunjungan on legacy tables (6–10 weeks)

**Recommended first route:** a **narrow, low-risk write** (e.g. catat kunjungan **or** a small Referensi write) to prove L2 — not remisi.

**Scope:**

- CI4 models for `identitas` + `kunjungan` (exact structure) under modules **Wbp** / **Kunjungan**.
- Wbp read/search + Kunjungan CRUD services on shared DB.
- Feature tests with **legacy-shaped** fixtures (not greenfield `inmates`).
- Legacy proxy for chosen process at pilot UPT (L2).
- Rollback drill: switch off → local path works.

**Exit criteria:**

- [ ] L2 live for pilot process on staging; soak metrics (latency, error rate, double-submit).
- [ ] Feature tests green for API process.
- [ ] Zero dual-writer incidents (monitoring: only API touches those tables during routed actions).
- [ ] Audit shows actor + via=legacy-proxy.

### Wave 2 — Perkara depth + more L2 routes (8–12 weeks)

- **Perkara** on `perkara` / `hukuman` / …  
- Timeline hukuman from real columns for remisi later.  
- **Mutasi** / **Fasilitas** on mutasi/blok tables as needed.  
- Additional L2 routes (registrasi steps that can be whole-API commands).

**Exit criteria:** hukuman readable; no remisi apply without Perkara depth; more processes at L2 with switches.

### Wave 3 — Remisi policies on real tables (10–14 weeks)

- Characterization fixtures from shared DB.  
- Policy packages; usulan lifecycle on existing remisi tables.  
- L2 for validate/usulan before apply.  
- Apply only when entire write set is API-owned.

### Wave 4+ — Integrasi, Keswat, Laporan, retire local paths (L3)

- Expand L2 coverage; remove dead local code paths after soak.  
- Optional SPA direct-to-API (L3).  
- Deferred domains as needed.

---

## Module Implementation Playbook

For each process family:

1. **Inventory** — entry URLs, libs, tables touched, transaction boundaries.
2. **Contract** — API request/response + error mapping; match what legacy UI needs.
3. **Models** — bind to **existing** tables; Entity transformers for JSON.
4. **Service** — rules + OrgContext (`ID_UPT` scope) + `UnitOfWork`.
5. **Feature tests** — arrange legacy-like rows; assert rows + HTTP envelope.
6. **Legacy proxy** — switchable client call; map API errors to legacy UX.
7. **Staging L1** — compare API vs legacy outputs offline.
8. **L2 flip** — pilot UPT; monitor; rollback switch ready.
9. **L3** — only after product retires legacy screen.

---

## API / Interface Changes

### Envelope (existing)

Keep `ApiResponse` success/error shape for new clients. Legacy proxy may unwrap into legacy JSON/flash.

### Headers (transition)

| Header | Purpose |
|--------|---------|
| `Authorization: Bearer <service-or-user-token>` | Auth |
| `X-Id-Upt` / `X-Org-Code` | Unit context (must be authorized for token) |
| `X-Actor-Nip` | On-behalf-of user |
| `Idempotency-Key` | Safe retries |
| `Accept: application/json` | ForceJson |

### Legacy client sketch (CI2)

```php
// application/libraries/Sdp_api_client.php (illustrative)
class Sdp_api_client {
    public function post($path, array $body, array $meta = []) {
        $this->ci->load->library('curl');
        $headers = [
            'Authorization: Bearer ' . getenv('SDP_API_SERVICE_TOKEN'),
            'X-Id-Upt: ' . $meta['id_upt'],
            'X-Actor-Nip: ' . $meta['nip'],
            'Idempotency-Key: ' . ($meta['idempotency_key'] ?? uniqid('sdp', true)),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        // curl post to rtrim(base,'/').'/'.ltrim($path,'/')
        // throw / return normalized errors for MY_Controller flash
    }
}
```

Route switch:

```php
if ($this->config->item('api_route_kunjungan')) {
    $result = $this->sdp_api_client->post('api/v1/kunjungan', $payload, $meta);
} else {
    // existing local lib/model path
}
```

---

## Data Model Changes

| Category | Policy |
|----------|--------|
| Domain tables | **No structural change** as SoR |
| Indexes | Additive indexes allowed if proven necessary for API query patterns (ops-approved DDL) |
| API platform | Prefer reuse `pengguna`/ACL; additive token table only if approved (C3) |
| Greenfield CI4 migrations | **Do not deploy as production domain schema**; rebase or drop from migrate path |

---

## Alternatives Considered

| Option | Pros | Cons | Verdict |
|--------|------|------|---------|
| **A. Shared schema + legacy→API** | No data remap; true dual-code; UI continuity | HTTP txn boundary; proxy work in CI2 | **Adopt (rev 3)** |
| **B. Greenfield + ETL** (rev 2) | Clean CI4 schema | Dual SoR, legal remap risk, ETL lag | **Rejected by product** |
| **C. API-only rewrite, freeze legacy UI** | Clean client story | No transition UX; big-bang per screen | Reject for transition |
| **D. Shared schema, both write locally** | No HTTP | Divergent logic forever; worst dual-code | **Reject** |
| **E. DB views / synonyms only** | Fast reads | Does not move business rules | Optional read helper only |

---

## Security & Privacy

| Topic | Control |
|-------|---------|
| Service token | Env on UPT app server; rotate; least privilege |
| On-behalf-of | Never trust `X-Actor-*` without valid service token; bind UPT to token allow-list |
| PII | Same DB sensitivity as today; redact logs (NOMOR_INDUK partial, names) |
| Transport | HTTPS between legacy and API (same host reverse-proxy or internal TLS) |
| Secrets | Never commit `database.php` passwords; rotate sample credentials |
| Permissions | Map legacy menu R/W/N → API permissions; deny by default on new routes |

---

## Observability

| Signal | Use |
|--------|-----|
| `sdp.proxy.request` | path, id_upt, actor, latency, status |
| `sdp.api.legacy_via` | count of requests with via=legacy-proxy |
| Error rate / 5xx | Rollback trigger for L2 switch |
| Idempotency conflicts | Detect double-submit |
| Audit | Who / which process / via proxy or direct |

---

## Rollout Plan

1. **Dev:** API + legacy against local/shared Docker MariaDB with schema dump.  
2. **Staging:** anonymized UPT clone; L1 parity tests.  
3. **Pilot L2:** one process, one UPT; switch on.  
4. **Expand L2** process-by-process.  
5. **L3** when UI replaced or menu retired.  

**Rollback:** legacy switch off (immediate). API kill flag optional.

---

## Risk Register

| ID | Risk | Severity | Mitigation |
|----|------|----------|------------|
| R1 | Split-brain dual writers | Critical | C6 single writer; switches; code review checklist |
| R2 | Non-atomic HTTP boundary | Critical | Whole process in one API `UnitOfWork` |
| R3 | Auth spoofing via headers | Critical | Service token + UPT allow-list; ignore bare headers |
| R4 | Latency / availability of API takes down UI | High | Timeouts, clear errors, fast rollback switch; colocate API |
| R5 | Greenfield scaffolds confuse team | High | Rebase Wave 0; docs status “non-prod schema” |
| R6 | Fat-lib paste | High | Policy packages + fixtures |
| R7 | Soft-delete column inconsistency | Medium | Per-table model config from schema pack |
| R8 | Kanwil scope bugs | High | Reuse proven legacy scope queries in services |
| R9 | Secret leakage | Critical | Env-only; rotate |
| R10 | Idempotency gaps → duplicate rows | High | Keys + unique constraints where legacy has them |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Processes at L2 | Increasing; inventory % tracked |
| Dual-writer incidents | **0** |
| Proxy p95 latency | Budget vs local (e.g. &lt; +200ms internal) |
| Feature tests for L2 processes | 100% |
| Rollback drill | Proven each pilot |
| Schema drift (unapproved DDL) | 0 |

---

## Key Decisions

| # | Decision | Rationale |
|---|----------|-----------|
| K1 | Rewrite **logic** into CI4 modules, not framework upgrade | Maintainability |
| K2 | **Shared legacy DB structure** is SoR | Product constraint; legal continuity |
| K3 | **No greenfield domain schema** / no ETL remap path | Supersedes rev 2 K3 |
| K4 | **Two codebases** during transition on one DB | Product constraint |
| K5 | **Legacy → API HTTP** for migrated capabilities | Strangler with UI continuity |
| K6 | **Single writer** per capability after L2 | Avoid split-brain |
| K7 | Entire process in one API transaction | HTTP cannot share legacy `trans_*` |
| K8 | Service token + on-behalf-of actor/UPT | Bridge session → API |
| K9 | Per-process **legacy switch** for route/rollback | Ops simplicity |
| K10 | Remisi/integrasi = versioned policies on **existing** tables | Spec from fat libs, no paste |
| K11 | Rebase existing greenfield modules onto legacy tables | Align repo with K2 |
| K12 | Additive indexes OK; additive tables only with approval | C3 |
| K13 | Feature tests on legacy-shaped data | Contract = real schema |
| K14 | Defer biometric, full surat, Bapas, Rupbasan | Focus |
| K15 | Pilot = one UPT DB, both apps configured to it | Matches install model |
| K16 | **Indonesian module names** (`Wbp`, `Kunjungan`, `Mutasi`, `Remisi`, `Perkara`, `Keswat`, `Fasilitas`, `Referensi`, `Laporan`) | Domain language matches SDP/legacy; see [`MODULE_NAMING.md`](./MODULE_NAMING.md) |

---

## Open Questions

1. ~~**API auth storage**~~ — **Decided:** additive `api_tokens` (and API-side RBAC as needed) with actors **mapped to legacy `pengguna` / NIP**. Domain tables remain legacy-only.
2. **First L2 process:** catat **kunjungan** vs a smaller **Referensi** write vs a read-only API first? _(Open — decide before PR-11)_
3. **Password / user sync:** API users 1:1 with `pengguna`?  
4. **Deployment topology:** API colocated on same UPT host vs central API farm calling UPT DBs?  
5. **Kanwil app:** same dual-code pattern or later?  
6. **OpenAPI:** Markdown through Wave 2 (prior preference) vs early OpenAPI?  
7. **Existing greenfield migrations:** delete vs keep for isolated unit demos only?

### Resolved product inputs (rev 3)

| Topic | Decision |
|-------|----------|
| Domain schema | Unchanged legacy structure |
| Engine | Same MySQL/MariaDB |
| Transition | Two codebases, one DB |
| Strangler | Legacy HTTP → new API |
| API auth tables | Additive tokens; map to `pengguna` |
| Module names | Indonesian (`Wbp`, `Kunjungan`, …) — [`MODULE_NAMING.md`](./MODULE_NAMING.md) |

---

## References

- Target: `docs/ARCHITECTURE.md`, `app/Modules/*`, `app/Services/*`, `tests/_support/Feature/ApiFeatureTestCase.php`
- Legacy: `MY_Controller.php`, `libraries/Curl.php`, `libraries/Lib_*`, `models/*_model.php`, `controllers/api/*`, `config/database.php` (**sensitive**)

---

## PR Plan

**Scope:** foundation for shared-DB + proxy strangler + first L2 wedge.  
**Non-goals:** remisi engine complete, greenfield ETL loaders, dual-write outbox to a second schema.

### Timeline

| Window | Focus |
|--------|--------|
| **Month 1** | PR-01 … PR-10 — foundation, rebase, auth bridge, client kit, first models/tests |
| **Month 2** | PR-11 … PR-15 — first L2 route, more tables, Legal start |
| **Month 3+** | Remission characterization + policies; more L2 routes |

---

### PR-01 — Docs: rev 3 strategy + inventory scaffold

| | |
|--|--|
| **Title** | `docs: shared-DB migration strategy + capability inventory` |
| **Files** | `docs/MIGRATION_STRATEGY.md`, `docs/inventory/*`, note in `ARCHITECTURE.md` (data layer caveat) |
| **Depends on** | None |

### PR-02 — ARCHITECTURE caveat: shared legacy schema

| | |
|--|--|
| **Title** | `docs: ARCHITECTURE shared-schema and legacy-proxy notes` |
| **Files** | `docs/ARCHITECTURE.md` (OrgContext ↔ `ID_UPT`, no greenfield domain SoR) |
| **Depends on** | PR-01 |

### PR-03 — Secrets hygiene + DB config for real MariaDB legacy schema

| | |
|--|--|
| **Title** | `chore: production-shaped DB config + .env.example (no secrets)` |
| **Files** | `app/Config/Database.php`, `.env.example` |
| **Depends on** | None |
| **Description** | Point default/dev at MariaDB dump of legacy structure when available; SQLite only for pure unit tests that mock DB. |

### PR-04 — Schema contract pack (pilot tables)

| | |
|--|--|
| **Title** | `docs: schema contract for identitas, kunjungan, pengguna, upt` |
| **Files** | `docs/migration/schema-contract.md` (PK, FKs, soft-delete cols, critical indexes) |
| **Depends on** | Access to DB dump or models |

### PR-05 — Service auth + on-behalf-of filters

| | |
|--|--|
| **Title** | `feat(auth): service token + actor/UPT context for legacy proxy` |
| **Files** | filters, `AuthService` or `ServiceTokenAuth`, config, tests |
| **Depends on** | PR-03; OQ-1 resolution for token storage |

### PR-06 — FeatureFlags kill switch

| | |
|--|--|
| **Title** | `feat(core): FeatureFlags for API write kill switches` |
| **Files** | `app/Services/FeatureFlags.php`, tests |
| **Depends on** | None |

### PR-07 — Legacy Sdp_api_client kit (in legacy tree or documented patch)

| | |
|--|--|
| **Title** | `feat(legacy): Sdp_api_client + route switch helper` |
| **Files** | Legacy `application/libraries/Sdp_api_client.php`, config keys, sample usage doc in `docs/migration/legacy-proxy.md` |
| **Depends on** | PR-05 contract |
| **Description** | Lives in legacy codebase; this repo holds the integration contract doc + example. |

### PR-08 — Rename modules to Indonesian + rebase Wbp onto `identitas` (read path)

| | |
|--|--|
| **Title** | `refactor+feat(Wbp): Indonesian module name + legacy identitas models` |
| **Files** | `Inmate` → `Wbp` rename; Model `$table='identitas'`; `WbpService` / routes `/api/v1/wbp`; Autoload + Services; feature tests |
| **Depends on** | PR-04, [`MODULE_NAMING.md`](./MODULE_NAMING.md) |

### PR-09 — Rename + rebase Kunjungan onto `kunjungan`

| | |
|--|--|
| **Title** | `refactor+feat(Kunjungan): Indonesian module + legacy kunjungan tables` |
| **Files** | `Visit` → `Kunjungan`; `KunjunganService`; `/api/v1/kunjungan`; tests |
| **Depends on** | PR-08 (Wbp scope) |

### PR-10 — Legacy-shaped test fixtures harness

| | |
|--|--|
| **Title** | `test: legacy schema fixture loader for ApiFeatureTestCase` |
| **Files** | `tests/_support/*`, sample SQL/JSON seeds for identitas/kunjungan |
| **Depends on** | PR-04 |

### PR-11 — First L2 end-to-end on staging (chosen process)

| | |
|--|--|
| **Title** | `feat: first legacy→API routed process (pilot)` |
| **Files** | API endpoint polish + legacy switch wiring + runbook |
| **Depends on** | PR-05–PR-10, PR-07 |
| **Description** | Prove proxy, idempotency, rollback. |

### PR-12 — Observability + audit via=legacy-proxy

| | |
|--|--|
| **Title** | `feat: proxy metrics and audit actor attribution` |
| **Files** | logging helper, audit writer |
| **Depends on** | PR-05, PR-11 |

### PR-13 — Referensi reads from `daftar_referensi` (+ peers)

| | |
|--|--|
| **Title** | `feat(Referensi): data referensi on legacy tables` |
| **Files** | `MasterData` → `Referensi` module |
| **Depends on** | PR-04 |

### PR-14 — Perkara read: perkara + hukuman

| | |
|--|--|
| **Title** | `feat(Perkara): read models on perkara/hukuman` |
| **Files** | `Legal` → `Perkara` module |
| **Depends on** | PR-08 |

### PR-15 — Pilot L2 runbook + rollback drill checklist

| | |
|--|--|
| **Title** | `docs: L2 rollout and rollback runbook` |
| **Files** | `docs/migration/runbooks/l2-proxy-rollout.md` |
| **Depends on** | PR-11 |

### PR-16+ — Further L2 routes, Remisi policies, Mutasi, etc.

Ordered by inventory priority; each PR = one process family slice + tests + legacy switch.

### PR dependency graph

```mermaid
flowchart TB
  PR01[PR-01 strategy]
  PR02[PR-02 ARCHITECTURE]
  PR03[PR-03 DB env]
  PR04[PR-04 schema contract]
  PR05[PR-05 service auth]
  PR06[PR-06 flags]
  PR07[PR-07 legacy client]
  PR08[PR-08 identitas]
  PR09[PR-09 kunjungan]
  PR10[PR-10 fixtures]
  PR11[PR-11 first L2]
  PR12[PR-12 observability]
  PR13[PR-13 Referensi]
  PR14[PR-14 Perkara read]
  PR15[PR-15 runbook]

  PR01 --> PR02
  PR03 --> PR05
  PR04 --> PR08
  PR04 --> PR10
  PR08 --> PR09
  PR05 --> PR07
  PR05 --> PR11
  PR07 --> PR11
  PR09 --> PR11
  PR10 --> PR11
  PR06 --> PR11
  PR11 --> PR12
  PR11 --> PR15
  PR08 --> PR14
  PR04 --> PR13
```

---

*End of design document (rev 3).*

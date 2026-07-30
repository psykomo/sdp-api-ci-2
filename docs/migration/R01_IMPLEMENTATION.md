# R0 + R1 implementation notes

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Status** | Done (read-only) |

## Endpoints

### R0 Referensi (`permission:referensi.read`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/referensi/jenis-registrasi` | Active jenis reg; `?active=0` all; `?is_tahanan=0\|1` |
| GET | `/api/v1/referensi/groups` | Distinct `daftar_referensi.GROUPS` |
| GET | `/api/v1/referensi/lookups?group=Agama` | Lookups by group |
| GET | `/api/v1/referensi/lookups/{ID_LOOKUP}` | One lookup |
| GET | `/api/v1/referensi/upt?search=` | UPT list |
| GET | `/api/v1/referensi/upt/{ID_UPT}` | One UPT |

### R1 Wbp (`permission:wbp.read`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/wbp?search=&perPage=&page=` | List identitas (`IS_DELETED=0`) |
| GET | `/api/v1/wbp/{NOMOR_INDUK}` | Show identitas + active `perkara[]` |

Org scope: `organizations.code` treated as legacy **`ID_UPT`** for unit orgs. Kanwil (`type=kanwil`) has no UPT filter.

Writes (`POST/PUT/DELETE /wbp`) return domain “not implemented” until R2.

## Models (legacy tables)

- `Referensi`: `JenisRegistrasiModel`, `DaftarReferensiModel`, `UptModel`
- `Wbp`: `IdentitasModel`, `PerkaraModel`

## Local auth on `db_sdp`

Platform tables via `php spark migrate -n App` (not `--all`).

Demo:

- Email: `operator@sdp.local`
- Password: `password`
- Orgs include `KW-DKI`, `093`, `604` (codes = ID_UPT for seed data)

```bash
php spark legacy:smoke-r01
php spark serve
# then POST /api/v1/auth/login …
```

## Smoke verified

- R0 jenis_registrasi: 15 active  
- R0 Agama lookups: 7  
- R1 list: 6 identitas; UPT `093` scoped list: 5  
- R1 show: includes perkara summary  

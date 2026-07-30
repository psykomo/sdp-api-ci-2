# R4 implementation — edit registrasi (+ basic R6 reads)

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Status** | Done (spine; not full legacy parity) |

## Endpoints

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/api/v1/wbp/registrasi` | `wbp.read` |
| `GET` | `/api/v1/wbp/registrasi/{id_perkara}` | `wbp.read` |
| `PUT` / `PATCH` | `/api/v1/wbp/registrasi/{id_perkara}` | `wbp.write` |

Org scope: unit org (`organizations.code` = numeric `ID_UPT`) filters perkara by `ID_UPT`. Kanwil sees all.

## PUT body (JSON) — partial update

Only provided fields are applied to `perkara`. Optional nested:

| Field | Behaviour |
|-------|-----------|
| `id_reg`, `id_status`, `id_sub_status`, `nmr_reg_gol`, dates, `keterangan`, `lokasi_*`, instansi… | Patch `perkara` |
| `kejahatan` | **Replace** active rows: soft-delete existing (`IS_DELETED=1`), insert new array |
| `hukuman` | **Upsert** first `hukuman` row; mirrors `TAHUN/BULAN/HARI_HUKUMAN` on perkara |

After a successful update, a new `history_registrasi` snapshot is appended (`KETERANGAN=API R4 update`, `ID_USER=null` — FK → `pengguna`).

**Not in R4:** mutasi golongan (→ **M1**), full ekspirasi engine, multi-level `keputusan`, `registrasi_a45`, dokumen (R8 waived).

## Response (show / after update)

Full detail: perkara fields + `kejahatan[]` + `hukuman` + `history_count` + nested `identitas` (without nested perkara list).

## Example

```bash
# unit org id for code 093
ORG_ID=<093> ./scripts/api.sh login
ORG_ID=<093> ./scripts/api.sh wbp-create "R4 test"
ORG_ID=<093> ./scripts/api.sh registrasi <NOMOR_INDUK> BI
ORG_ID=<093> ./scripts/api.sh registrasi-show <ID_PERKARA>
ORG_ID=<093> ./scripts/api.sh registrasi-update <ID_PERKARA> "edited"
# or full body:
BODY='{"nmr_reg_gol":"BI.R4/2026","kejahatan":[{"pasal_utama":"X","is_kejahatan_utama":1}],"hukuman":{"thn_kurung":2}}' \
  ORG_ID=<093> ./scripts/api.sh registrasi-update <ID_PERKARA>

php spark legacy:smoke-r01 --registrasi
```

## Code map

| Piece | Path |
|-------|------|
| Action | `app/Modules/Wbp/Actions/UbahRegistrasi.php` |
| Reads | `WbpQueryService::listRegistrasi` / `findRegistrasiOrFail` |
| Facade | `WbpService::updateRegistrasi` / `listRegistrasi` / `findRegistrasiOrFail` |
| HTTP | `Controllers/Api/Registrasi.php` |
| Routes | `Config/Routes.php` (static `wbp/registrasi*` before `wbp/(:segment)`) |

## Next

- **R5** history registrasi — see [R05_IMPLEMENTATION.md](./R05_IMPLEMENTATION.md)
- **M1** mutasi golongan (`POST /api/v1/mutasi/golongan`) — not part of PUT registrasi
- R6 polish (richer joins / filters) as needed for ManajemenRegistrasi UI cutover

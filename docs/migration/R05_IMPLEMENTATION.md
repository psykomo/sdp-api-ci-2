# R5 implementation — history registrasi read + write

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Status** | Done (spine; parity with R4 field set, not full legacy form) |

## Endpoints

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/api/v1/wbp/registrasi/{id_perkara}/history` | `wbp.read` |
| `GET` | `/api/v1/wbp/registrasi/{id_perkara}/history/{id_history_reg}` | `wbp.read` |
| `POST` | `/api/v1/wbp/registrasi/{id_perkara}/history` | `wbp.write` |
| `PUT` / `PATCH` | `/api/v1/wbp/registrasi/{id_perkara}/history/{id_history_reg}` | `wbp.write` |
| `DELETE` | `/api/v1/wbp/registrasi/{id_perkara}/history/{id_history_reg}` | `wbp.delete` |

Org scope: parent `perkara.ID_UPT` must match unit org code (same as R4/R6).

## Behaviour

### List / show
Active rows only (`IS_DELETE = 0`). Nested under `id_perkara` (404 if history belongs to another perkara).

### POST (append)
Creates a new `history_registrasi` snapshot. Missing spine fields default from current perkara. Default `keterangan`: `API R5 append`.

### PUT (edit)
Partial update of history spine fields (same family as R4 perkara edit):

`id_reg`, `id_status`, `id_sub_status`, `nmr_reg_gol`, dates, instansi, `keterangan`, `lokasi_sel`, `lokasi_dokumen`, `tahun/bulan/hari_hukuman`.

Optional nested (shared tables on parent perkara — matches legacy `HistoryRegistrasi::simpan` D2/D3):

| Field | Behaviour |
|-------|-----------|
| `kejahatan` | Soft-delete active + insert replacements |
| `hukuman` | Upsert first PN-level row + mirror years on perkara; also mirror years onto this history row when provided |

Does **not** update `perkara` core registration fields on history-only edit (legacy simpan D1 keeps perkara update commented out).

### DELETE
Soft-delete: `IS_DELETE=1`, `KONSOLIDASI=1`.

## Not in R5 spine

Full ekspirasi engine, multi-level `keputusan` (PT/MA/PK), `registrasi_a45`, field-level log_file parity, mutasi (→ **M1**).

## Example

```bash
ORG_ID=<093> ./scripts/api.sh history-list <ID_PERKARA>
ORG_ID=<093> ./scripts/api.sh history-create <ID_PERKARA> "manual snapshot"
ORG_ID=<093> ./scripts/api.sh history-update <ID_PERKARA> <ID_HISTORY_REG> "edited"
ORG_ID=<093> ./scripts/api.sh history-delete <ID_PERKARA> <ID_HISTORY_REG>

php spark legacy:smoke-r01 --registrasi
```

## Code map

| Piece | Path |
|-------|------|
| Actions | `DaftarHistoryRegistrasi`, `UbahHistoryRegistrasi`, `HapusHistoryRegistrasi` |
| Reads | `WbpQueryService::listHistory` / `findHistoryOrFail` |
| HTTP | `Controllers/Api/HistoryRegistrasi.php` |
| Routes | under `wbp/registrasi/(:segment)/history…` (before show registrasi) |

## Next

R5 complete. **M1** done: [M01_IMPLEMENTATION.md](./M01_IMPLEMENTATION.md).  

Epic remaining: L2 proxy, M2, optional R6/R7 — [PROGRESS.md](./PROGRESS.md).

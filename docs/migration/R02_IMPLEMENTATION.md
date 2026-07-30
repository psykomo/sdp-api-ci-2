# R2 implementation — identitas write

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Status** | Done |

## Endpoints

| Method | Path | Permission |
|--------|------|------------|
| POST | `/api/v1/wbp` | `wbp.write` |
| PUT/PATCH | `/api/v1/wbp/{NOMOR_INDUK}` | `wbp.write` |
| DELETE | `/api/v1/wbp/{NOMOR_INDUK}` | `wbp.delete` |

## Behaviour

### Create
- Body (snake_case): `nama_lengkap` (required), optional demographics (`tanggal_lahir`, `id_jenis_kelamin`, `alamat`, `nik`, …).
- `nomor_induk` optional; if omitted, generated as `{ID_UPT}{Ymd}{####}` from active unit org `code` (must be numeric ID_UPT, not Kanwil).
- Does **not** write `sidik_jari` / `usertbl` (deferred).
- Soft flag `IS_DELETED=0`.

### Update
- Cannot change `nomor_induk`.
- Org-scoped: unit must own via perkara at ID_UPT or UPT-prefixed new identity.

### Soft-delete
- Sets `IS_DELETED=1` only.
- **Blocked** if any active `perkara` (`IS_DELETE=0`) remain.

## Classes

- `Support/IdentitasFieldMap`
- `Actions/DaftarIdentitas`, `UbahIdentitas`, `HapusIdentitas`
- `WbpService` delegates writes to actions

## CLI / api.sh

```bash
php spark legacy:smoke-r01 --write
ORG_ID=<id for code 093> ./scripts/api.sh wbp-create "Nama Uji"
./scripts/api.sh wbp-update <NOMOR_INDUK> "Nama Baru"
./scripts/api.sh wbp-delete <NOMOR_INDUK>
```

## Next

R2 complete. **R3** (done): [R03_IMPLEMENTATION.md](./R03_IMPLEMENTATION.md). Epic progress: [PROGRESS.md](./PROGRESS.md).

# M1 implementation — mutasi golongan

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Status** | Done (spine; not full legacy form) |

## Endpoints

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/api/v1/mutasi/golongan/options?id_perkara=` | `wbp.read` |
| `GET` | `/api/v1/mutasi/golongan?id_perkara=` | `wbp.read` |
| `GET` | `/api/v1/mutasi/golongan/{id_mutasi_tahanan}` | `wbp.read` |
| `POST` | `/api/v1/mutasi/golongan` | `wbp.mutasi` |

Org scope: parent `perkara.ID_UPT` vs unit org code (same as Wbp registrasi).

## POST body

### Required

| Field | Description |
|-------|-------------|
| `id_perkara` | Active perkara |
| `id_reg_akhir` | Target `jenis_registrasi.ID_REG` (aliases: `mutasi_ke`, `id_reg`) |

### Optional

| Field | Default / notes |
|-------|-----------------|
| `id_reg_awal` | Current `perkara.ID_REG` |
| `tgl_efektif` | today |
| `nmr_srt_mg`, `tgl_srt_mg`, `penandatangan`, `keterangan` | — |
| `nmr_reg_gol` | Updates perkara if set |
| `tgl_ekspirasi`, `tgl_ekspirasi_awal`, `id_status`, `id_sub_status` | Optional perkara patch |
| `allow_any_reg` | `true` skips LEVEL progression check |

## Writes (one UnitOfWork)

1. `mutasi_golongan` insert (`ID_MUTASI_TAHANAN` generated with UPT prefix)  
2. `perkara` update: `ID_REG`, `IS_TAHANAN`, optional `NMR_REG_GOL` / surat / dates  
3. `history_registrasi` snapshot (`KETERANGAN=API M1 mutasi golongan A→B`)

## Rules (spine)

- Target must exist in `jenis_registrasi`
- Target ≠ source
- Default: target `LEVEL` must be **greater** than source `LEVEL` (legacy `pilihan_mutasi` query)
- `ID_USER` left null (FK → `pengguna`, not API users)

## Not in M1 spine

Full kejahatan/hukuman re-entry form, SPPT-TI integrate, ekspirasi engine, multi-level keputusan, **M2 mutasi UPT**.

## Example

```bash
ORG_ID=<093> ./scripts/api.sh mutasi-options <ID_PERKARA>
ORG_ID=<093> ./scripts/api.sh mutasi-golongan <ID_PERKARA> BIII
ORG_ID=<093> ./scripts/api.sh mutasi-list <ID_PERKARA>

php spark legacy:smoke-r01 --registrasi   # includes M1 after R5
```

## Code map

| Piece | Path |
|-------|------|
| Action | `app/Modules/Mutasi/Actions/MutasiGolongan.php` |
| Service | `MutasiGolonganService` |
| Model | `MutasiGolonganModel` |
| HTTP | `Controllers/Api/MutasiGolongan.php` |
| Routes | `Modules/Mutasi/Config/Routes.php` |

## Next

- **M2** mutasi UPT (`mutasi_upt*`) after M1 + R3/R4 stable  
- Optional: richer mutasi form fields / field-level audit  

# R3 implementation — registrasi create (spine)

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Status** | Done (spine; not full legacy parity) |

## Endpoint

`POST /api/v1/wbp/registrasi`  
Permission: `wbp.write`  
Requires unit org (`organizations.code` = numeric `ID_UPT`) or body `id_upt`.

## Body (JSON)

### Required

| Field | Description |
|-------|-------------|
| `nomor_induk` | Existing identitas |
| `id_reg` | `jenis_registrasi.ID_REG` (e.g. `BI`, `AI`) |

### Common optional

| Field | Default |
|-------|---------|
| `id_status` | `STA` |
| `id_sub_status` | `SSA1` |
| `nmr_reg_gol` | — |
| `nmr_reg_instansi` | — |
| `tgl_msk_lapas` | today |
| `tgl_ekspirasi` / `tgl_ekspirasi_awal` | — |
| `tgl_pertama_ditahan` / `tgl_akhir_ditahan` | — |
| `id_instansi_penyidik` | — (must exist in `instansi` if set) |
| `id_upt` | from active org code |
| `id_perkara` | auto-generated |
| `is_map` | false (MAP variant flag; core spine same) |
| `keterangan`, `lokasi_blok`, `lokasi_sel` | — |

### `kejahatan` (array, optional)

| Field | Notes |
|-------|--------|
| `pasal_utama`, `pasal_tambahan`, `uu_kejahatan` | |
| `id_terminologi` | |
| `is_kejahatan_utama` | first defaults to 1 |
| `noreggol` | defaults to `nmr_reg_gol` |
| `wilayah`, `deskripsi` | |

### `hukuman` (object, optional — single PN-level row)

| Field | Default |
|-------|---------|
| `id_jenis_hukuman` | `PID` |
| `thn_kurung`, `bln_kurung`, `hr_kurung` | 0 |
| `tgl_putusan`, `nmr_putusan`, `pasal` | — |
| `denda`, `up`, `hakim_ketua`, `jaksa` | — |

## Writes (one UnitOfWork)

1. `perkara`  
2. `history_registrasi`  
3. `kejahatan[]`  
4. `hukuman` (optional) + mirror TAHUN/BULAN/HARI_HUKUMAN on perkara  

**Not yet:** full ekspirasi engine, keputusan PN/PT/MA/PK chain, registrasi_a45, narkotik detail, mutasi_upt, dokumen (R8 waived).

## Example

```bash
ORG_ID=<093> ./scripts/api.sh wbp-create "Nama"
# then
ORG_ID=<093> ./scripts/api.sh registrasi <NOMOR_INDUK> BI

php spark legacy:smoke-r01 --registrasi
```

## Next

- **R4** edit registrasi — see [R04_IMPLEMENTATION.md](./R04_IMPLEMENTATION.md)
- **R5** history write parity  
- **M1** mutasi golongan  
- Expand R3 (keputusan levels, ekspirasi, MAP fields) as needed  

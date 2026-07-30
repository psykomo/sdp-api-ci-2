# Schema contract — registrasi + mutasi (pilot)

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Source DB** | OrbStack `sdp-mariadb` → `db_sdp` @ `127.0.0.1:3307` |
| **Dump** | `102sdp/db_sdp_new_30072026.sql` |
| **Inventory** | [`REGISTRASI_INVENTORY.md`](./REGISTRASI_INVENTORY.md) |
| **Purpose** | Authoritative columns/PKs/FKs for CI4 models (R0–R5, M1; M2/R8 reference) |

## Rules for implementers

1. **Do not rename** tables or columns (shared legacy SoR).
2. PKs are mostly **app-generated strings** (`varbinary`/`varchar`), not auto-increment.
3. Soft-delete flags differ by table (`IS_DELETED` vs `IS_DELETE`).
4. **Never** run greenfield `spark migrate` against `db_sdp` for these domains.
5. JSON may use snake_case; persistence uses exact DB names.
6. **Critical** columns = pilot minimum; **full columns** = `$allowedFields` source.

## Slice → tables

| Slice | Tables |
|-------|--------|
| R0 Referensi / form lookups | `jenis_registrasi`, `daftar_referensi`, `upt` |
| R1–R2 Wbp identitas | `identitas` |
| R3–R4 Registrasi (+ MAP as R3) | `perkara`, `history_registrasi`, `kejahatan`, `kejahatan_narkotik`, `hukuman`, `hukuman_detil`, `keputusan`, `registrasi_a45` |
| R5 History write (parity R4) | `history_registrasi` |
| R6 List/search | `identitas`, `perkara`, `jenis_registrasi` |
| R8 waived (schema ref only) | `dokumen` |
| M1 Mutasi golongan | `mutasi_golongan` |
| M2 Mutasi UPT (after M1+R3/R4) | `mutasi_upt`, `mutasi_upt_header`, `mutasi_upt_detail` |
| Auth bridge (later) | `pengguna` |

## ER sketch (pilot)

```text
identitas (NOMOR_INDUK)
  └──<* perkara (ID_PERKARA)
         ├── ID_REG → jenis_registrasi
         ├── ID_UPT → upt
         ├──<* kejahatan → kejahatan_narkotik
         ├──<* hukuman → hukuman_detil
         ├──<* keputusan
         ├──<* history_registrasi
         ├──<* mutasi_golongan          [M1]
         ├──<* dokumen                  [R8 waived]
         └──  registrasi_a45
mutasi_upt*                             [M2 after M1+R3/R4]
daftar_referensi / pengguna             [R0 / auth]
```

## Soft-delete matrix

| Table | Flag | Active |
|-------|------|--------|
| `jenis_registrasi` | — | app-defined |
| `daftar_referensi` | — | app-defined |
| `upt` | — | app-defined |
| `identitas` | `IS_DELETED` | `0` |
| `perkara` | `IS_DELETE` | `0` |
| `history_registrasi` | `IS_DELETE` | `0` |
| `kejahatan` | `IS_DELETED` | `0` |
| `kejahatan_narkotik` | — | app-defined |
| `hukuman` | — | app-defined |
| `hukuman_detil` | `IS_DELETED` | `0` |
| `keputusan` | — | app-defined |
| `registrasi_a45` | — | app-defined |
| `dokumen` | `IS_DELETED` | `0` |
| `mutasi_golongan` | — | app-defined |
| `mutasi_upt` | — | app-defined |
| `mutasi_upt_header` | — | app-defined |
| `mutasi_upt_detail` | — | app-defined |
| `pengguna` | `IS_DELETE` | `0` |

## Table contracts

### `jenis_registrasi`

| | |
|--|--|
| **PK** | `ID_REG` |
| **Soft delete** | _none_ |
| **Seed rows** | 17 |
| **Columns** | 18 |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_REG` | `varbinary(35)` | NO | PRI |
| `DESKRIPSI` | `varchar(50)` | YES |  |
| `IS_TAHANAN` | `tinyint(4)` | YES | MUL |
| `IS_ACTIVE` | `tinyint(1)` | YES |  |
| `LEVEL` | `tinyint(4)` | YES | MUL |

<details><summary>Full columns (18)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_REG` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_JENIS_REGISTRASI` | `varchar(4)` | YES | MUL | NULL |  |  |
| 3 | `DESKRIPSI` | `varchar(50)` | YES |  | NULL |  |  |
| 4 | `LAMA_HUKUMAN` | `int(11)` | YES |  | NULL |  |  |
| 5 | `PERPANJANG_1` | `int(11)` | YES |  | NULL |  |  |
| 6 | `PERPANJANG_2` | `int(11)` | YES |  | NULL |  |  |
| 7 | `PERPANJANG_3` | `int(11)` | YES |  | NULL |  |  |
| 8 | `PERPANJANG_4` | `int(11)` | YES |  | NULL |  |  |
| 9 | `LAMA_HUKUMAN_ANAK` | `int(11)` | YES |  | NULL |  |  |
| 10 | `PERPANJANG_ANAK_1` | `int(11)` | YES |  | NULL |  |  |
| 11 | `PERPANJANG_ANAK_2` | `int(11)` | YES |  | NULL |  |  |
| 12 | `PERPANJANG_ANAK_3` | `int(11)` | YES |  | NULL |  |  |
| 13 | `PERPANJANG_ANAK_4` | `int(11)` | YES |  | NULL |  |  |
| 14 | `IS_TAHANAN` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 15 | `KETERANGAN` | `varchar(30)` | YES |  | NULL |  |  |
| 16 | `LEVEL` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 17 | `IS_ACTIVE` | `tinyint(1)` | YES |  | 1 |  |  |
| 18 | `status_download` | `tinyint(1)` | YES | MUL | 0 |  |  |

</details>

### `daftar_referensi`

| | |
|--|--|
| **PK** | `ID_LOOKUP` |
| **Soft delete** | _none_ |
| **Seed rows** | 1139 |
| **Columns** | 6 |
| **FKs** | `GROUPS`→`referensi_group_aplikasi`.`GROUP_NAME` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_LOOKUP` | `varbinary(35)` | NO | PRI |
| `DESKRIPSI` | `varchar(255)` | YES | MUL |
| `GROUPS` | `varchar(100)` | YES | MUL |

<details><summary>Full columns (6)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_LOOKUP` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `GROUPS` | `varchar(100)` | YES | MUL | NULL |  |  |
| 3 | `DESKRIPSI` | `varchar(255)` | YES | MUL | NULL |  |  |
| 4 | `CATATAN` | `varchar(255)` | YES |  | NULL |  |  |
| 5 | `CONTENT` | `text` | NO |  |  |  |  |
| 6 | `status_download` | `tinyint(1)` | YES | MUL | 0 |  |  |

</details>

### `upt`

| | |
|--|--|
| **PK** | `ID_UPT` |
| **Soft delete** | _none_ |
| **Seed rows** | 703 |
| **Columns** | 49 |
| **FKs** | `JENIS`→`daftar_referensi`.`ID_LOOKUP`; `KANWIL`→`kanwil`.`KODE`; `KELAS`→`daftar_referensi`.`ID_LOOKUP` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_UPT` | `varbinary(35)` | NO | PRI |
| `URAIAN` | `varchar(100)` | YES |  |
| `KANWIL` | `varbinary(35)` | YES | MUL |
| `JENIS` | `varbinary(35)` | YES | MUL |
| `KELAS` | `varbinary(35)` | YES | MUL |

<details><summary>Full columns (49)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_UPT` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `URAIAN` | `varchar(100)` | YES |  | NULL |  |  |
| 3 | `URAIAN_KOP` | `varchar(200)` | YES |  | NULL |  |  |
| 4 | `KANWIL` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `JENIS` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 6 | `KELAS` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 7 | `KAPASITAS` | `int(11)` | YES |  | NULL |  |  |
| 8 | `ALAMAT` | `varchar(100)` | YES |  | NULL |  |  |
| 9 | `TELPON` | `varchar(20)` | YES |  | NULL |  |  |
| 10 | `FAX` | `varchar(20)` | YES |  | NULL |  |  |
| 11 | `KEPALA_UPT` | `varchar(50)` | YES |  | NULL |  |  |
| 12 | `JABATAN_KU` | `varchar(50)` | YES |  | NULL |  |  |
| 13 | `PANGKAT_KU` | `varchar(50)` | YES |  | NULL |  |  |
| 14 | `NIP_KU` | `varchar(50)` | YES |  | NULL |  |  |
| 15 | `PEJABAT_UPT` | `varchar(50)` | YES |  | NULL |  |  |
| 16 | `JABATAN_PU` | `varchar(50)` | YES |  | NULL |  |  |
| 17 | `PANGKAT_PU` | `varchar(50)` | YES |  | NULL |  |  |
| 18 | `NIP_PU` | `varchar(50)` | YES |  | NULL |  |  |
| 19 | `IP` | `varchar(50)` | YES |  | NULL |  |  |
| 20 | `LOGIN` | `varchar(50)` | YES |  | NULL |  |  |
| 21 | `PASSWORD` | `varchar(50)` | YES |  | NULL |  |  |
| 22 | `SDP_ADA` | `tinyint(1)` | YES |  | NULL |  |  |
| 23 | `HISTORI_REMISI_TERTENTU` | `text` | YES |  | NULL |  |  |
| 24 | `KONSOLIDASI` | `int(11)` | YES |  | NULL |  |  |
| 25 | `IS_KONSOLIDASI_OFFLINE` | `tinyint(1)` | NO |  | 0 |  |  |
| 26 | `DATI2` | `varchar(4)` | YES |  | NULL |  |  |
| 27 | `REGF_MONTH` | `tinyint(2)` | YES |  | 6 |  |  |
| 28 | `status_download` | `tinyint(1)` | YES |  | 0 |  |  |
| 29 | `KAPASITAS_KUNJUNGAN` | `int(3)` | YES |  | 100 |  |  |
| 30 | `LIMIT_KUNJUNGAN` | `int(3)` | YES |  | 30 |  |  |
| 31 | `TAHUN_REMISI` | `int(4)` | NO |  | 2015 |  |  |
| 32 | `LIMIT_TAHUN_REMISI` | `int(2)` | NO |  | 5 |  |  |
| 33 | `LIMIT_KONSOLIDASI_FOTO` | `int(11)` | NO |  | 0 |  |  |
| 34 | `LIMIT_KONSOLIDASI_SIDIK_JARI` | `int(11)` | NO |  | 0 |  |  |
| 35 | `LIMIT_KONSOLIDASI_DOKUMEN` | `int(11)` | NO |  | 0 |  |  |
| 36 | `NAMA_APLIKASI` | `varchar(20)` | YES |  | 'sdp' |  |  |
| 37 | `ID_TIME_ZONE` | `varchar(50)` | YES |  | NULL |  |  |
| 38 | `PIN` | `varchar(10)` | YES | UNI | NULL |  |  |
| 39 | `BACKUP_SCHEDULER` | `time` | YES |  | NULL |  |  |
| 40 | `KONSOLIDASI_SCHEDULER` | `time` | YES |  | NULL |  |  |
| 41 | `KONSOLIDASI_SCHEDULER_INTERVAL` | `int(11)` | YES |  | NULL |  |  |
| 42 | `LAP_REG_SCHEDULER` | `time` | YES |  | NULL |  |  |
| 43 | `KONSOLIDASI_INTEGRASI_SCHEDULER` | `time` | YES |  | NULL |  |  |
| 44 | `TERIMA_DATA_INTEGRASI_SCHEDULER` | `time` | YES |  | NULL |  |  |
| 45 | `TERIMA_DATA_INTEGRASI_SCHEDULER_INTERVAL` | `int(11)` | YES |  | NULL |  |  |
| 46 | `INCREAMENT_BACKUP_NUMBER` | `int(11)` | NO |  | 0 |  |  |
| 47 | `INCREAMENT_BACKUP_TIME` | `datetime` | YES |  | NULL |  |  |
| 48 | `TGL_PEMBERLAKUAN_PERMEN` | `date` | YES |  | NULL |  |  |
| 49 | `GENERATE_INTEGRASI_SCHEDULER` | `time` | YES |  | NULL |  |  |

</details>

### `identitas`

| | |
|--|--|
| **PK** | `NOMOR_INDUK` |
| **Soft delete** | `IS_DELETED` |
| **Seed rows** | 6 |
| **Columns** | 103 |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `NOMOR_INDUK` | `varbinary(35)` | NO | PRI |
| `NAMA_LENGKAP` | `varchar(75)` | NO | MUL |
| `NAMA_ALIAS1` | `varchar(50)` | YES |  |
| `TANGGAL_LAHIR` | `date` | YES |  |
| `ID_JENIS_KELAMIN` | `varbinary(35)` | YES | MUL |
| `ID_TEMPAT_LAHIR` | `varbinary(35)` | YES | MUL |
| `ALAMAT` | `text` | YES |  |
| `NIK` | `varchar(35)` | YES |  |
| `ID_JENIS_AGAMA` | `varbinary(35)` | YES | MUL |
| `ID_JENIS_PEKERJAAN` | `varbinary(35)` | YES | MUL |
| `ID_JENIS_WARGANEGARA` | `varbinary(35)` | YES | MUL |
| `IS_DELETED` | `tinyint(1)` | YES | MUL |
| `CREATED` | `timestamp` | YES |  |
| `UPDATED` | `timestamp` | NO |  |

<details><summary>Full columns (103)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_JENIS_SUKU` | `varchar(4)` | YES | MUL | NULL |  |  |
| 2 | `ID_JENIS_SUKU_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 3 | `ID_JENIS_RAMBUT` | `varchar(4)` | YES | MUL | NULL |  |  |
| 4 | `ID_JENIS_MUKA` | `varchar(4)` | YES | MUL | NULL |  |  |
| 5 | `ID_JENIS_PENDIDIKAN` | `varchar(4)` | YES | MUL | NULL |  |  |
| 6 | `ID_JENIS_PENDIDIKAN_LAIN` | `varbinary(35)` | YES |  | NULL |  |  |
| 7 | `ID_JENIS_TANGAN` | `varchar(4)` | YES | MUL | NULL |  |  |
| 8 | `ID_JENIS_AGAMA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 9 | `ID_JENIS_AGAMA_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 10 | `ID_JENIS_PEKERJAAN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 11 | `ID_JENIS_PEKERJAAN_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 12 | `NAMA_INSTANSI_PNS` | `varchar(50)` | YES |  | NULL |  | nama instansi pns |
| 13 | `NIP` | `varchar(30)` | YES |  | NULL |  | nomor induk pegawai |
| 14 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 15 | `ID_BENTUK_MATA` | `varchar(4)` | YES | MUL | NULL |  |  |
| 16 | `ID_WARNA_MATA` | `varchar(4)` | YES | MUL | NULL |  |  |
| 17 | `ID_JENIS_KEAHLIAN_2` | `varchar(4)` | YES | MUL | NULL |  |  |
| 18 | `ID_JENIS_KEAHLIAN_2_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 19 | `ID_JENIS_HIDUNG` | `varchar(4)` | YES | MUL | NULL |  |  |
| 20 | `ID_JENIS_LEVEL_1` | `varchar(4)` | YES | MUL | NULL |  |  |
| 21 | `ID_JENIS_MULUT` | `varchar(4)` | YES | MUL | NULL |  |  |
| 22 | `ID_JENIS_LEVEL_2` | `varchar(4)` | YES | MUL | NULL |  |  |
| 23 | `ID_JENIS_WARGANEGARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 24 | `ID_NEGARA_ASING` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 25 | `ID_PROPINSI` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 26 | `ID_PROPINSI_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 27 | `ID_JENIS_STATUS_PERKAWINAN` | `varchar(4)` | YES | MUL | NULL |  |  |
| 28 | `ID_JENIS_KELAMIN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 29 | `ID_JENIS_KAKI` | `varchar(4)` | YES | MUL | NULL |  |  |
| 30 | `ID_JENIS_KEAHLIAN_1` | `varchar(4)` | YES | MUL | NULL |  |  |
| 31 | `ID_JENIS_KEAHLIAN_1_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 32 | `ID_TEMPAT_LAHIR` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 33 | `ID_TEMPAT_LAHIR_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 34 | `ID_KOTA` | `varchar(4)` | YES | MUL | NULL |  |  |
| 35 | `ID_KOTA_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 36 | `NOMOR_INDUK` | `varbinary(35)` | NO | PRI | '' |  |  |
| 37 | `ID_TEMPAT_ASAL` | `varchar(4)` | YES | MUL | NULL |  |  |
| 38 | `ID_TEMPAT_ASAL_LAIN` | `varchar(50)` | YES |  | NULL |  |  |
| 39 | `RESIDIVIS` | `varchar(4)` | YES | MUL | NULL |  |  |
| 40 | `RESIDIVIS_COUNTER` | `tinyint(4)` | YES |  | 0 |  |  |
| 41 | `NIK` | `varchar(35)` | YES |  | NULL |  |  |
| 42 | `NAMA_LENGKAP` | `varchar(75)` | NO | MUL |  |  |  |
| 43 | `NAMA_ALIAS1` | `varchar(50)` | YES |  | NULL |  |  |
| 44 | `NAMA_ALIAS2` | `varchar(50)` | YES |  | NULL |  |  |
| 45 | `NAMA_ALIAS3` | `varchar(50)` | YES |  | NULL |  |  |
| 46 | `NAMA_KECIL1` | `varchar(50)` | YES |  | NULL |  |  |
| 47 | `NAMA_KECIL2` | `varchar(50)` | YES |  | NULL |  |  |
| 48 | `NAMA_KECIL3` | `varchar(50)` | YES |  | NULL |  |  |
| 49 | `TANGGAL_LAHIR` | `date` | YES |  | NULL |  |  |
| 50 | `IS_WBP_BERESIKO_TINGGI` | `tinyint(1)` | NO |  | 0 |  |  |
| 51 | `IS_PENGARUH_TERHADAP_MASYARAKAT` | `tinyint(1)` | NO |  | 0 |  |  |
| 52 | `IS_BACA_LATIN` | `tinyint(1)` | YES |  | NULL |  |  |
| 53 | `IS_BACA_QURAN` | `tinyint(1)` | YES |  | NULL |  |  |
| 54 | `ALAMAT` | `text` | YES |  | NULL |  |  |
| 55 | `ALAMAT_ALTERNATIF` | `text` | YES |  | NULL |  |  |
| 56 | `KODEPOS` | `varchar(10)` | YES |  | NULL |  |  |
| 57 | `TELEPON` | `varchar(15)` | YES |  | NULL |  |  |
| 58 | `ALAMAT_PEKERJAAN` | `varchar(100)` | YES |  | NULL |  |  |
| 59 | `KETERANGAN_PEKERJAAN` | `varchar(100)` | YES |  | NULL |  |  |
| 60 | `MINAT` | `varchar(100)` | YES |  | NULL |  |  |
| 61 | `NM_AYAH` | `varchar(75)` | YES |  | NULL |  |  |
| 62 | `TMP_TGL_AYAH` | `varchar(100)` | YES |  | NULL |  |  |
| 63 | `NM_IBU` | `varchar(75)` | YES |  | NULL |  |  |
| 64 | `TMP_TGL_IBU` | `varchar(100)` | YES |  | NULL |  |  |
| 65 | `NM_SAUDARA` | `text` | YES |  | NULL |  |  |
| 66 | `ANAKKE` | `int(3)` | YES |  | NULL |  |  |
| 67 | `JML_SAUDARA` | `int(3)` | YES |  | NULL |  |  |
| 68 | `JML_ISTRI_SUAMI` | `int(2)` | YES |  | NULL |  |  |
| 69 | `NM_ISTRI_SUAMI` | `text` | YES |  | NULL |  |  |
| 70 | `TMP_TGL_ISTRI_SUAMI` | `varchar(100)` | YES |  | NULL |  |  |
| 71 | `JML_ANAK` | `int(11)` | YES |  | NULL |  |  |
| 72 | `NM_ANAK` | `text` | YES |  | NULL |  |  |
| 73 | `TELEPHONE_KELUARGA` | `varchar(50)` | YES |  | NULL |  |  |
| 74 | `TINGGI` | `float` | YES |  | NULL |  |  |
| 75 | `BERAT` | `float` | YES |  | NULL |  |  |
| 76 | `CACAT` | `varchar(100)` | YES |  | NULL |  |  |
| 77 | `CIRI` | `varchar(200)` | YES |  | NULL |  |  |
| 78 | `CIRI2` | `varchar(200)` | YES |  | NULL |  |  |
| 79 | `CIRI3` | `varchar(200)` | YES |  | NULL |  |  |
| 80 | `FOTO_DEPAN` | `varchar(150)` | YES |  | NULL |  |  |
| 81 | `FOTO_KANAN` | `varchar(150)` | YES |  | NULL |  |  |
| 82 | `FOTO_KIRI` | `varchar(150)` | YES |  | NULL |  |  |
| 83 | `FOTO_CIRI_1` | `varchar(150)` | YES |  | NULL |  |  |
| 84 | `FOTO_CIRI_2` | `varchar(150)` | YES |  | NULL |  |  |
| 85 | `FOTO_CIRI_3` | `varchar(150)` | YES |  | NULL |  |  |
| 86 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 87 | `KONSOLIDASI_IMAGE` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 88 | `ID_KACAMATA` | `varchar(4)` | YES | MUL | NULL |  |  |
| 89 | `ID_TELINGA` | `varchar(4)` | YES | MUL | NULL |  |  |
| 90 | `ID_WARNAKULIT` | `varchar(4)` | YES | MUL | NULL |  |  |
| 91 | `ID_BENTUKRAMBUT` | `varchar(4)` | YES | MUL | NULL |  |  |
| 92 | `ID_BENTUKBIBIR` | `varchar(4)` | YES | MUL | NULL |  |  |
| 93 | `ID_LENGAN` | `varchar(4)` | YES | MUL | NULL |  |  |
| 94 | `ID_TINGKAT_PENGHASILAN` | `varchar(4)` | YES |  | NULL |  |  |
| 95 | `NOMOR_INDUK_NASIONAL` | `varchar(50)` | NO |  |  |  |  |
| 96 | `IS_VERIFIKASI` | `tinyint(1)` | NO | MUL | 0 |  |  |
| 97 | `IS_DISABILITAS` | `tinyint(1)` | YES |  | 0 |  |  |
| 98 | `IS_DELETED` | `tinyint(1)` | YES | MUL | 0 |  |  |
| 99 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 100 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 101 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 102 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 103 | `FOTO_CLOSEUP` | `varchar(255)` | YES |  | NULL |  |  |

</details>

### `perkara`

| | |
|--|--|
| **PK** | `ID_PERKARA` |
| **Soft delete** | `IS_DELETE` |
| **Seed rows** | 6 |
| **Columns** | 132 |
| **FKs** | `ID_INSTANSI_PENYIDIK`→`instansi`.`ID_INSTANSI`; `ID_JENIS_STATUS_ANAK_DIDIK`→`daftar_referensi`.`ID_LOOKUP`; `ID_LT`→`lama_tahanan`.`ID_LT`; `ID_REG`→`jenis_registrasi`.`ID_REG`; `ID_STATUS`→`daftar_referensi`.`ID_LOOKUP`; `ID_SUB_STATUS`→`daftar_referensi`.`ID_LOOKUP`; `ID_UPT`→`upt`.`ID_UPT`; `KATEGORI_REMISI`→`daftar_referensi`.`ID_LOOKUP`; `NOMOR_INDUK`→`identitas`.`NOMOR_INDUK` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_PERKARA` | `varbinary(35)` | NO | PRI |
| `NOMOR_INDUK` | `varbinary(35)` | YES | MUL |
| `ID_UPT` | `varbinary(35)` | YES | MUL |
| `ID_REG` | `varbinary(35)` | YES | MUL |
| `ID_STATUS` | `varbinary(35)` | YES | MUL |
| `ID_SUB_STATUS` | `varbinary(35)` | YES | MUL |
| `IS_TAHANAN` | `tinyint(4)` | YES | MUL |
| `NMR_REG_GOL` | `varchar(50)` | YES |  |
| `TGL_MSK_LAPAS` | `date` | YES |  |
| `TGL_EKSPIRASI` | `date` | YES | MUL |
| `TGL_EKSPIRASI_AWAL` | `date` | YES |  |
| `TGL_PERTAMA_DITAHAN` | `date` | YES |  |
| `TGL_AKHIR_DITAHAN` | `date` | YES |  |
| `IS_DELETE` | `tinyint(1)` | NO | MUL |
| `ID_INSTANSI_PENYIDIK` | `varbinary(35)` | YES | MUL |

<details><summary>Full columns (132)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_PERKARA` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_PERKARA_PARENT` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_STATUS` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `ID_SUB_STATUS` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `ID_UPT` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 6 | `ID_LT` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 7 | `ID_REG` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 8 | `IS_TAHANAN` | `tinyint(4)` | YES | MUL | NULL |  | 0 untuk napi |
| 9 | `1 untuk tahanan` | `` |  |  |  |  |  |
| 10 | `IS_DENDA_LUNAS` | `tinyint(4)` | YES | MUL | NULL |  | 0 untuk belum lunas, 1 untuk lunas |
| 11 | `IS_UP_LUNAS` | `tinyint(4)` | YES | MUL | NULL |  | 0 BELUM LUNAS 1 SUDAH LUNAS |
| 12 | `IS_RESTITUSI_LUNAS` | `tinyint(4)` | YES |  | NULL |  |  |
| 13 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 14 | `ID_JENIS_STATUS_ANAK_DIDIK` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 15 | `ID_INSTANSI_PENYIDIK` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 16 | `ID_INSTANSI_PENYIDIK_LAIN` | `varchar(255)` | YES |  | NULL |  |  |
| 17 | `NOMOR_INDUK` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 18 | `NMR_REG_GOL` | `varchar(50)` | YES |  | NULL |  |  |
| 19 | `NMR_REG_INSTANSI` | `varchar(50)` | YES |  | NULL |  |  |
| 20 | `TGL_SRT_THN` | `date` | YES |  | NULL |  |  |
| 21 | `NMR_SRT_THN` | `varchar(50)` | YES |  | NULL |  |  |
| 22 | `NM_PJBT_THN` | `varchar(75)` | YES |  | NULL |  |  |
| 23 | `INSTANSI_THN` | `varchar(50)` | YES |  | NULL |  |  |
| 24 | `TGL_PERTAMA_DITAHAN` | `date` | YES |  | NULL |  |  |
| 25 | `TGL_AKHIR_DITAHAN` | `date` | YES |  | NULL |  |  |
| 26 | `TGL_MSK_LAPAS` | `date` | YES |  | NULL |  |  |
| 27 | `TGL_EKSPIRASI_AWAL` | `date` | YES |  | NULL |  |  |
| 28 | `TGL_EKSPIRASI` | `date` | YES | MUL | NULL |  |  |
| 29 | `TOTAL_BULAN_REMISI` | `int(4)` | YES |  | NULL |  |  |
| 30 | `TOTAL_HARI_REMISI` | `int(4)` | YES |  | NULL |  |  |
| 31 | `TGL_SRT_PEJABAT_MENAHAN` | `date` | YES | MUL | NULL |  |  |
| 32 | `TGL_SRT_PELIMPAHAN` | `date` | YES | MUL | NULL |  |  |
| 33 | `NMR_SRT_PELIMPAHAN` | `varchar(50)` | YES |  | NULL |  |  |
| 34 | `KETERANGAN` | `varchar(100)` | YES |  | NULL |  |  |
| 35 | `LOKASI_DOKUMEN` | `varchar(50)` | YES |  | NULL |  |  |
| 36 | `LOKASI_SEL` | `varchar(50)` | YES | MUL | NULL |  |  |
| 37 | `LOKASI_BLOK` | `varchar(20)` | YES | MUL | NULL |  |  |
| 38 | `BARANG_BAWAAN` | `varchar(200)` | YES |  | NULL |  |  |
| 39 | `TGL_ENTRY` | `datetime` | YES |  | NULL |  |  |
| 40 | `IS_DELETE` | `tinyint(1)` | NO | MUL | 0 |  | 0 untuk aktif 1 untuk terdelete |
| 41 | `NMR_SRT_MG` | `varchar(10)` | YES |  | NULL |  |  |
| 42 | `TGL_SRT_MG` | `date` | YES |  | NULL |  |  |
| 43 | `TGL_EFEKTIF` | `date` | YES |  | NULL |  |  |
| 44 | `PENANDATANGAN` | `varchar(20)` | YES |  | NULL |  |  |
| 45 | `TGL_MG` | `date` | YES |  | NULL |  |  |
| 46 | `DOKUMEN_TERSEDIA_AKHIR` | `tinyint(4)` | YES |  | NULL |  | 0 UNTUK TIDAK ADA 1 UNTUK ADA |
| 47 | `NMR_PUTUSAN_AKHIR` | `varchar(50)` | YES |  | NULL |  |  |
| 48 | `TGL_PUTUSAN_AKHIR` | `date` | YES |  | NULL |  |  |
| 49 | `TGL_MENJALANI_PUTUSAN_AKHIR` | `date` | YES |  | NULL |  |  |
| 50 | `TGL_BA8` | `date` | YES |  | NULL |  |  |
| 51 | `DENDA_AKHIR` | `decimal(20,2)` | YES |  | NULL |  |  |
| 52 | `SISA_DENDA_AKHIR` | `decimal(20,2)` | YES |  | NULL |  |  |
| 53 | `THN_SUB_DENDA_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 54 | `BLN_SUB_DENDA_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 55 | `HR_SUB_DENDA_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 56 | `UP_AKHIR` | `decimal(20,2)` | YES |  | NULL |  |  |
| 57 | `SISA_UP_AKHIR` | `decimal(20,2)` | YES |  | NULL |  |  |
| 58 | `UP_TANGGUNG_RENTENG` | `tinyint(4)` | YES |  | 0 |  | 0 = TIDAK, 1 = TANGGUNG RENTENG |
| 59 | `THN_SUB_UP_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 60 | `BLN_SUB_UP_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 61 | `HR_SUB_UP_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 62 | `RESTITUSI_AKHIR` | `decimal(20,2)` | YES |  | NULL |  |  |
| 63 | `SISA_RESTITUSI_AKHIR` | `decimal(20,2)` | YES |  | NULL |  |  |
| 64 | `THN_SUB_RESTITUSI_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 65 | `BLN_SUB_RESTITUSI_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 66 | `HR_SUB_RESTITUSI_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 67 | `RES_TANGGUNG_RENTENG` | `tinyint(4)` | YES |  | NULL |  |  |
| 68 | `TAHUN_HUKUMAN` | `int(3)` | YES |  | NULL |  |  |
| 69 | `BULAN_HUKUMAN` | `int(3)` | YES |  | NULL |  |  |
| 70 | `HARI_HUKUMAN` | `int(3)` | YES |  | NULL |  |  |
| 71 | `TAHUN_PENGURANG` | `int(3)` | YES |  | NULL |  |  |
| 72 | `BULAN_PENGURANG` | `int(3)` | YES |  | NULL |  |  |
| 73 | `HARI_PENGURANG` | `int(3)` | YES |  | NULL |  |  |
| 74 | `TAHUN_CABUTPB` | `int(3)` | YES |  | NULL |  |  |
| 75 | `BULAN_CABUTPB` | `int(3)` | YES |  | NULL |  |  |
| 76 | `HARI_CABUTPB` | `int(3)` | YES |  | NULL |  |  |
| 77 | `TGL_MENJALANI_CABUTPB` | `date` | YES |  | NULL |  |  |
| 78 | `MENJALANI_CABUTPB_KE` | `tinyint(1)` | YES |  | NULL |  |  |
| 79 | `ASAL_TAHANAN` | `varchar(30)` | YES |  | NULL |  |  |
| 80 | `TGL_KEJADIAN` | `date` | YES |  | NULL |  |  |
| 81 | `JAM_KEJADIAN` | `time` | YES |  | NULL |  |  |
| 82 | `TEMPAT_KEJADIAN` | `varchar(30)` | YES |  | NULL |  |  |
| 83 | `RISALAH_KEJADIAN_PERKARA` | `text` | YES |  | NULL |  |  |
| 84 | `IS_PIDANA_DILUAR_KEBIASAAN` | `tinyint(1)` | NO |  | 0 |  |  |
| 85 | `KONSOLIDASI` | `tinyint(4)` | YES |  | NULL |  |  |
| 86 | `KATEGORI_REMISI` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 87 | `NMR_SURAT_JC` | `varchar(50)` | YES |  | NULL |  |  |
| 88 | `TGL_SURAT_JC` | `date` | YES |  | NULL |  |  |
| 89 | `BERSEDIA_JC` | `tinyint(1)` | YES |  | NULL |  |  |
| 90 | `TGL_KIRIM_JC` | `date` | YES |  | NULL |  |  |
| 91 | `TGL_TERIMA_JC` | `date` | YES |  | NULL |  |  |
| 92 | `STATUS_JC` | `tinyint(1)` | NO |  | 0 |  |  |
| 93 | `ID_JC_ASAL` | `varbinary(35)` | YES |  | NULL |  |  |
| 94 | `TGL_SURAT_SETIA_NKRI` | `date` | YES |  | NULL |  |  |
| 95 | `BERSEDIA_SETIA_NKRI` | `tinyint(1)` | YES |  | NULL |  |  |
| 96 | `IKUT_DERADIKALISASI` | `tinyint(1)` | YES |  | NULL |  |  |
| 97 | `NMR_SURAT_PELAKU_UTAMA` | `varchar(50)` | YES |  | NULL |  |  |
| 98 | `TGL_SURAT_PELAKU_UTAMA` | `date` | YES |  | NULL |  |  |
| 99 | `TGL_KIRIM_PELAKU_UTAMA` | `date` | YES |  | NULL |  |  |
| 100 | `TGL_TERIMA_PELAKU_UTAMA` | `date` | YES |  | NULL |  |  |
| 101 | `STATUS_PELAKU_UTAMA` | `tinyint(1)` | NO |  | 0 |  |  |
| 102 | `IS_MILITER` | `tinyint(1)` | YES |  | 0 |  |  |
| 103 | `IS_ANAK` | `tinyint(1)` | NO |  | 0 |  |  |
| 104 | `TGL_SEPERTIGA` | `date` | YES |  | NULL |  |  |
| 105 | `TGL_SEPERTIGA_REMISI` | `date` | YES |  | NULL |  |  |
| 106 | `TGL_SETENGAH` | `date` | YES |  | NULL |  |  |
| 107 | `TGL_SETENGAH_AWAL` | `date` | YES |  | NULL |  |  |
| 108 | `TGL_DUAPERTIGA` | `date` | YES |  | NULL |  |  |
| 109 | `TGL_DUAPERTIGA_AWAL` | `date` | YES |  | NULL |  |  |
| 110 | `NAMA_HAKIM_UTAMA` | `varchar(30)` | YES |  | NULL |  |  |
| 111 | `NAMA_JAKSA_UTAMA` | `varchar(30)` | YES |  | NULL |  |  |
| 112 | `HARI_DILUAR_UPT` | `int(5)` | YES |  | NULL |  |  |
| 113 | `KEJAKSAAN` | `int(11)` | NO |  | 0 |  |  |
| 114 | `KEPOLISIAN` | `varchar(50)` | NO |  | '' |  |  |
| 115 | `TGL_AWAL_GOLONGAN` | `date` | YES |  | NULL |  |  |
| 116 | `LAMA_DITAHAN` | `int(4)` | YES |  | NULL |  |  |
| 117 | `TGL_VERIFIKASI` | `date` | YES |  | NULL |  |  |
| 118 | `TGL_AWAL_TAHAN_GOLONGAN` | `date` | YES |  | NULL |  |  |
| 119 | `EKSEKUSI_JAKSA` | `varchar(5)` | YES |  | NULL |  |  |
| 120 | `TGL_UPDATE` | `timestamp` | YES |  | NULL |  |  |
| 121 | `JATUH_VONIS` | `varchar(5)` | YES |  | NULL |  |  |
| 122 | `TAHUN_POTAH` | `int(3)` | YES |  | NULL |  |  |
| 123 | `BULAN_POTAH` | `int(3)` | YES |  | NULL |  |  |
| 124 | `JENIS_RUMUS` | `tinyint(4)` | YES |  | 0 |  |  |
| 125 | `HARI_POTAH` | `int(3)` | YES |  | NULL |  |  |
| 126 | `STATUS_VERIFIKASI` | `tinyint(1)` | YES |  | 0 |  |  |
| 127 | `APPROVED` | `timestamp` | YES |  | NULL |  |  |
| 128 | `APPROVED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 129 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 130 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 131 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 132 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `history_registrasi`

| | |
|--|--|
| **PK** | `ID_HISTORY_REG` |
| **Soft delete** | `IS_DELETE` |
| **Seed rows** | 25 |
| **Columns** | 119 |
| **FKs** | `ID_INSTANSI_PENYIDIK`→`instansi`.`ID_INSTANSI`; `ID_LT`→`lama_tahanan`.`ID_LT`; `ID_PERKARA`→`perkara`.`ID_PERKARA`; `ID_REG`→`jenis_registrasi`.`ID_REG`; `ID_UPT`→`upt`.`ID_UPT`; `ID_USER`→`pengguna`.`ID_USER` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_HISTORY_REG` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |
| `NOMOR_INDUK` | `varbinary(35)` | YES |  |
| `ID_UPT` | `varbinary(35)` | YES | MUL |
| `ID_REG` | `varbinary(35)` | YES | MUL |
| `ID_STATUS` | `varbinary(35)` | YES | MUL |
| `IS_DELETE` | `tinyint(2)` | NO |  |
| `NMR_REG_GOL` | `varchar(50)` | YES |  |
| `IS_TAHANAN` | `tinyint(4)` | YES | MUL |

<details><summary>Full columns (119)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_HISTORY_REG` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_PERKARA_PARENT` | `varchar(15)` | YES | MUL | NULL |  |  |
| 4 | `ID_STATUS` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `ID_SUB_STATUS` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 6 | `ID_UPT` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 7 | `ID_LT` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 8 | `ID_REG` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 9 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 10 | `ID_JENIS_STATUS_ANAK_DIDIK` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 11 | `ID_INSTANSI_PENYIDIK` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 12 | `ID_INSTANSI_PENYIDIK_LAIN` | `varchar(255)` | YES |  | NULL |  |  |
| 13 | `NOMOR_INDUK` | `varbinary(35)` | YES |  | NULL |  |  |
| 14 | `NMR_REG_GOL` | `varchar(50)` | YES |  | NULL |  |  |
| 15 | `NMR_REG_INSTANSI` | `varchar(50)` | YES |  | NULL |  |  |
| 16 | `TGL_SRT_THN` | `date` | YES |  | NULL |  |  |
| 17 | `NMR_SRT_THN` | `varchar(50)` | YES |  | NULL |  |  |
| 18 | `NM_PJBT_THN` | `varchar(75)` | YES |  | NULL |  |  |
| 19 | `INSTANSI_THN` | `varchar(50)` | YES |  | NULL |  |  |
| 20 | `TGL_PERTAMA_DITAHAN` | `date` | YES |  | NULL |  |  |
| 21 | `TGL_AKHIR_DITAHAN` | `date` | YES |  | NULL |  |  |
| 22 | `TGL_MSK_LAPAS` | `date` | YES |  | NULL |  |  |
| 23 | `TGL_EKSPIRASI_AWAL` | `date` | YES |  | NULL |  |  |
| 24 | `TGL_EKSPIRASI` | `date` | YES |  | NULL |  |  |
| 25 | `TOTAL_BULAN_REMISI` | `int(4)` | YES |  | NULL |  |  |
| 26 | `TOTAL_HARI_REMISI` | `int(4)` | YES |  | NULL |  |  |
| 27 | `TGL_SRT_PEJABAT_MENAHAN` | `date` | YES |  | NULL |  |  |
| 28 | `TGL_SRT_PELIMPAHAN` | `date` | YES |  | NULL |  |  |
| 29 | `NMR_SRT_PELIMPAHAN` | `varchar(50)` | YES |  | NULL |  |  |
| 30 | `KETERANGAN` | `varchar(100)` | YES |  | NULL |  |  |
| 31 | `LOKASI_DOKUMEN` | `varchar(50)` | YES |  | NULL |  |  |
| 32 | `LOKASI_SEL` | `varchar(50)` | YES |  | NULL |  |  |
| 33 | `BARANG_BAWAAN` | `varchar(200)` | YES |  | NULL |  |  |
| 34 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 35 | `IS_DELETE` | `tinyint(2)` | NO |  | 0 |  | 0 untuk aktif 1 untuk terdelete |
| 36 | `NMR_SRT_MG` | `varchar(10)` | YES |  | NULL |  |  |
| 37 | `TGL_SRT_MG` | `date` | YES |  | NULL |  |  |
| 38 | `TGL_EFEKTIF` | `date` | YES |  | NULL |  |  |
| 39 | `PENANDATANGAN` | `varchar(20)` | YES |  | NULL |  |  |
| 40 | `TGL_MG` | `date` | YES |  | NULL |  |  |
| 41 | `DOKUMEN_TERSEDIA_AKHIR` | `tinyint(4)` | YES |  | NULL |  | 0 UNTUK TIDAK ADA 1 UNTUK ADA |
| 42 | `NMR_PUTUSAN_AKHIR` | `varchar(50)` | YES |  | NULL |  |  |
| 43 | `TGL_PUTUSAN_AKHIR` | `date` | YES |  | NULL |  |  |
| 44 | `TGL_MENJALANI_PUTUSAN_AKHIR` | `date` | YES |  | NULL |  |  |
| 45 | `TGL_BA8` | `date` | YES |  | NULL |  |  |
| 46 | `DENDA_AKHIR` | `decimal(11,0)` | YES |  | NULL |  |  |
| 47 | `SISA_DENDA_AKHIR` | `decimal(11,0)` | YES |  | NULL |  |  |
| 48 | `THN_SUB_DENDA_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 49 | `BLN_SUB_DENDA_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 50 | `HR_SUB_DENDA_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 51 | `UP_AKHIR` | `int(11)` | YES |  | NULL |  |  |
| 52 | `SISA_UP_AKHIR` | `decimal(11,0)` | YES |  | NULL |  |  |
| 53 | `THN_SUB_UP_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 54 | `BLN_SUB_UP_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 55 | `HR_SUB_UP_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 56 | `RESTITUSI_AKHIR` | `decimal(13,0)` | YES |  | NULL |  |  |
| 57 | `SISA_RESTITUSI_AKHIR` | `decimal(13,0)` | YES |  | NULL |  |  |
| 58 | `THN_SUB_RESTITUSI_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 59 | `BLN_SUB_RESTITUSI_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 60 | `HR_SUB_RESTITUSI_AKHIR` | `int(3)` | YES |  | NULL |  |  |
| 61 | `TAHUN_HUKUMAN` | `int(3)` | YES |  | NULL |  |  |
| 62 | `BULAN_HUKUMAN` | `int(3)` | YES |  | NULL |  |  |
| 63 | `HARI_HUKUMAN` | `int(3)` | YES |  | NULL |  |  |
| 64 | `TAHUN_PENGURANG` | `int(3)` | YES |  | NULL |  |  |
| 65 | `BULAN_PENGURANG` | `int(3)` | YES |  | NULL |  |  |
| 66 | `HARI_PENGURANG` | `int(3)` | YES |  | NULL |  |  |
| 67 | `TAHUN_CABUTPB` | `int(3)` | YES |  | NULL |  |  |
| 68 | `BULAN_CABUTPB` | `int(3)` | YES |  | NULL |  |  |
| 69 | `HARI_CABUTPB` | `int(3)` | YES |  | NULL |  |  |
| 70 | `TGL_MENJALANI_CABUTPB` | `date` | YES |  | NULL |  |  |
| 71 | `ASAL_TAHANAN` | `varchar(30)` | YES |  | NULL |  |  |
| 72 | `IS_TAHANAN` | `tinyint(4)` | YES | MUL | NULL |  | 0 UNTUK NAPI 1 UNTUK TAHANAN |
| 73 | `IS_DENDA_LUNAS` | `tinyint(4)` | YES | MUL | NULL |  | 0 UNTUK BELUM BAYAR DAN TIDAK ADA DENDA 1 UNTUK LUNAS |
| 74 | `IS_UP_LUNAS` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 75 | `IS_RESTITUSI_LUNAS` | `tinyint(4)` | YES |  | NULL |  |  |
| 76 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 77 | `KATEGORI_REMISI` | `varbinary(35)` | YES |  | NULL |  |  |
| 78 | `NMR_SURAT_JC` | `varchar(50)` | YES |  | NULL |  |  |
| 79 | `TGL_SURAT_JC` | `date` | YES |  | NULL |  |  |
| 80 | `BERSEDIA_JC` | `tinyint(1)` | YES |  | NULL |  |  |
| 81 | `TGL_KIRIM_JC` | `date` | YES |  | NULL |  |  |
| 82 | `TGL_TERIMA_JC` | `date` | YES |  | NULL |  |  |
| 83 | `STATUS_JC` | `tinyint(1)` | NO |  | 0 |  |  |
| 84 | `TGL_SURAT_SETIA_NKRI` | `date` | YES |  | NULL |  |  |
| 85 | `BERSEDIA_SETIA_NKRI` | `tinyint(1)` | YES |  | NULL |  |  |
| 86 | `IKUT_DERADIKALISASI` | `tinyint(1)` | YES |  | NULL |  |  |
| 87 | `NMR_SURAT_PELAKU_UTAMA` | `varchar(50)` | YES |  | NULL |  |  |
| 88 | `TGL_SURAT_PELAKU_UTAMA` | `date` | YES |  | NULL |  |  |
| 89 | `TGL_KIRIM_PELAKU_UTAMA` | `date` | YES |  | NULL |  |  |
| 90 | `TGL_TERIMA_PELAKU_UTAMA` | `date` | YES |  | NULL |  |  |
| 91 | `STATUS_PELAKU_UTAMA` | `tinyint(1)` | NO |  | 0 |  |  |
| 92 | `IS_MILITER` | `tinyint(1)` | YES |  | 0 |  |  |
| 93 | `IS_ANAK` | `tinyint(1)` | NO |  | 0 |  |  |
| 94 | `TGL_KEJADIAN` | `date` | YES |  | NULL |  |  |
| 95 | `JAM_KEJADIAN` | `time` | YES |  | NULL |  |  |
| 96 | `TEMPAT_KEJADIAN` | `varchar(30)` | YES |  | NULL |  |  |
| 97 | `RISALAH_KEJADIAN_PERKARA` | `text` | YES |  | NULL |  |  |
| 98 | `IS_PIDANA_DILUAR_KEBIASAAN` | `tinyint(1)` | NO |  | 0 |  |  |
| 99 | `TGL_SEPERTIGA` | `date` | YES |  | NULL |  |  |
| 100 | `TGL_SEPERTIGA_REMISI` | `date` | YES |  | NULL |  |  |
| 101 | `TGL_SETENGAH` | `date` | YES |  | NULL |  |  |
| 102 | `TGL_DUAPERTIGA` | `date` | YES |  | NULL |  |  |
| 103 | `NAMA_HAKIM_UTAMA` | `varchar(30)` | YES |  | NULL |  |  |
| 104 | `NAMA_JAKSA_UTAMA` | `varchar(30)` | YES |  | NULL |  |  |
| 105 | `HARI_DILUAR_UPT` | `int(5)` | YES |  | NULL |  |  |
| 106 | `KEJAKSAAN` | `int(11)` | YES |  | NULL |  |  |
| 107 | `KEPOLISIAN` | `varchar(50)` | YES |  | NULL |  |  |
| 108 | `TGL_VERIFIKASI` | `date` | YES |  | NULL |  |  |
| 109 | `TGL_AWAL_TAHAN_GOLONGAN` | `date` | YES |  | NULL |  |  |
| 110 | `JATUH_VONIS` | `varchar(5)` | YES |  | NULL |  |  |
| 111 | `TAHUN_POTAH` | `int(3)` | YES |  | NULL |  |  |
| 112 | `BULAN_POTAH` | `int(3)` | YES |  | NULL |  |  |
| 113 | `HARI_POTAH` | `int(3)` | YES |  | NULL |  |  |
| 114 | `APPROVED` | `timestamp` | YES |  | NULL |  |  |
| 115 | `APPROVED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 116 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 117 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 118 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 119 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `kejahatan`

| | |
|--|--|
| **PK** | `ID_KEJAHATAN` |
| **Soft delete** | `IS_DELETED` |
| **Seed rows** | 8 |
| **Columns** | 19 |
| **FKs** | `ID_PERKARA`→`perkara`.`ID_PERKARA` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_KEJAHATAN` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |
| `NOREGGOL` | `varchar(50)` | YES |  |
| `ID_TERMINOLOGI` | `varbinary(35)` | YES | MUL |
| `IS_KEJAHATAN_UTAMA` | `tinyint(4)` | YES | MUL |
| `PASAL_UTAMA` | `varchar(100)` | YES |  |
| `UU_KEJAHATAN` | `varchar(100)` | YES |  |
| `IS_DELETED` | `tinyint(1)` | NO |  |

<details><summary>Full columns (19)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_KEJAHATAN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_PASAL` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `WILAYAH` | `varchar(100)` | YES |  | NULL |  |  |
| 5 | `DESKRIPSI` | `varchar(500)` | YES |  | NULL |  |  |
| 6 | `NOREGGOL` | `varchar(50)` | YES |  | NULL |  |  |
| 7 | `PASAL_TAMBAHAN` | `varchar(100)` | YES |  | NULL |  |  |
| 8 | `IS_KEJAHATAN_UTAMA` | `tinyint(4)` | YES | MUL | NULL |  | 1 Merupakan kejahatan utama  |
| 9 | `0 bukan` | `` |  |  |  |  |  |
| 10 | `PASAL_UTAMA` | `varchar(100)` | YES |  | NULL |  |  |
| 11 | `UU_KEJAHATAN` | `varchar(100)` | YES |  | NULL |  |  |
| 12 | `ID_TERMINOLOGI` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 13 | `ID_TERMINOLOGI_LAIN` | `varchar(100)` | YES |  | NULL |  |  |
| 14 | `IS_DELETED` | `tinyint(1)` | NO |  | 0 |  |  |
| 15 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 16 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 17 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 18 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 19 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `kejahatan_narkotik`

| | |
|--|--|
| **PK** | `ID_KEJAHATAN_NARKOTIK` |
| **Soft delete** | _none_ |
| **Seed rows** | 5 |
| **Columns** | 9 |
| **FKs** | `ID_KEJAHATAN`→`kejahatan`.`ID_KEJAHATAN` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_KEJAHATAN_NARKOTIK` | `varbinary(35)` | NO | PRI |
| `ID_KEJAHATAN` | `varbinary(35)` | YES | MUL |

<details><summary>Full columns (9)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 2 | `ID_KEJAHATAN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_KEJAHATAN_NARKOTIK` | `varbinary(35)` | NO | PRI | '' |  |  |
| 4 | `JENIS_USER_NARKOTIK` | `varchar(255)` | YES |  | NULL |  |  |
| 5 | `KONSOLIDASI` | `tinyint(1)` | YES | MUL | 0 |  |  |
| 6 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 7 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 8 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 9 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `hukuman`

| | |
|--|--|
| **PK** | `ID_HKMAN` |
| **Soft delete** | _none_ |
| **Seed rows** | 21 |
| **Columns** | 61 |
| **FKs** | `ID_JENIS_HUKUMAN`→`daftar_referensi`.`ID_LOOKUP`; `ID_PERKARA`→`perkara`.`ID_PERKARA` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_HKMAN` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |
| `ID_JENIS_HUKUMAN` | `varbinary(35)` | YES | MUL |
| `TGL_PUTUSAN` | `date` | YES |  |
| `NMR_PUTUSAN` | `varchar(50)` | YES |  |
| `THN_KURUNG` | `int(11)` | YES |  |
| `BLN_KURUNG` | `int(11)` | YES |  |
| `HR_KURUNG` | `int(11)` | YES |  |
| `DENDA` | `decimal(20,2)` | YES |  |
| `UP` | `decimal(20,2)` | YES |  |

<details><summary>Full columns (61)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_HKMAN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_JENIS_HUKUMAN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `TGL_PUTUSAN` | `date` | YES |  | NULL |  |  |
| 6 | `NMR_PUTUSAN` | `varchar(50)` | YES |  | NULL |  |  |
| 7 | `PASAL` | `varchar(100)` | YES |  | NULL |  |  |
| 8 | `HAKIM_KETUA` | `varchar(75)` | YES |  | NULL |  |  |
| 9 | `HAKIM_ANGGOTA1` | `varchar(75)` | YES |  | NULL |  |  |
| 10 | `HAKIM_ANGGOTA2` | `varchar(75)` | YES |  | NULL |  |  |
| 11 | `JAKSA` | `varchar(75)` | YES |  | NULL |  |  |
| 12 | `PANITERA` | `varchar(75)` | YES |  | NULL |  |  |
| 13 | `TGL_DIJALANKAN_PTSN` | `date` | YES |  | NULL |  |  |
| 14 | `THN_KURUNG` | `int(11)` | YES |  | NULL |  |  |
| 15 | `BLN_KURUNG` | `int(11)` | YES |  | NULL |  |  |
| 16 | `HR_KURUNG` | `int(11)` | YES |  | NULL |  |  |
| 17 | `DENDA` | `decimal(20,2)` | YES |  | NULL |  |  |
| 18 | `THN_SUB_DENDA` | `int(11)` | YES |  | NULL |  |  |
| 19 | `BLN_SUB_DENDA` | `int(11)` | YES |  | NULL |  |  |
| 20 | `HR_SUB_DENDA` | `int(11)` | YES |  | NULL |  |  |
| 21 | `UP` | `decimal(20,2)` | YES |  | NULL |  |  |
| 22 | `THN_SUB_UP` | `int(11)` | YES |  | NULL |  |  |
| 23 | `BLN_SUB_UP` | `int(11)` | YES |  | NULL |  |  |
| 24 | `HR_SUB_UP` | `int(11)` | YES |  | NULL |  |  |
| 25 | `IS_BYR_UP` | `tinyint(1)` | YES | MUL | NULL |  |  |
| 26 | `TGL_BYR_UP` | `date` | YES |  | NULL |  |  |
| 27 | `UP_TANGGUNG_RENTENG` | `tinyint(4)` | YES |  | 0 |  | 0 = TIDAK, 1 = TANGGUNG RENTENG |
| 28 | `RESTITUSI` | `decimal(20,2)` | YES |  | NULL |  |  |
| 29 | `THN_SUB_RESTITUSI` | `int(11)` | YES |  | NULL |  |  |
| 30 | `BLN_SUB_RESTITUSI` | `int(11)` | YES |  | NULL |  |  |
| 31 | `HR_SUB_RESTITUSI` | `int(11)` | YES |  | NULL |  |  |
| 32 | `RES_TANGGUNG_RENTENG` | `tinyint(4)` | YES |  | NULL |  |  |
| 33 | `TGL_EKSE` | `date` | YES |  | NULL |  |  |
| 34 | `TMP_EKSE` | `varchar(50)` | YES |  | NULL |  |  |
| 35 | `TGL_HKM_LAKSANA` | `date` | YES |  | NULL |  |  |
| 36 | `TGL_EKSPIRASI_PERKIRAAN` | `date` | YES |  | NULL |  |  |
| 37 | `NOREGGOL` | `varchar(50)` | YES |  | NULL |  |  |
| 38 | `JENIS_REMISI` | `varchar(4)` | YES |  | NULL |  |  |
| 39 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 40 | `JENIS_INSTANSI` | `tinyint(4)` | YES |  | NULL |  | 1 UNTUK PN |
| 41 | `2 UNTUK PT` | `` |  |  |  |  |  |
| 42 | `3 UNTUK MA` | `` |  |  |  |  |  |
| 43 | `INSTANSI` | `int(11)` | NO |  | 0 |  |  |
| 44 | `INSTANSI_LAIN` | `varchar(255)` | YES |  | NULL |  |  |
| 45 | `PERANAN_KEJAHATAN` | `varchar(75)` | YES |  | NULL |  |  |
| 46 | `IS_HUKUMAN_AKHIR` | `tinyint(4)` | YES | MUL | NULL |  | 1 ADALAH HUKUMAN AKHIR 0 BUKAN |
| 47 | `PENCABUTAN_HAK_POLITIK` | `text` | YES |  | NULL |  |  |
| 48 | `DOKUMEN_TERSEDIA` | `tinyint(4)` | YES |  | NULL |  | 0 BELUM ADA 1 SUDAH ADA |
| 49 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 50 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 51 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 52 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 53 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 54 | `PENCABUTAN_HAK_POLITIK_MEMILIH` | `tinyint(1)` | YES |  | NULL |  |  |
| 55 | `PENCABUTAN_HAK_POLITIK_DIPILIH` | `tinyint(1)` | YES |  | NULL |  |  |
| 56 | `PENC_HAKPOL_MEM_THN` | `tinyint(11)` | YES |  | NULL |  |  |
| 57 | `PENC_HAKPOL_MEM_BLN` | `tinyint(11)` | YES |  | NULL |  |  |
| 58 | `PENC_HAKPOL_MEM_HR` | `tinyint(11)` | YES |  | NULL |  |  |
| 59 | `PENC_HAKPOL_DIP_THN` | `tinyint(11)` | YES |  | NULL |  |  |
| 60 | `PENC_HAKPOL_DIP_BLN` | `tinyint(11)` | YES |  | NULL |  |  |
| 61 | `PENC_HAKPOL_DIP_HR` | `tinyint(11)` | YES |  | NULL |  |  |

</details>

### `hukuman_detil`

| | |
|--|--|
| **PK** | _none declared_ |
| **Soft delete** | `IS_DELETED` |
| **Seed rows** | 0 |
| **Columns** | 12 |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_HKMAN` | `varbinary(35)` | YES |  |

<details><summary>Full columns (12)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_DETIL_HUKUMAN` | `varbinary(35)` | NO |  |  |  |  |
| 2 | `ID_HKMAN` | `varbinary(35)` | YES |  | NULL |  |  |
| 3 | `TIPE` | `enum('DENDA','RESTITUSI','UANG_PENGGANTI')` | YES |  | NULL |  |  |
| 4 | `MATA_UANG` | `varchar(50)` | YES |  | NULL |  |  |
| 5 | `JUMLAH` | `decimal(20,2)` | YES |  | NULL |  |  |
| 6 | `SISA_AKHIR` | `decimal(20,2)` | YES |  | NULL |  | untuk keperluan jika sudah dibayar sisanya akan masuk sini |
| 7 | `IS_DELETED` | `tinyint(1)` | YES |  | 0 |  |  |
| 8 | `KONSOLIDASI` | `tinyint(4)` | YES |  | 0 |  |  |
| 9 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 10 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 11 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 12 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `keputusan`

| | |
|--|--|
| **PK** | `ID_KEPUTUSAN` |
| **Soft delete** | _none_ |
| **Seed rows** | 26 |
| **Columns** | 49 |
| **FKs** | `ID_JENIS_HUKUMAN`→`daftar_referensi`.`ID_LOOKUP`; `ID_PERKARA`→`perkara`.`ID_PERKARA` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_KEPUTUSAN` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |

<details><summary>Full columns (49)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_KEPUTUSAN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_JENIS_HUKUMAN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `TGL_PUTUSAN` | `date` | YES |  | NULL |  |  |
| 6 | `NMR_PUTUSAN` | `varchar(50)` | YES |  | NULL |  |  |
| 7 | `PASAL` | `varchar(100)` | YES |  | NULL |  |  |
| 8 | `HAKIM_KETUA` | `varchar(75)` | YES |  | NULL |  |  |
| 9 | `HAKIM_ANGGOTA1` | `varchar(75)` | YES |  | NULL |  |  |
| 10 | `HAKIM_ANGGOTA2` | `varchar(75)` | YES |  | NULL |  |  |
| 11 | `JAKSA` | `varchar(75)` | YES |  | NULL |  |  |
| 12 | `PANITERA` | `varchar(75)` | YES |  | NULL |  |  |
| 13 | `TGL_DIJALANKAN_PTSN` | `date` | YES |  | NULL |  |  |
| 14 | `THN_KURUNG` | `int(11)` | YES |  | NULL |  |  |
| 15 | `BLN_KURUNG` | `int(11)` | YES |  | NULL |  |  |
| 16 | `HR_KURUNG` | `int(11)` | YES |  | NULL |  |  |
| 17 | `DENDA` | `decimal(13,0)` | YES |  | NULL |  |  |
| 18 | `THN_SUB_DENDA` | `int(11)` | YES |  | NULL |  |  |
| 19 | `BLN_SUB_DENDA` | `int(11)` | YES |  | NULL |  |  |
| 20 | `HR_SUB_DENDA` | `int(11)` | YES |  | NULL |  |  |
| 21 | `UP` | `decimal(13,0)` | YES |  | NULL |  |  |
| 22 | `THN_SUB_UP` | `int(11)` | YES |  | NULL |  |  |
| 23 | `BLN_SUB_UP` | `int(11)` | YES |  | NULL |  |  |
| 24 | `HR_SUB_UP` | `int(11)` | YES |  | NULL |  |  |
| 25 | `IS_BYR_UP` | `tinyint(1)` | NO | MUL |  |  |  |
| 26 | `TGL_BYR_UP` | `date` | YES |  | NULL |  |  |
| 27 | `RESTITUSI` | `decimal(13,0)` | YES |  | NULL |  |  |
| 28 | `THN_SUB_RESTITUSI` | `int(11)` | YES |  | NULL |  |  |
| 29 | `BLN_SUB_RESTITUSI` | `int(11)` | YES |  | NULL |  |  |
| 30 | `HR_SUB_RESTITUSI` | `int(11)` | YES |  | NULL |  |  |
| 31 | `TGL_EKSE` | `date` | YES |  | NULL |  |  |
| 32 | `TMP_EKSE` | `varchar(50)` | YES |  | NULL |  |  |
| 33 | `TGL_HKM_LAKSANA` | `date` | YES |  | NULL |  |  |
| 34 | `TGL_EKSPIRASI_PERKIRAAN` | `date` | YES |  | NULL |  |  |
| 35 | `NOREGGOL` | `varchar(50)` | YES |  | NULL |  |  |
| 36 | `JENIS_REMISI` | `varchar(4)` | YES |  | NULL |  |  |
| 37 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 38 | `JENIS_INSTANSI` | `tinyint(4)` | YES |  | NULL |  | 1 UNTUK PN  |
| 39 | `2 UNTUK PT` | `` |  |  |  |  |  |
| 40 | `3 UNTUK MA` | `` |  |  |  |  |  |
| 41 | `INSTANSI` | `int(11)` | NO |  | 0 |  |  |
| 42 | `INSTANSI_LAIN` | `varchar(255)` | YES |  | NULL |  |  |
| 43 | `IS_HUKUMAN_AKHIR` | `tinyint(4)` | YES | MUL | NULL |  | 1 UNTUK HUKUMAN AKHIR 0 BUKAN |
| 44 | `DOKUMEN_TERSEDIA` | `tinyint(4)` | YES |  | NULL |  | 0 BELUM ADA 1 SUDAH ADA |
| 45 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 46 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 47 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 48 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 49 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `registrasi_a45`

| | |
|--|--|
| **PK** | _none declared_ |
| **Soft delete** | _none_ |
| **Seed rows** | 0 |
| **Columns** | 10 |
| **FKs** | `ID_PERKARA`→`perkara`.`ID_PERKARA` |

<details><summary>Full columns (10)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 2 | `FLAG` | `varchar(50)` | YES | MUL | NULL |  |  |
| 3 | `PENGAJU` | `varchar(50)` | YES |  | NULL |  |  |
| 4 | `TGL` | `date` | YES |  | NULL |  |  |
| 5 | `NOMOR` | `varchar(50)` | YES |  | NULL |  |  |
| 6 | `PUTUSAN` | `varchar(100)` | YES |  | NULL |  |  |
| 7 | `PASAL` | `varchar(100)` | YES |  | NULL |  |  |
| 8 | `LAMA_PIDANA` | `int(11)` | YES |  | NULL |  |  |
| 9 | `NOREGGOL` | `varchar(50)` | YES |  | NULL |  |  |
| 10 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |

</details>

### `dokumen`

| | |
|--|--|
| **PK** | `ID_DOKUMEN` |
| **Soft delete** | `IS_DELETED` |
| **Seed rows** | 111 |
| **Columns** | 19 |
| **FKs** | `ID_KANWIL`→`kanwil`.`KODE`; `ID_PERKARA`→`perkara`.`ID_PERKARA`; `ID_UPT`→`upt`.`ID_UPT` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_DOKUMEN` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |
| `ID_UPT` | `varbinary(35)` | YES | MUL |
| `JENIS_DOKUMEN` | `varchar(100)` | NO | MUL |
| `NAMA_FILE` | `varchar(150)` | NO |  |
| `URL` | `text` | NO |  |
| `IS_DELETED` | `tinyint(1)` | NO |  |

<details><summary>Full columns (19)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_DOKUMEN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_UPT` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_KANWIL` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `JENIS_DOKUMEN` | `varchar(100)` | NO | MUL |  |  |  |
| 6 | `SUB_JENIS_DOKUMEN` | `varchar(150)` | YES | MUL | NULL |  |  |
| 7 | `ID_REFERENCE` | `varchar(50)` | NO | MUL |  |  |  |
| 8 | `URL` | `text` | NO |  |  |  |  |
| 9 | `NAMA_FILE` | `varchar(150)` | NO |  |  |  |  |
| 10 | `MIME_TYPE` | `varchar(150)` | YES |  | NULL |  |  |
| 11 | `JUDUL` | `varchar(100)` | YES | MUL | NULL |  |  |
| 12 | `KETERANGAN` | `text` | YES |  | NULL |  |  |
| 13 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 14 | `KONSOLIDASI` | `tinyint(4)` | NO |  | 0 |  |  |
| 15 | `IS_DELETED` | `tinyint(1)` | NO |  | 0 |  |  |
| 16 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 17 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 18 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 19 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |

</details>

### `mutasi_golongan`

| | |
|--|--|
| **PK** | `ID_MUTASI_TAHANAN` |
| **Soft delete** | _none_ |
| **Seed rows** | 15 |
| **Columns** | 16 |
| **FKs** | `ID_PERKARA`→`perkara`.`ID_PERKARA` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_MUTASI_TAHANAN` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |
| `ID_REG_AWAL` | `varchar(10)` | YES | MUL |
| `ID_REG_AKHIR` | `varchar(10)` | YES | MUL |
| `TGL_EFEKTIF` | `date` | YES |  |
| `NMR_SRT_MG` | `varchar(15)` | YES |  |
| `TGL_SRT_MG` | `date` | YES |  |

<details><summary>Full columns (16)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_MUTASI_TAHANAN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `NMR_SRT_MG` | `varchar(15)` | YES |  | NULL |  |  |
| 4 | `TGL_SRT_MG` | `date` | YES |  | NULL |  |  |
| 5 | `TGL_EFEKTIF` | `date` | YES |  | NULL |  |  |
| 6 | `PENANDATANGAN` | `varchar(20)` | YES |  | NULL |  |  |
| 7 | `ID_REG_AWAL` | `varchar(10)` | YES | MUL | NULL |  |  |
| 8 | `ID_REG_AKHIR` | `varchar(10)` | YES | MUL | NULL |  |  |
| 9 | `KETERANGAN` | `varchar(30)` | YES |  | NULL |  |  |
| 10 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 11 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 12 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 13 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 14 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 15 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 16 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `mutasi_upt`

| | |
|--|--|
| **PK** | `ID_MUTASI_TAHANAN` |
| **Soft delete** | _none_ |
| **Seed rows** | 0 |
| **Columns** | 12 |
| **FKs** | `ID_JENIS_MUTASI`→`daftar_referensi`.`ID_LOOKUP`; `ID_PERKARA`→`perkara`.`ID_PERKARA`; `ID_UPT_ASAL`→`upt`.`ID_UPT`; `ID_UPT_TUJUAN`→`upt`.`ID_UPT` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_MUTASI_TAHANAN` | `varbinary(35)` | NO | PRI |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |

<details><summary>Full columns (12)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_MUTASI_TAHANAN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_UPT_TUJUAN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `ID_UPT_ASAL` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 5 | `TGL_MUTASI` | `date` | YES |  | NULL |  |  |
| 6 | `NOMOR_SURAT_MUTASI` | `varchar(50)` | YES |  | NULL |  |  |
| 7 | `TGL_SURAT_MUTASI` | `date` | YES |  | NULL |  |  |
| 8 | `KETERANGAN_MUTASI` | `varchar(100)` | YES |  | NULL |  |  |
| 9 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 10 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 11 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 12 | `ID_JENIS_MUTASI` | `varbinary(35)` | YES | MUL | NULL |  |  |

</details>

### `mutasi_upt_header`

| | |
|--|--|
| **PK** | `ID_MUTASI_TAHANAN` |
| **Soft delete** | _none_ |
| **Seed rows** | 6 |
| **Columns** | 19 |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_MUTASI_TAHANAN` | `varbinary(35)` | NO | PRI |

<details><summary>Full columns (19)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_MUTASI_TAHANAN` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_UPT_TUJUAN` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ID_UPT_ASAL` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 4 | `TGL_MUTASI` | `date` | YES |  | NULL |  |  |
| 5 | `NOMOR_SURAT_MUTASI` | `varchar(50)` | YES |  | NULL |  |  |
| 6 | `TGL_SURAT_MUTASI` | `date` | YES |  | NULL |  |  |
| 7 | `KETERANGAN_MUTASI` | `varchar(100)` | YES |  | NULL |  |  |
| 8 | `TGL_ENTRY` | `date` | YES |  | NULL |  |  |
| 9 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 10 | `ID_USER` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 11 | `ID_JENIS_MUTASI` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 12 | `IS_TAHANAN` | `tinyint(4)` | YES |  | 1 |  | 0 untuk napi |
| 13 | `1 untuk tahanan` | `` |  |  |  |  |  |
| 14 | `DISETUJUI` | `tinyint(4)` | NO |  |  |  |  |
| 15 | `COUNTER` | `smallint(5) unsigned` | YES |  | NULL |  |  |
| 16 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 17 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 18 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 19 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `mutasi_upt_detail`

| | |
|--|--|
| **PK** | _none declared_ |
| **Soft delete** | _none_ |
| **Seed rows** | 7 |
| **Columns** | 9 |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_MUTASI_UPT_HEADER` | `varchar(20)` | NO | MUL |
| `ID_PERKARA` | `varbinary(35)` | YES | MUL |
| `ALASAN_TOLAK` | `varchar(255)` | YES |  |
| `DITOLAK` | `tinyint(1)` | YES |  |
| `KETERANGAN` | `varchar(255)` | YES |  |
| `CREATED` | `timestamp` | YES |  |
| `CREATED_BY` | `varchar(32)` | YES |  |
| `UPDATED` | `timestamp` | NO |  |
| `UPDATED_BY` | `varchar(32)` | YES |  |

<details><summary>Full columns (9)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_MUTASI_UPT_HEADER` | `varchar(20)` | NO | MUL |  |  |  |
| 2 | `ID_PERKARA` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `ALASAN_TOLAK` | `varchar(255)` | YES |  | NULL |  |  |
| 4 | `DITOLAK` | `tinyint(1)` | YES |  | 0 |  |  |
| 5 | `KETERANGAN` | `varchar(255)` | YES |  | NULL |  |  |
| 6 | `CREATED` | `timestamp` | YES |  | NULL |  |  |
| 7 | `CREATED_BY` | `varchar(32)` | YES |  | NULL |  |  |
| 8 | `UPDATED` | `timestamp` | NO |  | current_timestamp() | on update current_timestamp() |  |
| 9 | `UPDATED_BY` | `varchar(32)` | YES |  | NULL |  |  |

</details>

### `pengguna`

| | |
|--|--|
| **PK** | `ID_USER` |
| **Soft delete** | `IS_DELETE` |
| **Seed rows** | 13 |
| **Columns** | 19 |
| **FKs** | `LEVEL`→`level`.`ID` |

**Critical for pilot**

| Column | Type | Null | Key |
|--------|------|------|-----|
| `ID_USER` | `varbinary(35)` | NO | PRI |
| `PASSWORD` | `varchar(100)` | YES |  |
| `NAMA` | `varchar(75)` | YES |  |
| `NIP` | `char(20)` | YES |  |
| `ID_ROLE` | `varbinary(35)` | YES | MUL |
| `LEVEL` | `tinyint(4)` | YES | MUL |
| `IS_DELETE` | `tinyint(4)` | YES |  |
| `KANWIL` | `varbinary(35)` | NO | MUL |

<details><summary>Full columns (19)</summary>

| # | Column | Type | Null | Key | Default | Extra | Comment |
|---|--------|------|------|-----|---------|-------|---------|
| 1 | `ID_USER` | `varbinary(35)` | NO | PRI | '' |  |  |
| 2 | `ID_ROLE` | `varbinary(35)` | YES | MUL | NULL |  |  |
| 3 | `PASSWORD` | `varchar(100)` | YES |  | NULL |  |  |
| 4 | `NAMA` | `varchar(75)` | YES |  | NULL |  |  |
| 5 | `LAST_LOGIN` | `date` | YES |  | NULL |  |  |
| 6 | `LAST_CHANGE_PASSWORD` | `date` | YES |  | NULL |  |  |
| 7 | `LEVEL` | `tinyint(4)` | YES | MUL | NULL |  | 1 USER 2 ADMIN 3 SUPERVISOR |
| 8 | `EMAIL` | `varchar(30)` | YES |  | NULL |  |  |
| 9 | `NO_HP` | `varchar(15)` | YES |  | NULL |  |  |
| 10 | `UNIT_KERJA` | `varchar(15)` | YES |  | NULL |  |  |
| 11 | `NIP` | `char(20)` | YES |  | NULL |  |  |
| 12 | `KONSOLIDASI` | `tinyint(4)` | YES | MUL | NULL |  |  |
| 13 | `AKSES_DELETE` | `tinyint(4)` | YES |  | NULL |  |  |
| 14 | `KANWIL` | `varbinary(35)` | NO | MUL |  |  |  |
| 15 | `IS_DELETE` | `tinyint(4)` | YES |  | 0 |  |  |
| 16 | `IS_APPROVE` | `tinyint(1) unsigned` | NO |  | 1 |  |  |
| 17 | `TGL_APPROVE` | `varchar(75)` | YES |  | NULL |  |  |
| 18 | `USER_APPROVE` | `varchar(75)` | YES |  | NULL |  |  |
| 19 | `IP_USER` | `varchar(20)` | YES |  | NULL |  |  |

</details>

## ID generation

Preserve string IDs from legacy. On create, port generation from `identitas_model` / `perkara_model::save` / `history_registrasi_model::save` / `mutasi_golongan` save paths (document inside each Action). Do **not** invent auto-increment surrogates for these PKs.

## CI4 model checklist (next code step)

| Model | Table | First slices |
|-------|-------|--------------|
| `JenisRegistrasiModel` | `jenis_registrasi` | R0 |
| `DaftarReferensiModel` | `daftar_referensi` | R0 |
| `UptModel` (or Referensi) | `upt` | R0 / org map |
| `IdentitasModel` | `identitas` | R1–R2 |
| `PerkaraModel` | `perkara` | R1/R3/R4/R6 |
| `HistoryRegistrasiModel` | `history_registrasi` | R3/R5 |
| `KejahatanModel` | `kejahatan` | R3/R4 |
| `HukumanModel` | `hukuman` | R3/R4 |
| `MutasiGolonganModel` | `mutasi_golongan` | M1 |

## Next step after this contract

Implement **R0 + R1 read path only**: models + GET endpoints + smoke queries on live `db_sdp`. No greenfield migrations. No L2 yet.


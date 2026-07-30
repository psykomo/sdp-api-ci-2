# Registrasi inventory (canonical legacy)

| Field | Value |
|-------|--------|
| **Date** | 2026-07-30 |
| **Code** | `/Users/hap/Documents/dev/sdp/102sdp` (`staging`) — `system/application` |
| **DB** | OrbStack `sdp-mariadb` → `db_sdp` @ `127.0.0.1:3307` (dump `db_sdp_new_30072026.sql`) |
| **Goal** | Map legacy entrypoints → R-slices → tables → target API (Wbp/Perkara/Referensi) |
| **Strategy** | [`docs/MIGRATION_STRATEGY.md`](../MIGRATION_STRATEGY.md) epic “Wbp/registrasi full L2” |

This inventory is the **gate before** rebasing CI4 models or implementing L2 proxies.

---

## 1. Core data model (live DB)

| Table | PK | Approx rows (seed dump) | Soft-delete | Role |
|-------|-----|------------------------:|-------------|------|
| `identitas` | `NOMOR_INDUK` (varbinary) | 6 | `IS_DELETED` | Person / WBP identity |
| `perkara` | `ID_PERKARA` | 6 | `IS_DELETE` | Registration/case header; links to `NOMOR_INDUK`, `ID_REG`, `ID_UPT` |
| `history_registrasi` | `ID_HISTORY_REG` | 25 | `IS_DELETE` | Snapshot/history of reg changes (fields largely mirror `perkara`) |
| `jenis_registrasi` | `ID_REG` | 17 | `IS_ACTIVE` | Golongan / jenis reg master (`IS_TAHANAN`) |
| `kejahatan` | `ID_KEJAHATAN` | 8 | (see model) | Offenses on a perkara |
| `kejahatan_narkotik` | — | 5 | | Narkotik detail rows |
| `hukuman` | `ID_HKMAN` | 21 | | Sentence / putusan levels (PN/PT/MA/PK via app logic) |
| `hukuman_detil` | — | 0 | | Sentence detail lines |
| `daftar_referensi` | lookup | 1139 | | Agama, pekerjaan, … |
| `dokumen` | `ID_DOKUMEN` | 111 | `IS_DELETED` | Files tied to `ID_PERKARA` |
| `sidik_jari` | — | 1 | `IS_DELETED` | Fingerprint meta (FK `NOMOR_INDUK`) |
| `identitas_mirip` / `identitas_ktp` / `identitas_attribute_histori` | — | 0 | | Identity side tables |
| `registrasi_a45` | — | 0 | | A45-related registrasi extras |
| `mutasi_golongan` | — | 15 | | Golongan mutation (often from registrasi UI) |
| `upt` | `ID_UPT` | 703 | | Units |
| `pengguna` | `ID_USER` | 13 | `IS_DELETE` | Users |

**Keys for API design**

- IDs are **string/varbinary**, not auto-increment ints.
- Soft-delete column names **differ** (`IS_DELETED` vs `IS_DELETE`).
- Domain graph: `identitas` 1—* `perkara` 1—* (`kejahatan`, `hukuman`, `history_registrasi`, `dokumen`).
- `kunjungan` FKs to `perkara` (not inventaris for this epic).

---

## 2. R-slice reference (product epic)

| Slice | Meaning | L2 target |
|-------|---------|-----------|
| **R0** | Lookups for forms | Referensi reads |
| **R1** | Search/show identitas | Wbp GET |
| **R2** | Create/update identitas | Wbp POST/PUT |
| **R3** | Registrasi baru (multi-table) | `POST /wbp/registrasi` one command |
| **R4** | Edit registrasi/perkara | `PUT /wbp/registrasi/{id_perkara}` |
| **R5** | History registrasi read + **write (full parity with R4)** | GET + update history (same rules family as edit registrasi) |
| **R6** | Manajemen list/search registrasi | GET list |
| **R7** | Jenis registrasi master | Referensi/admin |
| **R8** | Documents in reg flow | **Waiver for pilot** — not DoD |
| **M1** | Mutasi golongan (from registrasi UI) | **Mutasi module now** — see §3.2 |
| **M2+** | Mutasi UPT package / other mutasi | Mutasi module (after M1; may trail R3 slightly) |
| **R9+** | Explicitly out or later | Portir, biometric, MAP-only unless folded in |

---

## 3. Controller → slice map

### 3.1 In scope for full registrasi L2 (primary)

| Legacy controller | ~LOC | Key methods | R-slices | Tables / models (primary) | Notes |
|-------------------|-----:|-------------|---------|---------------------------|--------|
| **`Registrasi.php`** | 3149 | `create`, `create_registrasi`, `identitas_baru`, **`insert`**, **`edit`**, **`simpan`**, `build_data`, **`mutasi`**, **`pilihan_mutasi`**, `deleteKejahatanPerkara`, document_* | **R3, R4** + **M1** (mutasi UI → Mutasi API) | `perkara`, `history_registrasi`, `kejahatan`, `hukuman`, `hukuman_detil`, `keputusan`, `registrasi_a45`, `mutasi_golongan`, `identitas` attributes | **Core write path.** `insert()` saves perkara → history → kejahatan → hukuman/keputusan (PN/PT/MA/PK) → ekspirasi → identitas attribute history. **`mutasi` / golongan change is M1 (Mutasi domain), not folded into R4 DTO.** Transactions commented out in places — API must use real `UnitOfWork`. |
| **`ManajemenRegistrasi.php`** | 2995 | `index`, `lihat`, `buildData`, `details`, `registrasi_detail*`, `new_registrasi`, `registrasi_delete*`, `submitMutasi`, cetak/export | **R6** reads; delete → **R4**/soft-delete; mutasi → later **Mutasi** | `perkara`, `identitas`, `history_registrasi`, `kejahatan`, `hukuman`, also remisi/grasi/pelanggaran for detail panels | List/detail hub. Detail screen pulls many domains — **L2 list/detail first**; nested remisi panels stay legacy until Remisi epic. |
| **`ManajemenIdentitas.php`** | 1891 | `index`, `lihat`, `viewEdit`, **`edit`**, **`delete`**, NIK/dukcapil helpers | **R1, R2** | `identitas` (+ optional mirip/ktp); **not** sidik_jari/usertbl cascade | Soft-delete: identitas only for pilot; **sidik_jari/usertbl deferred** (K25). |
| **`AddIdentitas.php`** | 988 | `createIdentitas`, `add`, `identitasLama`, search pusat APIs | **R2** (+ R1 search) | `identitas`, `usertbl`, `daftar_referensi`, `upt` | Create identity before/with registrasi. |
| **`HistoryRegistrasi.php`** | 2106 | Same shape as Registrasi: `insert`, `edit`, `simpan`, `mutasi`… | **R5 write full parity with R4** | Same stack as Registrasi; persists via `history_registrasi_model` | **In scope:** history edit must match registrasi edit capability (shared builders/actions where possible). |
| **`RegistrasiMAP.php`** | 2444 | `create_map`, `insert`, `edit`, `simpan`, … | **R3 variant** (+ R4-like edit if used) | Same core tables as Registrasi; MAP-specific fields | **In scope now** — same command family as R3 with MAP branches. |
| **`JenisRegistrasi.php`** | 335 | CRUD `add`/`edit`/`delete`/`cari` | **R7** | `jenis_registrasi` | Master data; small L2 candidate after R0. |
| **`SearchRegistrasi.php`** | 382 | `lihatPerkara`, `lihatIdentitas`, `buildData` | **R1, R6** | read | Search UI. |
| **`PencarianIdentitas.php`** | 1206 | `cari`, `buildData`, kemiripan | **R1** | `identitas` (+ mirip) | Search + similarity. |
| **`PopUpRegistrasi.php` / `PopUpRegistrasi2.php`** | ~430 | pick perkara/identitas | **R1, R6** | read | Popups for other screens. |
| **`CatatIdentitas.php`** | 95 | `add_identitas` | **R2** thin | identitas | Thin entry. |
| **`CatatIdentitasUtama.php`** | 146 | `add`, Biometric hook | **R2** partial | identitas | Biometric hook → out of core R2 unless product insists. |
| **`GagalAddIdentitas.php`** | 236 | failed-add recovery | **R2** edge | identitas | Support path. |
| **`Identitas_foto.php`** | 173 | list/foto checks | **R2** media / later R8 | foto paths on identitas | Photo files, not only DB. |

### 3.2 Mutasi domain — **in scope now** (not deferred)

**Decision (product):** Mutasi is required **now**, alongside the registrasi epic — not “later after Remisi.”

| Slice | Process | Legacy entry | Tables (primary) | Target module / API (draft) |
|-------|---------|--------------|------------------|-----------------------------|
| **M1** | **Mutasi golongan** (change `ID_REG` / reg type on active perkara) | `Registrasi::mutasi`, `pilihan_mutasi`, **`RegistrasiMutasi.php`**, parts of `ManajemenRegistrasi::submitMutasi` | `mutasi_golongan`, `perkara`, `history_registrasi`, kejahatan/hukuman as rules require | **`App\Modules\Mutasi`**: e.g. `POST /api/v1/mutasi/golongan` |
| **M2** | Mutasi UPT package (cross-unit move) | `Lib_mutasiupt`, mutasi UPT controllers | `mutasi_upt*` | Same **Mutasi** module; can trail M1 slightly but still this program phase |
| **M3** | Mutasi sel / placement (if in UPT daily path) | related mutasi sel UI | mutasi sel / blok-kamar | Mutasi + **Fasilitas** as needed |

**How it relates to R3/R4**

- **R3/R4** = create/edit registrasi payload (identitas + perkara + kejahatan + hukuman + …).
- **M1** = separate **command** owned by **Mutasi** service; may call **Wbp/Perkara** facades inside one `UnitOfWork`.
- Legacy **Registrasi UI** still hosts the button; L2 routes that action to **Mutasi API**, not into `PUT /wbp/registrasi`.
- Dependency: Mutasi needs **Perkara/Wbp readable** (and usually writable status fields) — implement **M1 after R1 + enough Perkara read**, ideally **in parallel with R3/R4**, not after Kunjungan/Remisi waves.

**Module layout (now)**

```text
app/Modules/Mutasi/
  Services/MutasiService.php          # facade (already scaffolded; rebase off greenfield)
  Actions/MutasiGolongan.php          # M1
  Actions/MutasiUpt.php               # M2 when ready
  Models → mutasi_golongan, mutasi_upt*, …
```

Greenfield `inmate_transfers` table is **not** the SoR; map to legacy `mutasi_*` tables.

### 3.3 Related but **OUT** of near-term DoD (unless reopened)

| Controller | ~LOC | Status | Later home |
|------------|-----:|--------|------------|
| **`Portir.php` / `PortirNew.php`** | 267 / 609 | **Out** (decided) | Portir module / R9 |
| **`MasihAdaPerkara.php`** | 1831 | R6-adjacent after core list | R6 variant |
| **`monitoring_data_registrasi.php`** | 1010 | Out | **Laporan** / ops |
| **`biometricregistrasi.php`** | 4185 | Out | **Biometrik** |
| **`Lib_registrasi.php` APIs** | 490 | Out | Integration epic |
| R8 dokumen paths on Registrasi | — | **Waiver** | Later dokumen slice |
| sidik_jari / usertbl on identitas delete | — | **Defer** | With biometrik / user sync |
| Remisi/grasi/integrasi panels in ManajemenRegistrasi detail | — | Out | Remisi / Integrasi |

**In scope (moved up):** `RegistrasiMAP.php` → **R3 variant**; `HistoryRegistrasi` writes → **R5 full parity**; Mutasi M1 now, **M2 after** M1+R3/R4.

---

## 4. Critical write path: `Registrasi::insert` (R3 spine)

Observed order (create path; transactions largely commented out):

```text
1. perkara_model->save($_POST)                 → perkara (+ ID_PERKARA)
2. history_registrasi_model->save($_POST)      → history_registrasi
3. mutasi_upt_model->save (conditional)        → mutasi_upt*   [borderline Mutasi]
4. kejahatan_model->save (loop)                → kejahatan
5. hukuman_model->save PN/PT/MA/PK             → hukuman
6. keputusan_model->save PN/PT/MA/PK           → keputusan*
7. registrasi_a45_model->save*                 → registrasi_a45
8. hukuman_model->set_hukuman_akhir
9. func_ekspirasi->update_ekspirasi_awal_db    → perkara date fields
10. identitas_model->set_identitas_attribute_histori  → identitas_attribute_histori
```

**API implication (R3):** one `POST /api/v1/wbp/registrasi` (name TBD) must:

- Accept structured DTO (identitas ref or embed, perkara, kejahatan[], hukuman by tingkat, optional a45).
- Run steps 1–10 (minus explicit out-of-scope mutasi_upt) inside **one UnitOfWork**.
- Generate IDs the legacy way (app-generated string IDs, not AI ints) **or** document a controlled change (product currently: **same structure** → keep string IDs).

**Edit path (`edit` / `simpan`):** updates `perkara`, may rewrite kejahatan/hukuman, history — map to **R4**.

---

## 5. Libraries (reuse as specs, not paste)

| Library | Methods (selected) | Use in migration |
|---------|-------------------|------------------|
| `Lib_identitas` | `getIdentitas`, `getPerkaras`, `setIdentitas`, `setIdentitasMirip` | R1/R2 read/write semantics |
| `lib_perkara` | `get_perkara`, `get_detail_wbp`, `get_perkara_and_kejahatan`, status validators | R4/R6 detail DTO |
| `Lib_registrasi` | party APIs + remisi SK migrasi | **Out** of core L2 |
| `func_ekspirasi` (helper/lib) | ekspirasi dates on insert | Must be ported/called for R3 correctness |

---

## 6. Models to reimplement first (CI4)

Priority for shared-schema models:

1. `IdentitasModel` → table `identitas`, PK `NOMOR_INDUK`, soft `IS_DELETED`  
2. `PerkaraModel` → `perkara`, PK `ID_PERKARA`, soft `IS_DELETE`  
3. `HistoryRegistrasiModel` → `history_registrasi`  
4. `KejahatanModel`, `HukumanModel`, `HukumanDetilModel`  
5. `JenisRegistrasiModel`, `DaftarReferensiModel`  
6. Later: `DokumenModel`, `RegistrasiA45Model`, `KeputusanModel` as R3 needs them  

Do **not** keep greenfield `inmates` / integer `organization_id` as SoR.

---

## 7. Suggested API surface (draft — after inventory)

| Method | Path (draft) | Slice | Replaces (examples) |
|--------|--------------|-------|---------------------|
| GET | `/api/v1/referensi/...` | R0 | dropdowns in AddIdentitas / Registrasi forms |
| GET | `/api/v1/wbp` | R1 | PencarianIdentitas, SearchRegistrasi list |
| GET | `/api/v1/wbp/{nomor_induk}` | R1 | Lib_identitas::getIdentitas |
| POST/PUT | `/api/v1/wbp` | R2 | AddIdentitas, ManajemenIdentitas edit |
| GET | `/api/v1/wbp/registrasi` | R6 | ManajemenRegistrasi index/buildData |
| GET | `/api/v1/wbp/registrasi/{id_perkara}` | R6 | details / registrasi_detail |
| POST | `/api/v1/wbp/registrasi` | **R3** | Registrasi::insert |
| PUT | `/api/v1/wbp/registrasi/{id_perkara}` | **R4** | Registrasi::edit/simpan |
| GET/PUT | `/api/v1/wbp/registrasi/{id_perkara}/history`… | R5 | HistoryRegistrasi read + write (parity with R4) |
| CRUD | `/api/v1/referensi/jenis-registrasi` | R7 | JenisRegistrasi controller |
| POST | `/api/v1/mutasi/golongan` | **M1** | Registrasi::mutasi, RegistrasiMutasi |
| POST | `/api/v1/mutasi/upt` (later in same phase) | **M2** | Lib_mutasiupt / mutasi UPT UI |

Permissions (examples): `wbp.read`, `wbp.write`, `wbp.registrasi.write`, `mutasi.golongan`, `mutasi.upt`, `referensi.read`, …

---

## 8. L2 proxy candidates (legacy switches)

When implementing L2, add switches per slice (not one global):

| Switch key (draft) | Legacy entry |
|--------------------|--------------|
| `api_route.wbp.identitas_write` | AddIdentitas, ManajemenIdentitas::edit |
| `api_route.wbp.registrasi_create` | Registrasi::insert |
| `api_route.wbp.registrasi_update` | Registrasi::edit/simpan |
| `api_route.wbp.registrasi_list` | ManajemenRegistrasi list (optional early) |
| `api_route.mutasi.golongan` | Registrasi::mutasi, RegistrasiMutasi |
| `api_route.mutasi.upt` | mutasi UPT controllers (M2) |

---

## 9. Implementation order (post-inventory)

| Step | Work | Depends |
|------|------|---------|
| 1 | This inventory signed / adjusted (out-of-scope list) | — |
| 2 | ~~Schema contract~~ → [`SCHEMA_CONTRACT.md`](./SCHEMA_CONTRACT.md) | Live `db_sdp` |
| 3 | CI4 models R0–R1 + GET APIs | Step 2 |
| 4 | R2 identitas write + tests | Step 3 |
| 5 | R3 RegistrasiBaru + **RegistrasiMAP variant** + characterization fixtures | Steps 2–4 |
| 6 | **M1 MutasiGolongan** + L2 from registrasi UI | Steps 3–5 (parallel R4 OK) |
| 7 | R4 edit + **R5 history write (parity with R4)** + R6 list | Step 5 |
| 8 | R7 as needed; L2 proxies for Wbp + M1 | Steps 4–7 |
| 9 | Epic pilot DoD (R0–R7 as scoped, M1; **no R8, no Portir, no sidik cascade**) | Steps 5–8 |
| 10 | **M2 Mutasi UPT** (follows M1 + R3/R4) | After pilot DoD or as capacity allows |

---

## 10. Decisions (locked)

| # | Topic | Decision |
|---|--------|----------|
| 1 | Mutasi domain timing | **Now** — M1 golongan in this phase; Mutasi module owns APIs (not inside R4 DTO) |
| 2 | **RegistrasiMAP** | **R3 variant now** — same multi-table registrasi command path / rules as core R3 (MAP-specific fields/branches as needed) |
| 3 | **HistoryRegistrasi write** | **Full parity with Registrasi edit (R4)** — history edit is in-scope write, not read-only first |
| 4 | **sidik_jari / usertbl** on identitas delete | **Defer** — not in R2 cascade scope for pilot |
| 5 | **R8 dokumen** | **Waiver** — documents not required for registrasi L2 pilot DoD; keep legacy local or later slice |
| 6 | **Portir** | **Remain out** of this epic |
| 7 | **M2 Mutasi UPT** | **Follow M1 + R3/R4** — not blocking first registrasi/golongan L2 cutover |

### Scope summary after decisions

| In scope (near-term DoD) | Out / later |
|--------------------------|-------------|
| R0–R4, R5 **read + write** (history full parity), R6, R7 as needed | R8 dokumen (waiver) |
| RegistrasiMAP as **R3 variant** | Portir |
| M1 mutasi golongan | sidik_jari / usertbl delete side effects |
| M2 mutasi UPT **after** M1+R3/R4 | Biometric, party APIs, Kunjungan, Remisi |

---

## 11. Sources

- Controllers/models under `102sdp/system/application/`  
- Live DB `db_sdp` (OrbStack)  
- Strategy epic slices R0–R8 in `docs/MIGRATION_STRATEGY.md`  

# Module naming (Bahasa Indonesia)

| Field | Value |
|-------|--------|
| **Status** | Normative |
| **Date** | 2026-07-29 |
| **Applies to** | `app/Modules/*`, API path segments, permission resources, service factories |

Domain modules use **Indonesian names** aligned with SDP / Ditjenpas vocabulary (and legacy table language).  
Shared technical core (`Auth`, `Users`, filters, `UnitOfWork`, …) may stay English.

See also: [`ARCHITECTURE.md`](./ARCHITECTURE.md), [`MIGRATION_STRATEGY.md`](./MIGRATION_STRATEGY.md).

---

## Canonical map

| Module folder / namespace | Meaning | Legacy / domain anchors | Former English name |
|--------------------------|---------|-------------------------|---------------------|
| **Wbp** | Warga Binaan Pemasyarakatan | `identitas`, registrasi, `NOMOR_INDUK` | Inmate |
| **Kunjungan** | Kunjungan | `kunjungan`, `kunjungan_*` | Visit |
| **Mutasi** | Mutasi (UPT, sel, golongan, …) | `mutasi_*`, `Lib_mutasiupt` | Transfer |
| **Remisi** | Remisi | `remisi`, `Lib_remisi*` | Remission |
| **Integrasi** | Integrasi / PB / asimilasi family (if split from Remisi) | `Lib_integrasi*`, PB | _(part of Remission)_ |
| **Perkara** | Perkara, hukuman, kejahatan, grasi data | `perkara`, `hukuman`, `kejahatan` | Legal |
| **Keswat** | Kesehatan dan perawatan | `keswat_*`, obat, bama | Medical |
| **Fasilitas** | Blok, kamar, penempatan, sarana | blok/kamar, sapras | Facility |
| **Referensi** | Data referensi / parameter | `daftar_referensi`, parameter\* | MasterData |
| **Laporan** | Laporan & agregasi | laporan\*, DWH reads | Report |

Optional later (deferred waves):

| Module | Meaning | Notes |
|--------|---------|--------|
| **Bapas** | Bapas / litmas / BPS | Deferred |
| **Rupbasan** | Rupbasan | Deferred |
| **Biometrik** | Sidik jari / absensi hardware | Deferred; often integration, not pure domain |

---

## Conventions

### Folders & PHP namespaces

```text
app/Modules/Wbp/
app/Modules/Kunjungan/
app/Modules/Mutasi/
…

namespace App\Modules\Wbp\Services;
class WbpService { … }
```

- Folder and PSR-4 segment: **PascalCase** Indonesian term (`Wbp`, `Kunjungan`, not `WBP` folder with mixed styles — acronyms as single Pascal token `Wbp`).
- Register in [`app/Config/Autoload.php`](../app/Config/Autoload.php):
  `'App\Modules\Wbp' => APPPATH . 'Modules/Wbp'`.

### Classes

| Role | Pattern | Example |
|------|---------|---------|
| Facade service | `{Module}Service` | `WbpService`, `KunjunganService` |
| Query service | `{Module}QueryService` | `WbpQueryService` |
| Model | `{Entity}Model` matching table language | `IdentitasModel`, `KunjunganModel` |
| Entity | Domain noun (ID preferred) | `Identitas`, `Kunjungan`, `Perkara` |
| Action | Verb + noun (ID) | `DaftarWbp`, `PembebasanWbp`, `CatatKunjungan` |
| Controller | Plural resource (ID) | `Wbp`, `Kunjungan`, `Remisi` under `Controllers/Api/` |

Module folders, namespaces, services, routes, and permissions use Indonesian names
(as of the pure-rename change). Greenfield **table** names (`inmates`, `visits`, …)
may still be English until the shared-legacy-schema rebase.

### HTTP API paths

Prefer Indonesian resource segments:

```text
/api/v1/wbp
/api/v1/kunjungan
/api/v1/mutasi
/api/v1/remisi
/api/v1/perkara
/api/v1/keswat
/api/v1/fasilitas
/api/v1/referensi
/api/v1/laporan
```

Core auth may stay:

```text
/api/v1/auth/login
/api/v1/users
```

### Permissions

```text
{resource}.{action}
```

Examples: `wbp.read`, `wbp.write`, `kunjungan.write`, `remisi.submit`, `remisi.decide`, `perkara.read`, `mutasi.approve`.

Use the **module resource** name (lowercase), not English (`inmate.*` is retired).

### Config\Services factories

```php
public static function wbpService(bool $getShared = false): \App\Modules\Wbp\Services\WbpService
public static function kunjunganService(bool $getShared = false): \App\Modules\Kunjungan\Services\KunjunganService
public static function mutasiService(bool $getShared = false): \App\Modules\Mutasi\Services\MutasiService
// …
```

### Dependency direction (unchanged topology, new names)

```text
Referensi → Wbp → {Kunjungan, Keswat, Perkara, Remisi} → Mutasi / Laporan
```

- **Thin module template:** `Kunjungan`
- **Grown module template:** `Wbp`
- **Cross-module orchestrator example:** `Mutasi` → `Wbp` facade

### Database tables

**Unchanged** (shared legacy structure). Module names do **not** force table renames:

| Module | Example tables |
|--------|----------------|
| Wbp | `identitas`, … |
| Kunjungan | `kunjungan`, … |
| Perkara | `perkara`, `hukuman`, … |

### Docs language

- Module and API identifiers: **Indonesian** as above.
- Prose in docs may mix EN/ID; first mention can gloss: “modul **Wbp** (warga binaan)”.
- Code comments for domain rules may use Indonesian terms matching legacy (`NOMOR_INDUK`, `ID_PERKARA`).

---

## Migration from English scaffolds

| Former path | Current path |
|-------------|--------------|
| `app/Modules/Inmate` | `app/Modules/Wbp` |
| `app/Modules/Visit` | `app/Modules/Kunjungan` |
| `app/Modules/Transfer` | `app/Modules/Mutasi` |
| `app/Modules/Remission` | `app/Modules/Remisi` |
| `app/Modules/Legal` | `app/Modules/Perkara` |
| `app/Modules/Medical` | `app/Modules/Keswat` |
| `app/Modules/Facility` | `app/Modules/Fasilitas` |
| `app/Modules/MasterData` | `app/Modules/Referensi` |
| `app/Modules/Report` | `app/Modules/Laporan` |

**Done** for code/API surface (folders, namespaces, services, routes, permissions).  
Remaining: map models to **legacy shared tables** (see migration strategy); optional rename of greenfield tables if ever kept.

---

## Out of scope for module rename

- Framework / CI4 English APIs
- HTTP headers that are protocol (`Authorization`, `Accept`)
- Optional technical codes already English in JSON envelopes (`status: success`) — keep envelope stable; **resource names** are Indonesian

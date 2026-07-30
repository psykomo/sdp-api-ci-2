# Registrasi + Mutasi migration progress

| Field | Value |
|-------|--------|
| **As of** | 2026-07-30 |
| **Branch** | `feat/wbp-registrasi-r0-r5-m1` |
| **DB** | Shared legacy `db_sdp` @ `127.0.0.1:3307` (see `.env.example`) |

Living checklist for the Wbp/registrasi epic + M1. Detail lives in per-slice `R0x` / `M01` notes.

## Slice status

| Slice | Status | Notes | Doc |
|-------|--------|-------|-----|
| **R0** Referensi reads | **Done** | jenis_registrasi, lookups, upt | [R01](./R01_IMPLEMENTATION.md) |
| **R1** Identitas reads | **Done** | list/show + org `ID_UPT` scope | [R01](./R01_IMPLEMENTATION.md) |
| **R2** Identitas writes | **Done** | create/update/soft-delete | [R02](./R02_IMPLEMENTATION.md) |
| **R3** Registrasi create | **Done (spine)** | perkara + history + kejahatan + hukuman; MAP flag only | [R03](./R03_IMPLEMENTATION.md) |
| **R4** Registrasi edit | **Done (spine)** | partial update + kej replace + huk upsert + history append | [R04](./R04_IMPLEMENTATION.md) |
| **R5** History R/W | **Done (spine)** | list/show/append/edit/soft-delete under perkara | [R05](./R05_IMPLEMENTATION.md) |
| **R6** Registrasi list/show | **Basic done** | with R4; no rich identitas joins / advanced filters | [R04](./R04_IMPLEMENTATION.md) |
| **R7** Jenis reg admin | **Not started** | optional; R0 GET is enough for forms | inventory |
| **R8** Dokumen | **Waived** | pilot DoD | inventory K25 |
| **M1** Mutasi golongan | **Done (spine)** | options + create + list/show | [M01](./M01_IMPLEMENTATION.md) |
| **M2** Mutasi UPT | **Not started** | after M1 + R3/R4 | strategy |
| **L2 proxy** (CI2 → API) | **Not started** | single-writer cutover for reg + M1 | strategy |

**Legend:** *Done (spine)* = API path works on live schema for pilot fields; not full legacy form parity (ekspirasi engine, multi-level keputusan, SPPT-TI, etc.).

## Smoke

```bash
php spark legacy:smoke-r01 --registrasi   # R0–R6 basic + R5 + M1
cp .env.example .env                      # local shared DB
./scripts/api.sh login
```

## Explicit gaps (not blocking “spine done”)

- Full **RegistrasiMAP** field set / branches  
- Multi-level **keputusan** (PN/PT/MA/PK), **registrasi_a45**, **ekspirasi** engine  
- R3/R4/R5 **feature tests** beyond CLI smoke  
- **Legacy L2 proxies** (registrasi create/edit, history, mutasi golongan)  
- **M2** mutasi UPT  
- R6 polish / R7 only if product needs them  

## Recommended next

1. **L2 proxy** for registrasi + mutasi golongan (epic DoD requires legacy → API), **or**  
2. **M2** mutasi UPT if product prioritizes cross-unit moves  

## Related

- Inventory: [REGISTRASI_INVENTORY.md](./REGISTRASI_INVENTORY.md)  
- Schema: [SCHEMA_CONTRACT.md](./SCHEMA_CONTRACT.md)  
- Strategy: [../MIGRATION_STRATEGY.md](../MIGRATION_STRATEGY.md)  

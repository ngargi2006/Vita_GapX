# Vita-Gap — Knowledge Base Curation & Extension Guide

The Vita-Gap Knowledge Base (KB) serves as the source of truth for all biological pathway definitions, enzyme alternatives, naming aliases, and phenotype classification rules.

---

## 1. KB Source of Truth & Cache Lifecycle

The authoritative KB source file is located at:
```
kb/vitagap_kb.json
```

To achieve sub-millisecond execution speeds under high concurrency without per-request JSON deserialization overhead, Vita-Gap compiles the JSON tree into an executable PHP OPcache array:
```
var/kb_cache.php
```

Whenever `kb/vitagap_kb.json` is edited, you **must recompile the cache** by running:
```bash
php bin/seed.php
```

---

## 2. JSON Schema Architecture

`kb/vitagap_kb.json` adheres to the following root structure:

```json
{
  "kb_version": "1.0.0",
  "kegg_release": "Release 112.0/Oct 2026",
  "vitamins": [
    {
      "code": "B2",
      "name": "Riboflavin",
      "active_forms": "FMN, FAD",
      "summary": "...",
      "pathways": [ ... ],
      "rules": [ ... ],
      "aliases": [ ... ]
    }
  ]
}
```

### 2.1 Pathways (`pathways[]`)
Each pathway defines an enzymatic route:
- `code`: Unique identifier (e.g., `B2_DENOVO`, `B1_TYR`, `K2_FUTALOSINE`).
- `name`: Descriptive biological route title.
- `role`: One of `de_novo`, `precursor`, `activation`, `salvage`, `transport`, `dependency`.
- `kegg_modules`: Associated KEGG module codes (e.g. `["M00125"]`).
- `loci_k`: Number of independent unlinked genomic loci (1 or 2).
- `steps[]`: Ordered pathway reactions.

### 2.2 Steps (`steps[]`)
- `code`: Step identifier (e.g., `B2.GCH2`).
- `name`: Enzyme name.
- `branch`: Track lane identifier (e.g. `pyrimidine`, `ribulose`, `merge`).
- `ec`: Valid Enzyme Commission number (e.g. `3.5.4.25`).
- `kind`:
  - `key`: Committed dedicated step (weight = 2.0).
  - `normal`: Sequential pathway step (weight = 1.0).
  - `low`: Shared multi-pathway step (weight = 0.5).
  - `gap`: Known biological phosphatase gap (excluded from denominator).
  - `activation`: Coenzyme activation step (reported separately).
  - `optional`: Optional salvage or transport step.
- `options[]`: Array of alternative KO definitions. Use `+` for multi-subunit complexes (e.g. `["K01497", "K14652"]` or `["K03148+K03154"]`).

### 2.3 Aliases (`aliases[]`)
- `alias`: Lowercase gene symbol (e.g. `ribA`, `ribC`).
- `ko`: Target KEGG Orthology ID (e.g. `K01497`).
- `naming`: `generic`, `ecoli`, `bsubtilis`, or `other`.
- `verify`: Set to `true` if this alias carries cross-convention ambiguity warnings.

### 2.4 Rules (`rules[]`)
Rules are evaluated top-down; the first rule where all conditions hold wins:
- `call`: Target phenotype call (`PROTOTROPH`, `PROBABLE_PROTOTROPH`, `PRECURSOR_DEPENDENT`, `BLOCKED`, `AUXOTROPH`, `NOT_REQUIRED`, `NOT_ASSESSABLE`, `UNCERTAIN`).
- `if`: Rule condition DSL object:
  - `pathway`: Filter to specific pathway code.
  - `completeness_gte` / `completeness_lte`: Weighted completeness threshold ($C_w$).
  - `key_coverage_eq` / `key_coverage_lte`: Key step coverage ($KC$).
  - `key_missing_lte` / `key_missing_gte`: Missing key step count.
  - `present`: Array of required step codes.
  - `absent`: Array of forbidden step codes.
  - `scope`: `open` or `closed`.
  - `genome_incomplete`: Boolean (`draft`, `mag`, or completeness $< 95\%$).
  - `any_of`: Array of sub-conditions (logical OR).
  - `all_of`: Array of sub-conditions (logical AND).

---

## 3. Verification & Curation Standards

1. **No Hallucinated Identifiers:** Every KO and EC added must be verified against official KEGG release definitions:
   ```bash
   php bin/verify_kegg.php
   ```
2. **Deterministic Thresholds:** All numerical thresholds live strictly in `kb/vitagap_kb.json`, never hard-coded in PHP logic.
3. **Idempotent Seeding:** `bin/seed.php` must run cleanly and idempotently on both MySQL and file-only environments.
4. **Validation Verification:** After any curation change, run the test runner:
   ```bash
   php bin/test.php
   ```

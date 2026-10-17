# Vita-Gap — Master Build Prompt (A → Z)

*Genome-based vitamin pathway gap analysis for bacteria · Spec v1.0 · 14 Oct 2026*
*KEGG module definitions in §8 were checked against kegg.jp on 14 Oct 2026.*

> **How to use this file**
> 1. Create an empty repo and save this file as `docs/VITA-GAP-SPEC.md`.
> 2. Tell your AI coding agent (Claude Code, Cursor, etc.): *"Read docs/VITA-GAP-SPEC.md. Do Phase 0 only, run the checks, then stop and summarize."*
> 3. Continue one phase at a time (§14). Don't start the next phase until its acceptance criteria pass.

---

## 1. Mission (to the AI agent)

You are a senior full-stack engineer and microbial bioinformatician. Build **Vita-Gap**: a website that checks whether a bacterium seems to have the genes needed to make vitamins. For every vitamin the report must answer:

| # | Question | Report section |
|---|---|---|
| Q1 | Which vitamin-making genes were detected? | Detected steps |
| Q2 | Which expected genes are missing from the list? | Missing steps |
| Q3 | How much of the pathway appears complete? | Completeness |
| Q4 | Could the bacterium have difficulty making the vitamin? | Call |
| Q5 | How strong is the evidence for that prediction? | Evidence score + limitations |

Scope: bacteria · vitamins **B1, B2, B3, B5, B6, B7, B9, B12, K2** · input = gene annotations (not raw DNA).

## 2. Biology the code must respect

1. **Gene names are ambiguous across species.** `ribC` is riboflavin synthase in *E. coli* but the riboflavin kinase/FAD synthetase in *B. subtilis*; `ribE` is riboflavin synthase in KEGG/*B. subtilis* naming; `cobS`/`cobT` name different enzymes in the aerobic and late B12 steps. → Match on **KEGG Orthology (KO) IDs**; gene symbols are weaker evidence.
2. **Alternative routes exist.** PLP: PdxA/PdxJ *or* PdxS/PdxT. Biotin pimeloyl moiety: BioC–BioH, BioI or BioW. B12: aerobic *or* anaerobic corrin ring. K2: classical *or* futalosine. → Evaluate every variant; report the best one.
3. **Fused enzymes** cover several steps (RibBA, K14652, = 2 steps).
4. **Known gaps:** some steps are done by unspecific or replaceable enzymes (riboflavin phosphatase, dihydroneopterin-triphosphate phosphatase; IlvC can replace PanE; GapA can replace Epd). → Never penalize them.
5. **"Usage" genes stay in auxotrophs** (RibF, FolC/FolA, CoaA–E, BirA, B12 late steps). → Only **key (committed) steps** decide de novo synthesis.
6. **Absence depends on the input.** A typed partial list cannot prove absence (*open world*). A complete annotation can (*closed world*), discounted for incomplete genomes (MAGs).
7. **Genes ≠ growth.** Expression, regulation and growth conditions are not modelled; every report says so.

## 3. What makes it research-level

- KO-based matching with an **Ambiguity Resolver** (naming convention: Generic/KEGG · *E. coli*-like · *B. subtilis*-like).
- **Variant-aware** logic (alternatives, complexes, fusions) built on KEGG module definitions.
- **Key steps + known-gap registry** → fewer false "missing" alarms.
- **Phenotype classes**, not yes/no: Prototroph · Probable prototroph · Blocked · Precursor-dependent (pABA, HMP, cobinamide…) · Auxotroph (± uptake system) · Not required.
- **Transporter-aware** and **MAG-completeness-aware** evidence.
- **Explainable evidence score** with a per-step breakdown ("why this call?").
- **Community mode ("Vitamin Economy")**: many genomes → heatmap + cross-feeding network.
- **Reproducible reports**: KB + engine version, input SHA-256, parameters, auto-written Methods paragraph, permalink, JSON/CSV export.
- **Validation page** benchmarking calls against KEGG and published predictions.

## 4. Tech stack (fixed; ask before changing)

- **Frontend:** HTML5, CSS custom properties, **vanilla JS ES modules + `fetch`**. Chart.js and Cytoscape.js from cdnjs. No build step, no framework.
- **Backend:** **PHP 8.2+**, no framework, PSR-12, `declare(strict_types=1)`, PDO with prepared statements only. Composer only for PHPUnit (dev).
- **Database:** **MySQL 8** (InnoDB, utf8mb4).
- **Local:** XAMPP/Laragon or `docker-compose` (php:8.3-apache + mysql:8). Deploy on any PHP + MySQL host (static-only hosts cannot run the backend).
- **CLI scripts:** PHP, in `bin/`.

## 5. Architecture & request flow

```
Browser ──fetch POST /api/analyze (JSON)──▶ public/api/index.php (Router)
  1. Tokenizer + FormatDetector   → tokens (KO / EC / symbol / unknown)
  2. Matcher (aliases + naming)   → KO evidence set (+ quality q, ambiguities)
  3. PathwayEvaluator             → per-variant completeness, key coverage, gaps
  4. RuleEngine + EvidenceScorer  → call + evidence score per vitamin
  5. ReportBuilder                → Q1–Q5, limitations, methods, versions
  6. Only if "Save" is ticked     → AnalysisRepository → MySQL → share link
Browser ◀── JSON report ── renders cards, metro maps, tables
```

The knowledge base (KB) source of truth is `kb/vitagap_kb.json`. It is seeded into MySQL (versioned), then the engine loads it once and caches it as a PHP array (`var/kb_cache.php`, OPcache).

## 6. Folder structure

```
vita-gap/
├── public/                      # web root
│   ├── index.html analyze.html report.html compare.html
│   ├── library.html methods.html api.html about.html
│   ├── assets/css/app.css
│   ├── assets/js/ api.js analyze.js report.js metro.js compare.js ui.js
│   └── api/index.php            # front controller
├── src/
│   ├── Http/   Router.php JsonResponse.php RateLimiter.php Validator.php
│   ├── Input/  Tokenizer.php FormatDetector.php Parsers/*.php
│   ├── Engine/ Matcher.php PathwayEvaluator.php RuleEngine.php EvidenceScorer.php ReportBuilder.php
│   ├── Kb/     KbRepository.php KbCache.php
│   └── Db/     Database.php AnalysisRepository.php BatchRepository.php
├── kb/vitagap_kb.json           # curated KB (versioned)
├── db/schema.sql
├── bin/ seed.php verify_kegg.php fetch_demo.php benchmark.php
├── tests/ Unit/ Golden/ fixtures/
├── docs/ VITA-GAP-SPEC.md METHODS.md API.md openapi.yaml KB_CURATION.md VALIDATION.md
├── var/                         # cache (git-ignored)
└── docker-compose.yml .env.example composer.json README.md CITATION.cff LICENSE
```

## 7. Database (MySQL 8)

```sql
CREATE TABLE kb_versions (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(20) NOT NULL UNIQUE, kegg_release VARCHAR(60),
  sha256 CHAR(64) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB;
CREATE TABLE vitamins (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(4) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL, active_forms VARCHAR(80), summary TEXT) ENGINE=InnoDB;
CREATE TABLE pathways (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kb_version_id SMALLINT UNSIGNED NOT NULL,
  vitamin_id TINYINT UNSIGNED NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(160) NOT NULL,
  role ENUM('de_novo','precursor','activation','salvage','transport','dependency') NOT NULL,
  kegg_modules VARCHAR(60), loci_k TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_pw (kb_version_id, code),
  FOREIGN KEY (kb_version_id) REFERENCES kb_versions(id),
  FOREIGN KEY (vitamin_id) REFERENCES vitamins(id)) ENGINE=InnoDB;
CREATE TABLE steps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pathway_id SMALLINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL, pos TINYINT UNSIGNED NOT NULL, branch VARCHAR(30),
  name VARCHAR(120) NOT NULL, ec VARCHAR(60),
  kind ENUM('key','normal','low','gap','activation','optional') NOT NULL DEFAULT 'normal',
  note TEXT, UNIQUE KEY uq_step (pathway_id, code),
  FOREIGN KEY (pathway_id) REFERENCES pathways(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE step_options (               -- one row per alternative; '+' = complex
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, step_id INT UNSIGNED NOT NULL,
  kos VARCHAR(80) NOT NULL, label VARCHAR(80),
  FOREIGN KEY (step_id) REFERENCES steps(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE ko_terms (ko CHAR(6) PRIMARY KEY, symbol VARCHAR(60), definition VARCHAR(255),
  ec VARCHAR(80), verified_at DATETIME) ENGINE=InnoDB;
CREATE TABLE gene_aliases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alias VARCHAR(40) NOT NULL, ko CHAR(6) NOT NULL,
  naming ENUM('generic','ecoli','bsubtilis','other') NOT NULL DEFAULT 'generic',
  source VARCHAR(80), INDEX ix_alias (alias)) ENGINE=InnoDB;
CREATE TABLE refs (id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, short_ref VARCHAR(60),
  citation TEXT, doi VARCHAR(120)) ENGINE=InnoDB;
CREATE TABLE analyses (
  id CHAR(26) PRIMARY KEY, share_token CHAR(32) NOT NULL UNIQUE, delete_hash CHAR(64) NOT NULL,
  created_at DATETIME(3) NOT NULL, expires_at DATETIME NULL,
  kb_version_id SMALLINT UNSIGNED NOT NULL, engine_version VARCHAR(20) NOT NULL,
  organism VARCHAR(160), genome_type ENUM('complete','draft','mag','unknown') NOT NULL,
  completeness DECIMAL(5,2) NULL, input_scope ENUM('closed','open') NOT NULL,
  input_format VARCHAR(30), input_sha256 CHAR(64) NOT NULL,
  input_text MEDIUMTEXT NULL,             -- only if the user ticks "store my input"
  params JSON, report JSON NOT NULL,
  FOREIGN KEY (kb_version_id) REFERENCES kb_versions(id)) ENGINE=InnoDB;
CREATE TABLE analysis_results (
  analysis_id CHAR(26) NOT NULL, vitamin_code VARCHAR(4) NOT NULL, best_pathway VARCHAR(40),
  completeness DECIMAL(5,4), key_coverage DECIMAL(5,4), call_code VARCHAR(30),
  evidence TINYINT UNSIGNED, PRIMARY KEY (analysis_id, vitamin_code),
  FOREIGN KEY (analysis_id) REFERENCES analyses(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE batches (id CHAR(26) PRIMARY KEY, share_token CHAR(32) UNIQUE, delete_hash CHAR(64),
  name VARCHAR(120), created_at DATETIME(3)) ENGINE=InnoDB;
CREATE TABLE batch_members (batch_id CHAR(26), analysis_id CHAR(26),
  PRIMARY KEY (batch_id, analysis_id)) ENGINE=InnoDB;
CREATE TABLE rate_limits (ip_hash CHAR(64), window_start DATETIME, hits INT UNSIGNED,
  PRIMARY KEY (ip_hash, window_start)) ENGINE=InnoDB;
```

## 8. Knowledge base (curated pathway checklist)

**Kind:** **K** key · N normal · L low-specificity (shared with other pathways, weight 0.5) · G known gap (never penalized) · A activation/usage (reported separately, never proves synthesis). `/` = alternatives (OR) · `+` = complex (AND).

### B1 Thiamine → TPP (M00127 ThiH/tyrosine route · M00895 ThiO/glycine route · salvage M00899)

| Step | KO options | Kind |
|---|---|---|
| ThiF + ThiS (sulfur carrier) | K03148+K03154 | L |
| ThiI | K03151 | L |
| ThiH (tyrosine) / ThiO (glycine) | K03150 / K03153 | N |
| ThiG thiazole synthase | K03149 | **K** |
| TenI (ThiO route only) | K10810 | N |
| ThiC HMP-P synthase | K03147 | **K** |
| ThiD HMP(-P) kinase | K00941 / K14153 / K21219 | N |
| ThiE TMP synthase | K00788 / K14153 / K21219 / K21220 | **K** |
| ThiL TMP kinase | K00946 | A |

Salvage: ThiM K00878 (HET kinase), ThiK K07251, TPK K00949. Uptake: ThiT K16789; ThiBPQ K02064+K02063+K02062. Branches: thiazole, pyrimidine (ThiC, ThiD), coupling.

### B2 Riboflavin → FMN/FAD (M00125)

| Step | KO options | Kind |
|---|---|---|
| GTP cyclohydrolase II (RibA) | K01497 / K14652 | **K** |
| Deaminase (RibD) | K01498 / K11752 | N |
| Reductase (RibD) | K00082 / K11752 | N |
| ArPP phosphatase | K22912 / K20860 / K20861 / K20862 / K21063 / K21064 | G |
| DHBP synthase (RibB) | K02858 / K14652 | **K** |
| Lumazine synthase (RibH) | K00794 | **K** |
| Riboflavin synthase | K00793 | **K** |
| Riboflavin kinase + FAD synthetase | K11753 / (K20884 + K22949) | A |

Uptake: RibU/FmnP K23675; RfuABCD K23510+K23511+K23512+K23513. KEGG lists *E. coli* K-12 (eco), *B. subtilis* 168 (bsu) and *B. thetaiotaomicron* (bth) as complete for M00125 → positive controls.

### B3 NAD (M00115 aspartate route · M00912 tryptophan/kynurenine route)

| Step | KO options | Kind |
|---|---|---|
| Aspartate oxidase / dehydrogenase (NadB) | K00278 / K06989 | **K** |
| Quinolinate synthase (NadA) | K03517 | **K** |
| *Variant:* Trp → quinolinate (TDO/IDO → formamidase → KMO → kynureninase → 3-HAO) | K00453/K00463 → K01432/K14263/K07130 → K00486 → K01556 → K00452 | **K** |
| NadC | K00767 | **K** |
| NadD (or NadM) | K00969 / K06210 | A |
| NadE | K01916 / K01950 | A |

Salvage (add KOs at seed time via KEGG `find`): PncA, PncB, NadV, NadR, PnuC → enables the "niacin/NR-dependent" call.

### B5 Pantothenate → CoA (M00119 · CoA M00120)

| Step | KO options | Kind |
|---|---|---|
| BCAT (IlvE) | K00826 | L |
| PanB | K00606 | **K** |
| PanE (IlvC can substitute) | K00077 | G |
| PanD | K01579 | N |
| PanC | K01918 / K13799 | **K** |
| CoA from pantothenate: PanK K00867/K03525/K09680/K01947 → CoaBC K13038 (or K01922/K21977 + K01598) → CoaD K00954/K02201 → CoaE K00859 (COASY K02318) | — | A |

Uptake: PanF (add KO at seed time).

### B6 Pyridoxal 5′-phosphate (variant A M00124 · variant B M00916)

| Variant | Steps (KO) |
|---|---|
| A (DXP-dependent) | Epd K03472 (G) · PdxB K03473 (N) · SerC K00831 (L) · PdxA K00097 (**K**) · PdxJ K03474 (**K**) · PdxH K00275 (A) |
| B (DXP-independent) | PdxS + PdxT K06215+K08681 (**K**) |

Salvage: PdxK/PdxY (add at seed time).

### B7 Biotin (core M00123 · pimeloyl routes M00572, M00573, M00577 · BioU M00950)

| Step | KO options | Kind |
|---|---|---|
| BioF | K00652 | **K** |
| BioA / BioK / BioU (or fused) | K00833 / K19563 / K25570 / K19562 | **K** |
| BioD | K01935 / K19562 | **K** |
| BioB | K01012 | **K** |
| Pimeloyl moiety (any one route) | BioC K02169 + BioH-type K02170/K09789/K19560/K19561 · or BioI K16593 · or BioW K01906 | precursor branch |

Usage: BirA K03524. Uptake: BioY K03523 (+ BioM K16784, BioN K16783). Core complete but no pimeloyl route found → Probable prototroph (some pimeloyl routes are still undescribed).

### B9 Folate / THF (M00126; the pABA branch is not in the module)

| Step | KO options | Kind |
|---|---|---|
| GTP cyclohydrolase I (FolE / FolE2) | K01495 / K09007 / K22391 | **K** |
| Dihydroneopterin-TP phosphatase | K01077 / K01113 / K08310 / K19965 | G |
| FolB aldolase (or PTPS-III) | K01633 / K22101 (fused: K13940, K13939) | N |
| FolK (HPPK) | K00950 (fused: K13940, K13941, K13939) | **K** |
| FolP (DHPS) | K00796 (fused: K13941, K13939) | **K** |
| FolC | K11754 / K20457 | A |
| FolA (DHFR) | K00287 / K13998 | A |
| pABA branch: PabA + PabB (or fused), PabC | add at seed via KEGG `find/ko/aminodeoxychorismate` | **K** (pABA) |

### B12 Cobamide (M00924 anaerobic · M00925 aerobic · M00122 late steps)

| Block | Steps (KO options) | Kind |
|---|---|---|
| Early (shared with siroheme) | CysG K02302, or SUMT K02303/K13542 + precorrin-2 DH K02304/K24866 | L |
| Corrin ring, anaerobic | CbiK/X K02190/K03795/K22011 · CbiL K03394 · CbiH K05934/K13541/K21479 · CbiF K05936 · CbiG K02189/K13541 · CbiD K02188 · CbiJ K05895 · CbiT + CbiE K02191, K03399 (or K00595) · CbiC K06042 · CbiA K02224 | **K** |
| Corrin ring, aerobic | CobI K03394/K13540 · CobG K02229 · CobJ K05934/K13540/K13541 · CobM K05936 · CobF K02228 · CobK K05895 · CobL K00595 · CobH K06042 · CobB K02224 · CobNST K02230+K09882+K09883 | **K** |
| Late / nucleotide loop | Adenosyltransferase K00798/K19221 · CobQ K02232 · AdoCbi-P synthase step K02225/K02227 · CobU K02231 · CobT K00768 · α-ribazole-P phosphatase K02226/K22316 · CobS K02233 | precursor branch (kept by salvagers) |

Uptake: BtuF + BtuC + BtuD K25034+K25027+K25028 (+ BtuB, add at seed). **Dependence markers** (add KOs at seed): MetH, NrdJ, methylmalonyl-CoA mutase, EutBC, PduCDE, QueG. Lower-ligand (DMB) genes are optional: report "lower-ligand route not found" as a limitation, not a block.

### K2 Menaquinone (M00116 classical · M00930 futalosine · M00931 modified futalosine)

| Variant | Steps (KO) |
|---|---|
| Classical | MenF K02552 (N) · MenD K02551 (**K**) · MenH K08680 (N) · MenC K02549 (N) · MenE K01911 (N) · MenB K01661 (**K**) · MenI K19222 (G) · MenA K02548 (**K**) · UbiE/MenG K03183 (L) |
| Futalosine | MqnA K11782 (**K**) · MqnE K18285 (N) · deaminase K18286/K20810 (N) · MqnB K11783 (N) · MqnC K11784 (**K**) · MqnD K11785 (**K**) |
| Modified futalosine | MqnA K11782 · MqnE K18285 · nucleosidase K18284 · MqnC K11784 · MqnD K11785 |

No K2 pathway → **Not required** ("may use ubiquinone or not respire"), never "auxotroph".

### Ambiguity seed table (extend it from KEGG)

| Symbol | Meaning (naming convention) | KO |
|---|---|---|
| ribA | GTP cyclohydrolase II (generic/KEGG, *E. coli*) | K01497 |
| ribA | bifunctional RibBA (*B. subtilis*) | K14652 |
| ribC | riboflavin synthase (*E. coli*) | K00793 |
| ribC | riboflavin kinase/FAD synthetase (*B. subtilis*) | K11753 |
| ribE | riboflavin synthase (generic/KEGG, *B. subtilis*) | K00793 |
| ribE | lumazine synthase (*E. coli*, also called ribH) ⚑ | K00794 |
| cobS | cobalamin synthase (late steps) | K02233 |
| cobS | cobaltochelatase subunit (aerobic) | K09882 |
| cobT | DMB phosphoribosyltransferase (late steps) | K00768 |
| cobT | cobaltochelatase subunit (aerobic) | K09883 |
| cobA | uroporphyrinogen-III methyltransferase (aerobic B12) | K02303 |
| cobA | corrinoid adenosyltransferase (*Salmonella*) ⚑ | K19221 |

⚑ = confirm at seed time. Paralog warnings (symbol-only matches get lower q): thiF↔moeB, thiS↔moaD, panE↔ilvC.

### KB JSON format (complete example: B2)

```json
{
  "kb_version": "1.0.0",
  "vitamins": [
    {
      "code": "B2",
      "name": "Riboflavin",
      "active_forms": "FMN, FAD",
      "pathways": [
        {
          "code": "B2_DENOVO",
          "name": "Riboflavin biosynthesis (bacteria)",
          "role": "de_novo",
          "kegg_modules": ["M00125"],
          "loci_k": 1,
          "steps": [
            {"code": "B2.GCH2", "branch": "pyrimidine", "name": "GTP cyclohydrolase II", "ec": "3.5.4.25", "kind": "key", "options": ["K01497", "K14652"]},
            {"code": "B2.DEAM", "branch": "pyrimidine", "name": "Pyrimidine deaminase", "ec": "3.5.4.26", "kind": "normal", "options": ["K01498", "K11752"]},
            {"code": "B2.RED", "branch": "pyrimidine", "name": "Pyrimidine reductase", "ec": "1.1.1.193", "kind": "normal", "options": ["K00082", "K11752"]},
            {"code": "B2.PHOS", "branch": "pyrimidine", "name": "ArPP phosphatase", "ec": "3.1.3.104", "kind": "gap", "options": ["K22912", "K20860", "K20861", "K20862", "K21063", "K21064"], "note": "Often unannotated; never penalize."},
            {"code": "B2.DHBPS", "branch": "ribulose", "name": "DHBP synthase", "ec": "4.1.99.12", "kind": "key", "options": ["K02858", "K14652"]},
            {"code": "B2.LS", "branch": "merge", "name": "Lumazine synthase", "ec": "2.5.1.78", "kind": "key", "options": ["K00794"]},
            {"code": "B2.RS", "branch": "merge", "name": "Riboflavin synthase", "ec": "2.5.1.9", "kind": "key", "options": ["K00793"], "alternatives": []}
          ]
        },
        {
          "code": "B2_ACTIVATION",
          "name": "Riboflavin to FMN to FAD",
          "role": "activation",
          "steps": [
            {"code": "B2.RFK", "name": "Riboflavin kinase", "ec": "2.7.1.26", "kind": "activation", "options": ["K11753", "K20884"]},
            {"code": "B2.FADS", "name": "FMN adenylyltransferase", "ec": "2.7.7.2", "kind": "activation", "options": ["K11753", "K22949"]}
          ]
        },
        {
          "code": "B2_TRANSPORT",
          "name": "Riboflavin uptake",
          "role": "transport",
          "steps": [
            {"code": "B2.T1", "name": "RibU/FmnP (ECF)", "kind": "optional", "options": ["K23675"]},
            {"code": "B2.T2", "name": "RfuABCD (ABC)", "kind": "optional", "options": ["K23510+K23511+K23512+K23513"]}
          ]
        }
      ],
      "rules": [
        {"id": "B2.R1", "call": "PROTOTROPH", "if": {"pathway": "B2_DENOVO", "completeness_gte": 0.85, "key_coverage_eq": 1}},
        {"id": "B2.R2", "call": "PROBABLE_PROTOTROPH", "if": {"pathway": "B2_DENOVO", "completeness_gte": 0.6, "key_missing_lte": 1, "any_of": [{"missing_key_has_alternative": true}, {"genome_incomplete": true}]}},
        {"id": "B2.R3", "call": "BLOCKED", "if": {"pathway": "B2_DENOVO", "completeness_gte": 0.4, "key_missing_gte": 1}},
        {"id": "B2.R4", "call": "AUXOTROPH", "if": {"pathway": "B2_DENOVO", "key_coverage_lte": 0.25, "scope": "closed"}},
        {"id": "B2.R5", "call": "NOT_ASSESSABLE", "if": {"scope": "open", "matched_steps_eq": 0}},
        {"id": "B2.R6", "call": "UNCERTAIN", "if": {}}
      ],
      "aliases": [
        {"alias": "ribA", "ko": "K01497", "naming": "generic"},
        {"alias": "ribA", "ko": "K14652", "naming": "bsubtilis"},
        {"alias": "ribBA", "ko": "K14652", "naming": "generic"},
        {"alias": "ribB", "ko": "K02858", "naming": "generic"},
        {"alias": "ribD", "ko": "K11752", "naming": "generic"},
        {"alias": "ribH", "ko": "K00794", "naming": "generic"},
        {"alias": "ribE", "ko": "K00793", "naming": "generic"},
        {"alias": "ribE", "ko": "K00794", "naming": "ecoli", "verify": true},
        {"alias": "ribC", "ko": "K00793", "naming": "ecoli"},
        {"alias": "ribC", "ko": "K11753", "naming": "bsubtilis"},
        {"alias": "ribF", "ko": "K11753", "naming": "generic"},
        {"alias": "ribU", "ko": "K23675", "naming": "generic"},
        {"alias": "fmnP", "ko": "K23675", "naming": "generic"}
      ]
    }
  ]
}
```

**Rule DSL keys:** `pathway`, `completeness_gte`, `key_coverage_eq/lte`, `key_missing_lte/gte`, `missing_key_has_alternative`, `genome_incomplete` (draft, MAG or completeness < 95 %), `scope` (`open`/`closed`), `matched_steps_eq`, `present` / `absent` (step codes), `branch_complete` / `branch_absent`, `any_of`, `all_of`. Rules run top-down; first match wins. All thresholds live in the KB, never in PHP.

## 9. Analysis engine (exact behaviour)

### 9.1 Inputs

| Format | How to read it |
|---|---|
| Plain list (pasted) | split on newlines, commas, semicolons, tabs, spaces and arrows (`→ -> => ⟶`); case-insensitive |
| KO list | one `Kxxxxx` per line |
| KofamScan | `-f mapper` (gene ⇥ KO) or detail-tsv (use only lines starting with `*`) |
| BlastKOALA / GhostKOALA | `user_ko.txt` (gene ⇥ KO; blank KO = skip) |
| eggNOG-mapper v2 `.emapper.annotations` | skip `#` lines; use `Preferred_name`, `EC`, `KEGG_ko` (`ko:K00794,…`) |
| Prokka `.tsv` | columns `gene`, `EC_number` |
| Generic CSV | detect header columns for gene / ko / ec |

Auto-detect the format (user can override). Limits: 5 MB, 50,000 lines. Also ask for: organism name; naming convention; genome type (complete / draft / MAG + CheckM completeness %); **input scope** (complete annotation = closed world, or partial list = open world).

### 9.2 Matching & match quality *q*

Token classes: KO `^K\d{5}$` · EC `^(EC:?)?\d+\.\d+\.\d+\.\d+$` · gene symbol (alias table) · unknown (offer "did you mean…" for edit distance ≤ 1; never auto-apply).

Symbol resolution: use the entry for the chosen naming convention; else a single `generic` entry; else mark **ambiguous** and ask the user (Ambiguity Resolver).

| Evidence | q |
|---|---|
| KO from KofamScan above threshold | 1.0 |
| KO from KOALA / eggNOG / KO list | 0.9 |
| Unambiguous symbol, or EC unique to one step | 0.8 |
| Alias / old name | 0.7 |
| Symbol whose meaning differs in another convention (warning chip) | 0.6 |
| EC shared by several steps | 0.5 |
| Ambiguous and unresolved | not used until resolved |

A step is present if any option is fully present. A complex needs all subunits (a partial complex counts 0.5 and is flagged).

### 9.3 Completeness (per pathway variant)

Weights: K = 2, N = 1, L = 0.5. G and A steps are excluded and shown separately.

- **C_w** = Σ(w·p) / Σw (weighted completeness, shown as %)
- **C_u** = present steps / total steps (also shown)
- **KC** = key steps present / key steps total

Best variant = highest C_w (tie → higher KC).

### 9.4 Calls (rule engine; first match wins)

| Code | Simple label | Default rule |
|---|---|---|
| PROTOTROPH | Can make it | C_w ≥ 0.85 and KC = 1 |
| PROBABLE_PROTOTROPH | Probably can make it | C_w ≥ 0.60, ≤ 1 key step missing, and that step has a documented alternative **or** the genome is incomplete |
| PRECURSOR_DEPENDENT | Needs a precursor | vitamin-specific rules (below) |
| BLOCKED | Blocked at a key step — check this gene | C_w ≥ 0.40 and ≥ 1 key step missing |
| AUXOTROPH | Probably needs it from its surroundings | KC ≤ 0.25, **closed world only**; add "· uptake system found" if a transporter matched |
| NOT_REQUIRED | Doesn't seem to need it | B12: no dependence markers · K2: no pathway |
| NOT_ASSESSABLE | Not enough data | open world and nothing matched |
| UNCERTAIN | Unclear | everything else |

Precursor rules: **B1** HMP-dependent (ThiC absent; ThiG + ThiE present) / HET-dependent (ThiG absent; ThiC + ThiE + ThiM present) · **B3** niacin/NR-dependent (no quinolinate route; NadD + NadE + PncB/NadV/NadR present) · **B7** desthiobiotin-dependent (BioB only) · **B9** pABA-dependent (pterin branch + FolP present, pABA branch absent) · **B12** cobinamide salvager (corrin KC ≤ 0.25, late block C_w ≥ 0.85).

In open world, BLOCKED is worded "not found in your list", and AUXOTROPH / NOT_REQUIRED are never issued.

### 9.5 Evidence score (0–100) → answers Q5

- **E_found** = weighted mean *q* over found steps in the best variant
- **a** (absence reliability) = complete genome 1.0 · draft 0.9 · MAG = CheckM completeness (0–1) · partial list 0
- **a_path** = 1 − (1 − a)^k, where k = independent gene loci for that pathway (KB `loci_k`: 1 for rib, bio, pan, pdxST; 2 for thi, nad, fol, pdxA/J, cob, men)

| Call | Evidence |
|---|---|
| PROTOTROPH, PROBABLE_PROTOTROPH | 100 · E_found · C_w |
| BLOCKED, PRECURSOR_DEPENDENT | 100 · E_found · a |
| AUXOTROPH, NOT_REQUIRED | 100 · a_path (+10 if an uptake system is found; cap 100) |

Tiers: **Strong ≥ 75 · Moderate 50–74 · Weak < 50**. Always show the per-step contribution table.

### 9.6 Auto-generated limitations (examples)

- Partial list → "Missing = not in your list, not proven absent."
- MAG → "About X % chance the whole pathway sits in unassembled DNA" (X = 100·(1 − c)^k).
- Symbols only → "Gene names differ between species; KO IDs are more reliable."
- Always → "Known-gap steps are not counted. Genes show potential, not expression or growth."

### 9.7 Worked example (the original note) = golden test #1

Input (partial list, generic naming): `RibA → RibB → RibD → RibH`

- **Found:** GTP cyclohydrolase II (K01497), DHBP synthase (K02858), deaminase + reductase (K11752), lumazine synthase (K00794)
- **Missing:** riboflavin synthase (K00793, KEGG name *ribE*) — a key step. Phosphatase = known gap (not counted). RibF = activation (reported separately).
- **C_u** = 5/6 = 83 % · **C_w** = 8/10 = 80 % · **KC** = 3/4
- **Call:** BLOCKED — "Riboflavin synthase (ribE) not found in your list"
- **Evidence:** 100 · 0.8 · 0 = **0 → Weak** (partial list cannot prove absence)
- Same genes from a complete genome annotation: evidence 100 · 0.8 · 1.0 = **80 → Strong**

## 10. API (JSON over HTTPS)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/health` | status + engine/KB versions |
| GET | `/api/vitamins` | vitamins and pathways |
| GET | `/api/pathways/{code}` | steps, KOs, notes, references |
| GET | `/api/lookup?q=ribE&naming=ecoli` | gene/KO/EC lookup incl. ambiguity |
| POST | `/api/parse` | dry run: tokens, matches, unknown, ambiguous |
| POST | `/api/analyze` | full report (not saved) |
| POST | `/api/analyses` | save → `share_token` + `delete_token` |
| GET | `/api/analyses/{share_token}` | load a saved report |
| DELETE | `/api/analyses/{share_token}` | requires `delete_token` |
| POST | `/api/batch` | many genomes → matrix + network |
| GET | `/api/export/{share_token}.json` (or `.csv`) | downloads |

Envelope: `{"ok": true, "data": {}, "error": null, "meta": {"request_id": "", "engine_version": "1.0.0", "kb_version": "1.0.0", "ms": 42}}`. Error codes: `INVALID_INPUT`, `AMBIGUOUS_TOKENS`, `TOO_LARGE`, `RATE_LIMITED`, `NOT_FOUND`, `SERVER_ERROR` (with matching HTTP status).

Request example:

```json
{
  "organism": "Bacterium X",
  "naming": "generic",
  "genome_type": "unknown",
  "completeness": null,
  "scope": "open",
  "format": "auto",
  "data": "RibA -> RibB -> RibD -> RibH",
  "vitamins": ["B2"],
  "resolve": {}
}
```

Per-vitamin report object: `vitamin, best_pathway, completeness {weighted, unweighted, key}, steps [{code, name, ec, kind, status, matched [{token, ko, q}], alternatives, verify_hint}], activation, transport, call {code, label, rule_id, reasons[]}, evidence {score, tier, contributions[]}, limitations[]`. Global `methods`: versions, parameters, input SHA-256 and a ready-to-paste Methods paragraph.

## 11. UI / UX

**Pages:** Home · Analyze (wizard: Organism → Input → Options → Review & resolve ambiguities → Run) · Report · Compare (community) · Pathway Library · Methods & Validation · API · About/Help.

**Report layout**
1. Header: organism, input type, scope badge (Partial list / Complete annotation), versions, Save · Share · Export.
2. **Vitamin barcode:** 9 cells coloured by call (icon + text, never colour alone).
3. Vitamin cards: call chip, completeness ring, evidence tier, one-line reason.
4. Per-vitamin detail with a **metro-map pathway view** (signature visual): lanes = branches, stations = steps; ● found, ○ missing, dashed = known gap; branches visibly merge (e.g., riboflavin's two branches meet at lumazine synthase). Clicking a station opens a panel: KO/EC, matched token, q, alternatives, how to verify (BLAST the reference protein from the KEGG KO page against the genome; check contig ends and pseudogenes) and a wet-lab check (growth in vitamin-free defined medium ± the vitamin). Then the Q1–Q5 boxes, the step table, the evidence breakdown and the limitations.
5. Methods box with a copy button.

**Compare page:** heatmap (genome × vitamin), cross-feeding network in Cytoscape.js (edge = producer → auxotroph of the same vitamin; thicker if the receiver has an uptake system), community coverage table ("does at least one member make each vitamin?"), CSV/GraphML export.

**Simple ↔ Expert toggle:** Expert shows KO, EC, q and rule IDs.

**Design tokens** (Okabe–Ito, colour-blind-safe): Prototroph `#009E73` · Probable `#56B4E9` · Blocked `#E69F00` · Precursor `#CC79A7` · Auxotroph `#D55E00` · Not required `#0072B2` · Uncertain / Not assessable `#8A8A8A`. Light and dark themes (`prefers-color-scheme` + toggle), system font stack, monospace for IDs, mobile-first from 360 px (tables become cards), WCAG 2.1 AA, `prefers-reduced-motion`.

**Empty state demos:** "Note example (riboflavin)", *E. coli* K-12, *B. subtilis* 168, *M. genitalium*.

## 12. Security, privacy, performance

- PDO prepared statements only; validate every field; size limits; reject binaries; parse uploads in memory and never execute them.
- JS renders user text with `textContent` (never `innerHTML`); PHP escapes any server HTML.
- Headers: CSP (self + cdnjs), `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `frame-ancestors 'none'`.
- Rate limit 60 analyses/hour per salted IP hash; batch ≤ 50 genomes.
- Saving is opt-in. Tokens = `bin2hex(random_bytes(16))`; the delete token is stored hashed; raw input is stored only if the user ticks "store my input". Unsaved analyses leave nothing in the DB.
- Secrets in `.env` (git-ignored); no stack traces in production.
- Speed targets: 5,000-gene genome < 300 ms server time; 50-genome batch < 10 s.
- KEGG REST is free for academic use: store only IDs + your own short descriptions, link to KEGG pages, don't redistribute KEGG flat files.

## 13. Testing & validation

- **Unit (PHPUnit):** tokenizer (arrows, mixed separators, Unicode), every parser (fixtures in `tests/fixtures/`), alias + ambiguity resolution, complexes/fusions, completeness maths, every rule, evidence maths.
- **Golden tests (JSON snapshots):**
  1. The note example (§9.7), open and closed world.
  2. Same list + `ribE` → all key steps present.
  3. `ribC`: generic → ambiguous; *E. coli* naming → K00793; *B. subtilis* naming → K11753.
  4–6. Real KO sets for `eco`, `bsu`, `mge` fetched by `bin/fetch_demo.php` from `https://rest.kegg.jp/link/ko/<org>`, cross-checked with `https://rest.kegg.jp/link/module/<org>`. *E. coli* K-12 must **not** get a de novo B12 call; eco, bsu and bth must be PROTOTROPH for B2.
- API contract tests; security tests (SQL injection / XSS in the organism name, oversized file, wrong file type); Lighthouse accessibility ≥ 90.
- **Research validation (Phase 6):** 30–50 genomes that are in KEGG and have published predictions (Rodionov et al. 2019 phenotype matrix; Soto-Martin et al. 2020 growth data where available). Report accuracy, MCC and Cohen's κ per vitamin, confusion matrices, and every disagreement with its likely reason, on the Methods & Validation page.

## 14. Build phases & acceptance criteria

| Phase | Deliverables | Done when |
|---|---|---|
| 0 Setup | skeleton, docker-compose, `.env.example`, `/api/health`, README stub | health returns versions; PHPUnit runs |
| 1 Knowledge base | `kb/vitagap_kb.json` (9 vitamins), `db/schema.sql`, `bin/seed.php`, `bin/verify_kegg.php` | every KO checked via `https://rest.kegg.jp/list/<KO>` (report in `docs/kb_verification.md`); unknowns flagged, nothing invented; seeding is idempotent |
| 2 Engine | tokenizer, parsers, matcher, evaluator, rule engine, evidence, report builder (pure PHP, no HTTP) | unit tests + golden tests 1–3 pass |
| 3 API + MySQL | endpoints, envelope, validation, rate limit, save/share/delete | contract tests pass; `docs/API.md` + `openapi.yaml` |
| 4 Frontend | all pages, wizard, resolver, report, metro map, exports, print CSS | note example end-to-end in ≤ 3 clicks; works at 360 px; Lighthouse a11y ≥ 90 |
| 5 Community | batch upload, heatmap, network, coverage | 10-genome batch < 5 s; GraphML opens in Cytoscape |
| 6 Validation | demo fetch, golden tests 4–6, benchmark, Methods & Validation page | metrics + disagreement list published; thresholds documented |
| 7 Polish & deploy | METHODS, KB_CURATION, VALIDATION docs, CITATION.cff, LICENSE, deployment | fresh clone → running app in < 10 min using the README |

## 15. Rules for the AI agent

1. Never invent KO IDs, EC numbers, gene names or references. If unsure, add `"verify": true` and a TODO, and tell me.
2. The KB is data (JSON), not hard-coded PHP.
3. Keep the stack in §4; ask before adding anything.
4. Write tests before engine code; run all tests at the end of every phase.
5. Stop after each phase and report: what changed, test results, open questions.
6. Small, typed, documented functions; no global state; clear error messages.
7. Every statement in a report must trace back to a step, a rule ID or a limitation.
8. Wording: "the genome suggests…", "not detected…". Never "cannot" or "proven".

## 16. Key references (check DOIs before citing)

1. Kanehisa M, Goto S. KEGG: Kyoto Encyclopedia of Genes and Genomes. *Nucleic Acids Res.* 2000;28:27–30.
2. Rodionov DA, et al. Micronutrient requirements and sharing capabilities of the human gut microbiome. *Front Microbiol.* 2019;10:1316.
3. Magnúsdóttir S, et al. Systematic genome assessment of B-vitamin biosynthesis suggests co-operation among gut microbes. *Front Genet.* 2015;6:148.
4. Shelton AN, et al. Uneven distribution of cobamide biosynthesis and dependence in bacteria predicted by comparative genomics. *ISME J.* 2019;13:789–804.
5. Soto-Martin EC, et al. Vitamin biosynthesis by human gut butyrate-producing bacteria and cross-feeding in synthetic microbial communities. *mBio.* 2020;11:e00886-20.
6. Price MN, Deutschbauer AM, Arkin AP. GapMind: automated annotation of amino acid biosynthesis. *mSystems.* 2020;5:e00291-20.
7. Zimmermann J, Kaleta C, Waschina S. gapseq: informed prediction of bacterial metabolic pathways and reconstruction of accurate metabolic models. *Genome Biol.* 2021;22:81.
8. Aramaki T, et al. KofamKOALA: KEGG Ortholog assignment based on profile HMM and adaptive score threshold. *Bioinformatics.* 2020;36:2251–2252.
9. Chklovski A, et al. CheckM2: a rapid, scalable and accurate tool for assessing microbial genome quality using machine learning. *Nat Methods.* 2023;20:1203–1212.
10. Green ML, Karp PD. A Bayesian method for identifying missing enzymes in predicted metabolic pathway databases. *BMC Bioinformatics.* 2004;76.

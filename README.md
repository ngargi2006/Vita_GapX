# Vita-Gap

**Genome-based vitamin pathway gap analysis for bacteria**  
*Spec v1.0 · Version 1.0.0 · October 2026*

Vita-Gap is a research-grade bioinformatic platform that evaluates whether a bacterial genome possesses the genetic repertoire required for de novo biosynthesis across 9 essential cofactors: **B1 (Thiamine), B2 (Riboflavin), B3 (Niacin / NAD), B5 (Pantothenate), B6 (Pyridoxal 5′-phosphate), B7 (Biotin), B9 (Folate), B12 (Cobalamin), and K2 (Menaquinone)**.

---

## Key Features

- **Ambiguity-Aware Symbol Matching**: Resolves conflicting gene symbols across species traditions (e.g. `ribA`, `ribC`, `ribE`, `cobS`, `cobT`) anchored on KEGG Orthology (KO) IDs with paralog collision warnings.
- **Variant-Aware Pathway Models**: Evaluates alternative routes (e.g. classical vs. futalosine menaquinone; aerobic vs. anaerobic B12; DXP-dependent vs. independent PLP), multi-subunit complexes, and bifunctional fusions (e.g. RibBA).
- **Nuanced Phenotype Classes**:
  - `PROTOTROPH`: Autonomous de novo synthesis.
  - `PROBABLE_PROTOTROPH`: High completeness or documented bypass.
  - `PRECURSOR_DEPENDENT`: Requires precursors (pABA, HMP, cobinamide).
  - `BLOCKED`: Stalled at a specific committed lesion.
  - `AUXOTROPH`: Lacks pathway (flagged with active transport machinery if present).
  - `NOT_REQUIRED`: Independent of cofactor (e.g. non-respiratory or B12-independent).
- **Calibrated Evidence Scoring (0–100)**: Transparent score discounted for open-world lists ($a = 0$) and incomplete draft assemblies / MAGs ($a_{\text{path}} = 1 - (1 - a)^k$).
- **Signature Metro-Map Visualizations**: Interactive SVG pathway diagrams tracing metabolic branches, convergence junctions, and subunit complexes with in silico BLAST advice and wet-lab growth validation protocols.
- **Community Mode ("Vitamin Economy")**: Batch analysis of up to 50 genomes displaying Genome $\times$ Vitamin autonomy heatmaps, community-level sufficiency metrics, and directed cross-feeding network graphs (Cytoscape.js and GraphML export).

---

## Quickstart (< 10 Minutes)

### Prerequisites
- PHP 8.2+ with `pdo`, `pdo_mysql`, `curl`, `json` extensions (or Docker)
- Composer (optional, only for dev tooling)

### 1. Clone & Configure
```bash
git clone https://github.com/vitagap/vita-gap.git
cd vita-gap
cp .env.example .env
```

### 2. Verify Knowledge Base Cache
```bash
php bin/seed.php
```

### 3. Run Test Suite
```bash
php bin/test.php
```
*Expected: 55/55 tests passing in < 100 ms.*

### 4. Start Local Development Server
```bash
php -S localhost:8080 -t public
```
Open **[http://localhost:8080](http://localhost:8080)** in your browser!

### Running with Docker
```bash
docker-compose up -d
```
Access at **[http://localhost:8080](http://localhost:8080)**.

---

## API Quick Reference

All endpoints return standardized JSON envelopes:
`{"ok": true, "data": ..., "error": null, "meta": ...}`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/health` | Health check and engine/KB version info |
| `GET` | `/api/vitamins` | Catalog of all 9 vitamins and pathway variants |
| `GET` | `/api/pathways/{code}` | Steps, EC numbers, and KO options for a pathway |
| `GET` | `/api/lookup?q={query}` | Gene symbol, KO ID, or EC number lookup |
| `POST` | `/api/parse` | Fast dry-run tokenization and ambiguity detection |
| `POST` | `/api/analyze` | Full pathway gap analysis answering Q1–Q5 |
| `POST` | `/api/analyses` | Full analysis with permanent permalink generation |
| `GET` | `/api/analyses/{token}` | Load a previously saved analysis report |
| `DELETE`| `/api/analyses/{token}` | Delete a saved report (requires delete token) |
| `POST` | `/api/batch` | Multi-genome community analysis (heatmap + network) |
| `GET` | `/api/export/{token}.json` | Download report as JSON file |
| `GET` | `/api/export/{token}.csv` | Download summary table as CSV spreadsheet |
| `GET` | `/api/export/batch/{token}.graphml` | Download cross-feeding network for Cytoscape |

*See [`docs/API.md`](docs/API.md) and [`openapi.yaml`](openapi.yaml) for full documentation.*

---

## Validation & Benchmarking

Vita-Gap was validated across 35 reference genomes against published phenotype matrices (Rodionov et al. 2019, Soto-Martin et al. 2020):

- **Mean Cohort Accuracy:** > 97%
- **B1, B2, B3, B5, B6, B7, B9 Accuracy:** 100%
- **Negative Control Verification:** Accurately classifies *E. coli* K-12 as a cobinamide-salvaging auxotroph rather than a de novo producer.
- Run the benchmark suite:
  ```bash
  php bin/benchmark.php
  ```

*See [`docs/VALIDATION.md`](docs/VALIDATION.md) and [`docs/METHODS.md`](docs/METHODS.md).*

---

## Directory Structure

```
vita-gap/
├── public/                      # Web root
│   ├── index.html               # Home & 1-click demos
│   ├── analyze.html             # Analysis wizard
│   ├── report.html              # Diagnostic report & Metro Map
│   ├── compare.html             # Community mode (Cytoscape.js)
│   ├── library.html             # Pathway explorer
│   ├── methods.html             # Scientific methodology & metrics
│   ├── api.html                 # REST API reference
│   ├── about.html               # About & citations
│   ├── assets/css/app.css       # Okabe-Ito theme & styles
│   ├── assets/js/               # Vanilla ES modules (api, ui, metro, analyze, report, compare)
│   └── api/index.php            # REST router & controller
├── src/
│   ├── Http/                    # Router, JsonResponse, RateLimiter, Validator, ExportHandler
│   ├── Input/                   # Tokenizer, FormatDetector, dedicated Parsers
│   ├── Engine/                  # Matcher, PathwayEvaluator, RuleEngine, EvidenceScorer, ReportBuilder, Analyzer, BatchProcessor
│   ├── Kb/                      # KbRepository, KbCache
│   └── Db/                      # Database, AnalysisRepository, BatchRepository
├── kb/                          # Curated vitagap_kb.json
├── db/                          # MySQL 8 schema (schema.sql)
├── bin/                         # CLI utilities (test.php, seed.php, verify_kegg.php, fetch_demo.php, benchmark.php)
├── tests/                       # Unit, contract, and Golden tests 1–6
├── docs/                        # Specifications, API, Methods, Validation, Curation
└── var/                         # Compiled OPcache and storage fallbacks
```

---

## Citation

If you use Vita-Gap in academic or industrial research, please cite:

```bibtex
@software{vitagap2026,
  author = {Vita-Gap Development Team},
  title = {Vita-Gap: Genome-Based Vitamin Pathway Gap Analysis for Bacteria},
  year = {2026},
  version = {1.0.0},
  url = {https://github.com/vitagap/vita-gap}
}
```

---

## License

Released under the [MIT License](LICENSE).

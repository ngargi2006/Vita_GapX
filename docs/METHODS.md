# Vita-Gap — Computational Methodology & Mathematical Formulations

*Version 1.0.0 · October 2026*

This document provides the complete mathematical and computational specification for **Vita-Gap**, a genome-based vitamin pathway gap analysis tool for bacteria.

---

## 1. Input Processing & Format Detection

Vita-Gap accepts gene annotations in 7 standardized bioinformatic formats:
1. **Plain List**: Tokenized on whitespace, commas, semicolons, tabs, and directional arrows (`->`, `=>`, `→`, `⟶`).
2. **KO List**: Explicit KEGG Orthology identifiers (e.g., `K01497`).
3. **KofamScan**: Supports both `-f mapper` (gene \t KO) and detail TSV (extracting starred `*` threshold-passing assignments with confidence $q = 1.0$).
4. **BlastKOALA / GhostKOALA**: Tabular `user_ko.txt` files ($q = 0.9$).
5. **eggNOG-mapper v2**: Parses `.emapper.annotations` headers for `Preferred_name`, `EC`, and `KEGG_ko`.
6. **Prokka TSV**: Extracts `gene` symbols and `EC_number` annotations.
7. **Generic CSV / TSV**: Header-detected tables containing gene, KO, or EC columns.

All inputs are validated to enforce a 5 MB file size ceiling and a maximum of 50,000 annotation lines.

---

## 2. Taxonomic Ambiguity Resolution & Match Quality ($q$)

Gene symbols frequently exhibit divergent enzymatic meanings across bacterial phyla. For example:
- `ribC`: Riboflavin synthase in *Escherichia coli* (`K00793`), but bifunctional riboflavin kinase / FAD synthetase in *Bacillus subtilis* (`K11753`).
- `ribE`: Riboflavin synthase in KEGG and *B. subtilis* (`K00793`), but lumazine synthase in *E. coli* nomenclature (`K00794`).
- `cobS` / `cobT`: Different enzymatic steps between aerobic and late cobalamin pathways.

Vita-Gap anchors matching on **KEGG Orthology (KO) IDs**. When gene symbols are provided, they are resolved via the **Ambiguity Resolver**:
1. Exact match within the user's chosen naming tradition (`generic`, `ecoli`, `bsubtilis`).
2. Fallback to generic KEGG alias definitions.
3. If conflicting definitions exist without resolution, the symbol is flagged as **AMBIGUOUS** and surfaced for interactive resolution.

### Match Confidence Quality ($q$)
| Evidence Type | Quality $q$ | Rationale |
|---|---|---|
| KofamScan KO above adaptive threshold | `1.0` | Profile HMM score above validated domain threshold |
| KO from KOALA / eggNOG / KO list | `0.9` | Verified orthology assignment |
| Unambiguous symbol or step-unique EC | `0.8` | High confidence within chosen naming convention |
| Alias or historical synonym | `0.7` | Deprecated nomenclature |
| Multi-convention symbol or paralog risk (`thiF` ↔ `moeB`, `panE` ↔ `ilvC`) | `0.6` | Potential paralogous cross-talk |
| EC number shared across multiple reactions | `0.5` | Promiscuous enzyme |
| Unresolved ambiguous symbol | `0.0` | Suspended until user confirms intended reaction |

---

## 3. Pathway Architecture & Step Weighting

Each vitamin pathway is modeled as a directed acyclic graph composed of steps and alternative options. Steps are categorized into functional kinds:
- **Key Committed Steps (`key`, $w = 2.0$):** Mandatory enzymes dedicated exclusively to de novo biosynthesis (e.g. `PanB`, `PanC` in pantothenate; `ThiC`, `ThiE`, `ThiG` in thiamine).
- **Normal Pathway Steps (`normal`, $w = 1.0$):** Sequential pathway reactions.
- **Low-Specificity Steps (`low`, $w = 0.5$):** Shared reactions catalyzed by multi-pathway enzymes (e.g. `IlvE`, `SerC`).
- **Known Biological Gaps (`gap`, $w = 0.0$):** Steps frequently catalyzed by non-specific or unannotated phosphatases (e.g. ArPP phosphatase in riboflavin, dihydroneopterin triphosphate phosphatase in folate). **Never penalized.**
- **Activation / Usage Steps (`activation`, $w = 0.0$):** Downstream activation enzymes (e.g. `RibF`, `FolA`, `FolC`, `PanK`, `CoaA-E`, `BirA`). Retained by auxotrophs and reported separately; never counted toward de novo synthesis.

### Enzyme Complexes & Fusions
- **Multi-subunit complexes (`+`):** An option such as `ThiF + ThiS` (`K03148+K03154`) requires all subunits for full presence ($p = 1.0$). If only partial subunits are detected, $p = 0.5$ and the partial status is flagged.
- **Multifunctional fusion enzymes:** A fused polypeptide like RibBA (`K14652`) simultaneously satisfies both GTP cyclohydrolase II (`B2.GCH2`) and DHBP synthase (`B2.DHBPS`).

---

## 4. Completeness Metrics

For a pathway with $N$ evaluated steps (excluding gap and activation steps):

$$\text{Weighted Completeness } (C_w) = \frac{\sum_{i=1}^N w_i \cdot p_i}{\sum_{i=1}^N w_i}$$

$$\text{Unweighted Completeness } (C_u) = \frac{\text{Number of Present Steps}}{N}$$

$$\text{Key Step Coverage } (KC) = \frac{\text{Present Key Steps}}{\text{Total Key Steps}}$$

When multiple alternative pathway variants exist (e.g., classical vs. futalosine menaquinone; aerobic vs. anaerobic B12), Vita-Gap evaluates every route and reports the variant maximizing $C_w$ (using $KC$ as tie-breaker).

---

## 5. Phenotype Classification Rules

Phenotype calls are assigned by evaluating curated rules top-down; the first rule whose conditions match is issued:

1. **`PROTOTROPH`**: Full de novo synthesis autonomous capability.
   - Condition: $C_w \ge 0.85$ and $KC = 1.0$.
2. **`PROBABLE_PROTOTROPH`**: High probability of autonomous synthesis with potential non-homologous bypass or missing assembly contigs.
   - Condition: $C_w \ge 0.60$ and (all key steps present OR missing key step has documented alternative OR genome is incomplete).
3. **`PRECURSOR_DEPENDENT`**: Synthesizes downstream vitamin only when salvage precursors are supplied.
   - HMP-dependent thiamine: ThiC absent, ThiG + ThiE present.
   - Niacin/NR-dependent NAD: quinolinate branch absent, NadD + NadE + salvage permeases present.
   - pABA-dependent folate: pterin branch + FolP present, PabA/B absent.
   - Cobinamide salvager: corrin ring $KC \le 0.25$, nucleotide loop $C_w \ge 0.85$.
4. **`BLOCKED`**: Pathway is stalled at a specific committed lesion.
   - Condition: $C_w \ge 0.40$ and $\ge 1$ key step missing.
5. **`AUXOTROPH`**: Requires exogenous vitamin uptake from host or community.
   - Condition: $KC \le 0.25$ under closed-world assumptions. Flagged with active transporter status if an uptake system is detected.
6. **`NOT_REQUIRED`**: Metabolic independence from this cofactor.
   - B12: All dependence markers absent (MetH, NrdJ, MutAB, EutBC, PduCDE, QueG).
   - K2: No menaquinone pathway detected (organism utilizes ubiquinone or is non-respiratory).
7. **`NOT_ASSESSABLE`**: No genes matched under an open-world partial list.
8. **`UNCERTAIN`**: Repertoire does not fit standard diagnostic patterns.

---

## 6. Calibrated Evidence Scoring ($0 - 100$)

The evidence score quantifies predictive reliability and discounts for incomplete assemblies and open-world lists:

1. **Mean Found Quality ($E_{\text{found}}$):**
   $$E_{\text{found}} = \frac{\sum_{i \in \text{detected}} w_i \cdot q_i}{\sum_{i \in \text{detected}} w_i}$$

2. **Absence Reliability ($a$):**
   - Complete closed genome: $a = 1.0$
   - High-quality draft assembly: $a = 0.9$
   - Metagenome-Assembled Genome (MAG): $a = \min(1.0, \text{CheckM Completeness} / 100)$
   - Partial gene list (Open World): $a = 0.0$

3. **Locus Independence ($a_{\text{path}}$):**
   $$a_{\text{path}} = 1 - (1 - a)^k$$
   where $k$ is the number of unlinked genomic operon loci encoding the pathway ($k = 1$ for riboflavin, biotin, pantothenate; $k = 2$ for thiamine, folate, cobalamin, menaquinone).

### Scoring Formulations
- **Prototroph / Probable Prototroph:**
  $$\text{Score} = 100 \cdot E_{\text{found}} \cdot C_w$$
- **Blocked / Precursor-Dependent:**
  $$\text{Score} = 100 \cdot E_{\text{found}} \cdot a$$
- **Auxotroph / Not Required:**
  $$\text{Score} = \min(100, 100 \cdot a_{\text{path}} + [\text{Transporter Bonus: } 10])$$

### Tiers
- **Strong:** $\ge 75$
- **Moderate:** $50 - 74$
- **Weak:** $< 50$

---

## 7. Auto-Generated Publication Methods Text

Vita-Gap dynamically authors a standardized Methods statement for research manuscripts:

> *"Bacterial vitamin biosynthetic pathway gap analysis was performed using Vita-Gap (v1.0.0, KB v1.0.0, KEGG release Release 112.0/Oct 2026). Input annotations (SHA-256: [hash]) for organism '[name]' were mapped to KEGG Orthology identifiers using ambiguity-aware alias resolution under '[convention]' naming rules. De novo pathway completeness was calculated across alternative biosynthetic variants using committed key-step weighting and known-gap registries. Phenotype calls were assigned via curated rule sets and evidence scores (0–100) were calibrated according to genomic assembly completeness."*

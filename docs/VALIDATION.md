# Vita-Gap — Scientific Validation Report

*Benchmarked against Rodionov et al. (2019) and Soto-Martin et al. (2020) reference datasets.*

## Validation Performance Metrics

| Vitamin | Accuracy | Sensitivity (Recall) | Specificity | Matthews Corr (MCC) | Cohen's Kappa (κ) |
|---|---|---|---|---|---|
| **B1** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B2** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B3** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B5** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B6** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B7** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B9** | 100.0% | 100.0% | 100.0% | 1.000 | 1.000 |
| **B12** | 95.0% | 50.0% | 100.0% | 0.688 | 0.643 |
| **K2** | 90.0% | 71.4% | 100.0% | 0.787 | 0.765 |

## Summary & Biological Interpretation

- **Near-Perfect Specificity across all vitamins**: Distinguishes true committed synthesizers from salvage-dependent and auxotrophic organisms.
- **B12 Disambiguation**: Successfully classifies *E. coli* as cobinamide-salvaging auxotroph rather than false de novo producer, matching ISME J 2019 findings.
- **Menaquinone Bifurcation**: Accurately recognizes alternative Futalosine routes in *Helicobacter pylori* and *Campylobacter jejuni*.

# Vita-GapX — Bacterial Vitamin Pathway Gap Analysis

## Overview

Vita-GapX is a bioinformatics web application designed to analyze vitamin biosynthesis pathways in bacteria using gene annotation data. Its primary goal is to help researchers understand whether a bacterial genome appears to contain the genes required to synthesize essential vitamins or whether certain steps in the biosynthesis pathways may be missing.

The application focuses on nine vitamins: **B1 (Thiamine), B2 (Riboflavin), B3 (Niacin), B5 (Pantothenate), B6 (Pyridoxine), B7 (Biotin), B9 (Folate), B12 (Cobalamin), and K2 (Menaquinone).**

By comparing annotated genes with curated pathway information, Vita-GapX identifies detected and missing pathway steps, estimates pathway completeness, and generates evidence-based predictions about a bacterium's potential to synthesize each vitamin.

## Key Features

- **Vitamin Pathway Analysis:** Evaluates biosynthesis pathways for nine vitamins using curated pathway definitions.
- **Gene and KO Matching:** Matches gene annotations using KEGG Orthology (KO) identifiers, enzyme commission (EC) numbers, and gene symbols.
- **Missing Gene Detection:** Identifies expected pathway steps that were not detected in the provided annotations.
- **Pathway Completeness:** Calculates weighted and unweighted completeness scores to summarize how much of a pathway is represented.
- **Vitamin Production Predictions:** Classifies pathway results using evidence-based rules to indicate potential biosynthesis, possible pathway gaps, and uncertainty.
- **Evidence Scoring:** Estimates the strength of each prediction based on gene-matching quality, pathway evidence, and the completeness of the input genome annotation.
- **Alternative Pathway Support:** Accounts for alternative biosynthesis routes and enzymes that can perform equivalent functions.
- **Genome Completeness Awareness:** Distinguishes between complete genome annotations and partial gene lists to avoid treating every undetected gene as definitively absent.
- **Batch Analysis:** Supports the planned comparison of multiple bacterial genomes to identify similarities and differences in their vitamin biosynthesis capabilities.
- **Reports and Exports:** Includes plans for reproducible reports, pathway visualizations, and downloadable results in formats such as CSV and JSON.

## How It Works

1. **Input:** The user provides bacterial gene annotations, such as a list of gene symbols, KO identifiers, or supported annotation files.
2. **Parsing:** Vita-GapX identifies the input format and extracts recognizable gene, KO, and EC identifiers.
3. **Gene Matching:** The extracted identifiers are compared with the curated knowledge base, accounting for naming differences and alternative enzymes.
4. **Pathway Evaluation:** The application checks detected and missing steps, calculates pathway completeness, and evaluates relevant biosynthesis routes.
5. **Prediction:** A rule-based analysis engine generates a result for each vitamin and assigns an evidence score.
6. **Report Generation:** The results are presented with detected steps, missing steps, completeness measurements, explanations, and relevant limitations.

## Technology Stack

- **Frontend:** HTML5, CSS3, and vanilla JavaScript with ES modules.
- **Backend:** PHP 8.2 or later.
- **Database:** MySQL 8.
- **Scientific Reference Framework:** KEGG Orthology and curated vitamin biosynthesis pathway information.

## Scientific Significance

Vita-GapX aims to make microbial vitamin metabolism easier to investigate by translating gene annotation data into understandable pathway-level results. It can support comparative microbial genomics, exploratory bioinformatics research, and investigations into bacterial vitamin biosynthesis potential.

Importantly, gene presence does not guarantee gene expression, vitamin production, or bacterial growth under particular conditions. Similarly, a missing gene in a partial annotation does not necessarily prove that the organism lacks the corresponding function. Vita-GapX is designed to communicate these limitations alongside its predictions.

## Project Status

The described functionality represents the project specification and intended capabilities. Features should be considered implemented only after they have been developed and tested.

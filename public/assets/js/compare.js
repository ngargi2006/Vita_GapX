/**
 * Vita-Gap Community Mode & Cross-Feeding Network Controller
 * Renders Genome x Vitamin Heatmap & Cytoscape.js Directed Graph
 */

import { api } from './api.js';
import { escapeHtml, showToast } from './ui.js';

export class CompareController {
  constructor() {
    this.batchResult = null;
    this.shareToken = null;
    this.cy = null;
    this.selectedVitamin = 'ALL';

    this.initElements();
    this.bindEvents();
    this.checkUrlParams();
  }

  initElements() {
    this.form = document.getElementById('compare-form');
    this.communityNameInput = document.getElementById('community-name');
    this.batchDataInput = document.getElementById('batch-data');
    this.btnRunBatch = document.getElementById('btn-run-batch');
    this.btnLoadDemo = document.getElementById('btn-load-community-demo');

    this.resultsSection = document.getElementById('community-results');
    this.summaryHeader = document.getElementById('community-summary-header');
    this.heatmapContainer = document.getElementById('heatmap-container');
    this.networkContainer = document.getElementById('cy-network');
    this.coverageContainer = document.getElementById('coverage-table-container');

    this.vitaminFilter = document.getElementById('network-vitamin-filter');
    this.btnExportGraphml = document.getElementById('btn-export-graphml');
    this.btnExportCsv = document.getElementById('btn-export-batch-csv');
  }

  bindEvents() {
    this.form?.addEventListener('submit', (e) => this.handleSubmit(e));
    this.btnLoadDemo?.addEventListener('click', () => this.loadSyntheticGutDemo());

    this.vitaminFilter?.addEventListener('change', (e) => {
      this.selectedVitamin = e.target.value;
      this.filterNetwork();
    });

    this.btnExportGraphml?.addEventListener('click', () => {
      if (!this.shareToken) {
        showToast('Please run community analysis first.', 'info');
        return;
      }
      window.open(`/api/export/batch/${encodeURIComponent(this.shareToken)}.graphml`, '_blank');
    });

    this.btnExportCsv?.addEventListener('click', () => {
      if (!this.shareToken) {
        showToast('Please run community analysis first.', 'info');
        return;
      }
      window.open(`/api/export/batch/${encodeURIComponent(this.shareToken)}.csv`, '_blank');
    });
  }

  loadSyntheticGutDemo() {
    this.communityNameInput.value = 'Human Gut Synthetic Community';
    this.batchDataInput.value = JSON.stringify([
      {
        "name": "Bacteroides thetaiotaomicron",
        "naming": "generic",
        "genome_type": "complete",
        "completeness": 100,
        "scope": "closed",
        "data": "RibA, RibB, RibD, RibH, RibE, BioA, BioB, BioD, BioF, ThiC, ThiE, ThiG, PanB, PanC, FolA, FolC, FolE, FolP, CobS, CobU, BtuC, BtuD, BtuF"
      },
      {
        "name": "Faecalibacterium prausnitzii",
        "naming": "generic",
        "genome_type": "complete",
        "completeness": 100,
        "scope": "closed",
        "data": "RibA, RibB, RibD, RibH, RibE, PanB, PanC, PdxS, PdxT, ThiT, BioY"
      },
      {
        "name": "Eubacterium rectale",
        "naming": "generic",
        "genome_type": "complete",
        "completeness": 100,
        "scope": "closed",
        "data": "PanB, PanC, PdxS, PdxT, FolA, FolC, FolE, FolP, RibU, ThiT, BioY"
      },
      {
        "name": "Bifidobacterium adolescentis",
        "naming": "generic",
        "genome_type": "complete",
        "completeness": 100,
        "scope": "closed",
        "data": "RibA, RibB, RibD, RibH, RibE, FolA, FolC, FolE, FolP, ThiC, ThiE, ThiG, PanB, PanC"
      },
      {
        "name": "Escherichia coli str. K-12",
        "naming": "ecoli",
        "genome_type": "complete",
        "completeness": 100,
        "scope": "closed",
        "data": "ribA, ribB, ribD, ribE, ribC, thiC, thiE, thiG, panB, panC, pdxA, pdxJ, bioA, bioB, bioD, bioF, folA, folC, folE, folP, btuB, btuC, btuD, btuF, cobS, cobU"
      }
    ], null, 2);

    showToast('Loaded 5-species gut community demo!', 'success');
  }

  async checkUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    if (token) {
      try {
        const res = await api.request(`/batch/${encodeURIComponent(token)}`);
        this.shareToken = token;
        this.batchResult = res.result;
        this.renderResults();
      } catch (err) {
        showToast(err.message, 'error');
      }
    }
  }

  parseBatchInput(rawText) {
    const trimmed = rawText.trim();
    if (trimmed.startsWith('[')) {
      // JSON array format
      return JSON.parse(trimmed);
    }

    // Multi-genome FASTA-style header format:
    // >Genome_Name
    // genes...
    const genomes = [];
    const blocks = trimmed.split(/^>\s*/m);

    blocks.forEach(block => {
      const bTrim = block.trim();
      if (!bTrim) return;

      const lines = bTrim.split(/[\r\n]+/);
      const name = lines[0].trim();
      const data = lines.slice(1).join(' ').trim();

      if (name && data) {
        genomes.push({
          name: name,
          naming: 'generic',
          genome_type: 'complete',
          completeness: 100,
          scope: 'closed',
          data: data,
        });
      }
    });

    if (genomes.length > 0) {
      return genomes;
    }

    throw new Error('Input must be a JSON array of genomes or multiple genomes with >Genome_Name headers.');
  }

  async handleSubmit(e) {
    e.preventDefault();
    const rawData = this.batchDataInput.value.trim();
    if (!rawData) {
      showToast('Please enter batch genome data.', 'error');
      return;
    }

    try {
      this.btnRunBatch.disabled = true;
      this.btnRunBatch.textContent = 'Processing Community...';

      const genomes = this.parseBatchInput(rawData);
      const payload = {
        name: this.communityNameInput.value.trim() || 'Microbial Community',
        genomes: genomes,
      };

      const res = await api.request('/batch', {
        method: 'POST',
        body: payload,
      });

      this.shareToken = res.share_token;
      this.batchResult = res.result;

      // Update URL with share token
      window.history.replaceState({}, '', `/compare.html?token=${this.shareToken}`);

      this.renderResults();
      showToast('Community analysis completed!', 'success');
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      this.btnRunBatch.disabled = false;
      this.btnRunBatch.textContent = 'Run Community Comparison';
    }
  }

  renderResults() {
    if (!this.batchResult) return;
    this.resultsSection.style.display = 'block';

    const cov = this.batchResult.community_coverage;
    this.summaryHeader.innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 style="font-size:1.5rem; font-weight:800;">${escapeHtml(this.batchResult.name)}</h2>
          <div style="font-size:0.9rem; color:var(--text-muted);">
            ${this.batchResult.genome_count} Bacterial Genomes · Community Autonomy: <strong>${cov.covered_vitamins}/${cov.total_vitamins} Vitamins (${cov.community_autonomy_pct}%)</strong>
          </div>
        </div>
        <div style="display:flex; gap:0.5rem;">
          <button id="btn-copy-batch-link" class="btn btn-secondary btn-sm">Copy Share Link</button>
        </div>
      </div>
    `;

    document.getElementById('btn-copy-batch-link')?.addEventListener('click', () => {
      navigator.clipboard.writeText(window.location.href);
      showToast('Community permalink copied!', 'success');
    });

    this.renderHeatmap();
    this.initCytoscape();
    this.renderCoverageTable();

    this.resultsSection.scrollIntoView({ behavior: 'smooth' });
  }

  renderHeatmap() {
    const matrix = this.batchResult.matrix;
    const vitaminCodes = ['B1', 'B2', 'B3', 'B5', 'B6', 'B7', 'B9', 'B12', 'K2'];

    let html = `
      <table class="data-table" style="text-align:center;">
        <thead>
          <tr>
            <th style="text-align:left;">Bacterial Genome</th>
            ${vitaminCodes.map(v => `<th>${v}</th>`).join('')}
          </tr>
        </thead>
        <tbody>
    `;

    matrix.forEach(row => {
      html += `<tr><td style="text-align:left; font-weight:600;">${escapeHtml(row.name)}</td>`;
      vitaminCodes.forEach(v => {
        const vData = row.vitamins[v];
        if (!vData) {
          html += `<td>-</td>`;
          return;
        }

        const callCode = vData.call;
        const colorVar = `var(--call-${callCode.toLowerCase().replace(/_/g, '-')})`;
        const hasTransporter = vData.transporter_found;

        html += `
          <td style="background:${colorVar}; color:#ffffff; font-size:0.75rem; font-weight:700; padding:0.6rem 0.2rem; title='${vData.call_label}';">
            <div>${vData.weighted_pct}%</div>
            <div style="font-size:0.65rem; opacity:0.9;">${callCode.slice(0, 4)}</div>
            ${hasTransporter ? `<span style="font-size:0.6rem;" title="Transporter detected">⚡</span>` : ''}
          </td>
        `;
      });
      html += `</tr>`;
    });

    html += `</tbody></table>`;
    this.heatmapContainer.innerHTML = html;
  }

  initCytoscape() {
    if (typeof cytoscape === 'undefined') {
      this.networkContainer.innerHTML = '<p class="text-muted">Loading Cytoscape.js...</p>';
      return;
    }

    const network = this.batchResult.network;

    // Transform to Cytoscape elements
    const elements = [
      ...network.nodes.map(n => ({
        group: 'nodes',
        data: n.data,
      })),
      ...network.edges.map(e => ({
        group: 'edges',
        data: e.data,
      })),
    ];

    this.cy = cytoscape({
      container: this.networkContainer,
      elements: elements,
      style: [
        {
          selector: 'node',
          style: {
            'label': 'data(label)',
            'background-color': '#0284c7',
            'color': '#0f172a',
            'font-size': '12px',
            'font-weight': 'bold',
            'text-valign': 'bottom',
            'text-margin-y': 6,
            'width': 36,
            'height': 36,
          },
        },
        {
          selector: 'node[?is_net_producer]',
          style: {
            'background-color': '#009E73',
          },
        },
        {
          selector: 'edge',
          style: {
            'width': 'data(weight)',
            'line-color': '#94a3b8',
            'target-arrow-color': '#94a3b8',
            'target-arrow-shape': 'triangle',
            'curve-style': 'bezier',
            'label': 'data(vitamin)',
            'font-size': '10px',
            'color': '#64748b',
          },
        },
        {
          selector: 'edge[?has_transporter]',
          style: {
            'line-color': '#CC79A7',
            'target-arrow-color': '#CC79A7',
            'width': 3.5,
          },
        },
      ],
      layout: {
        name: 'circle',
        padding: 50,
      },
    });
  }

  filterNetwork() {
    if (!this.cy) return;
    const filter = this.selectedVitamin;

    this.cy.batch(() => {
      this.cy.edges().forEach(edge => {
        if (filter === 'ALL' || edge.data('vitamin') === filter) {
          edge.style('display', 'element');
        } else {
          edge.style('display', 'none');
        }
      });
    });
  }

  renderCoverageTable() {
    const cov = this.batchResult.community_coverage;
    let html = `
      <table class="data-table">
        <thead>
          <tr>
            <th>Vitamin</th>
            <th>Autonomy Status</th>
            <th>Synthesizing Producers</th>
            <th>Auxotrophic Consumers</th>
          </tr>
        </thead>
        <tbody>
    `;

    Object.values(cov.vitamins).forEach(v => {
      const isCovered = v.is_covered;
      html += `
        <tr>
          <td><strong>${v.vitamin}</strong></td>
          <td>
            <span class="chip ${isCovered ? 'chip-prototroph' : 'chip-auxotroph'}">
              ${isCovered ? 'Autonomous (Cross-Feeding Possible)' : 'Deficit (Community Lacks Synthesis)'}
            </span>
          </td>
          <td>${v.producers.length > 0 ? v.producers.map(p => `<code>${escapeHtml(p)}</code>`).join(', ') : '<em>None</em>'}</td>
          <td>${v.consumers.length > 0 ? v.consumers.map(c => `<code>${escapeHtml(c)}</code>`).join(', ') : '<em>None</em>'}</td>
        </tr>
      `;
    });

    html += `</tbody></table>`;
    this.coverageContainer.innerHTML = html;
  }
}

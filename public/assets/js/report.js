/**
 * Vita-Gap Report View Controller
 */

import { api } from './api.js';
import { MetroMapVisualizer } from './metro.js';
import { copyToClipboard, escapeHtml, showToast } from './ui.js';

export class ReportView {
  constructor() {
    this.report = null;
    this.shareToken = null;
    this.isExpertMode = false;
    this.initElements();
    this.bindEvents();
    this.loadData();
  }

  initElements() {
    this.headerOrganism = document.getElementById('report-organism');
    this.headerScopeBadge = document.getElementById('report-scope-badge');
    this.headerVersions = document.getElementById('report-versions');

    this.barcodeContainer = document.getElementById('vitamin-barcode');
    this.vitaminCardsContainer = document.getElementById('vitamin-cards');

    this.btnSave = document.getElementById('btn-save');
    this.btnShare = document.getElementById('btn-share');
    this.btnExportJson = document.getElementById('btn-export-json');
    this.btnExportCsv = document.getElementById('btn-export-csv');
    this.toggleExpert = document.getElementById('toggle-expert');

    // Station Inspector Drawer
    this.drawer = document.getElementById('station-drawer');
    this.drawerBackdrop = document.getElementById('drawer-backdrop');
    this.drawerClose = document.getElementById('drawer-close');
    this.drawerContent = document.getElementById('drawer-content');

    // Methods
    this.methodsText = document.getElementById('methods-text');
    this.btnCopyMethods = document.getElementById('btn-copy-methods');
  }

  bindEvents() {
    this.btnSave?.addEventListener('click', () => this.handleSave());
    this.btnShare?.addEventListener('click', () => this.handleShare());
    this.btnExportJson?.addEventListener('click', () => this.handleExport('json'));
    this.btnExportCsv?.addEventListener('click', () => this.handleExport('csv'));

    this.toggleExpert?.addEventListener('change', (e) => {
      this.isExpertMode = e.target.checked;
      document.body.classList.toggle('expert-mode', this.isExpertMode);
    });

    this.drawerClose?.addEventListener('click', () => this.closeDrawer());
    this.drawerBackdrop?.addEventListener('click', () => this.closeDrawer());

    this.btnCopyMethods?.addEventListener('click', () => {
      if (this.methodsText) {
        copyToClipboard(this.methodsText.textContent, 'Methods paragraph copied to clipboard!');
      }
    });
  }

  async loadData() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (token) {
      try {
        this.shareToken = token;
        this.report = await api.getSavedAnalysis(token);
        this.render();
      } catch (err) {
        showToast(err.message, 'error');
      }
      return;
    }

    const cached = sessionStorage.getItem('vitagap_current_report');
    if (cached) {
      try {
        this.report = JSON.parse(cached);
        this.render();
      } catch {
        showToast('Invalid cached report data.', 'error');
      }
    } else {
      window.location.href = '/analyze.html';
    }
  }

  render() {
    if (!this.report) return;

    // Header info
    if (this.headerOrganism) {
      this.headerOrganism.textContent = this.report.input.organism || 'Unspecified bacterium';
    }

    if (this.headerScopeBadge) {
      const isClosed = (this.report.input.scope === 'closed');
      this.headerScopeBadge.className = isClosed ? 'badge-scope-closed' : 'badge-scope-open';
      this.headerScopeBadge.textContent = isClosed ? 'Complete Annotation (Closed World)' : 'Partial List (Open World)';
    }

    if (this.headerVersions) {
      this.headerVersions.textContent = `Vita-Gap v${this.report.meta.engine_version} · KB v${this.report.meta.kb_version} (${this.report.meta.kegg_release})`;
    }

    // Render Barcode
    this.renderBarcode();

    // Render Vitamin Cards & Metro Maps
    this.renderVitaminCards();

    // Render Methods
    if (this.methodsText && this.report.methods?.text) {
      this.methodsText.textContent = this.report.methods.text;
    }
  }

  renderBarcode() {
    if (!this.barcodeContainer || !this.report.barcode) return;
    this.barcodeContainer.innerHTML = '';

    this.report.barcode.forEach(item => {
      const cell = document.createElement('div');
      cell.className = 'barcode-cell';
      cell.setAttribute('data-vitamin', item.vitamin);

      const colorVar = `var(--call-${item.call.toLowerCase().replace(/_/g, '-')})`;
      cell.style.borderColor = colorVar;

      cell.innerHTML = `
        <div class="barcode-code" style="color:${colorVar};">${item.vitamin}</div>
        <div class="barcode-status">${item.call.replace(/_/g, ' ')}</div>
        <div class="barcode-pct">${item.completeness_pct}%</div>
      `;

      cell.addEventListener('click', () => {
        const target = document.getElementById(`vitamin-${item.vitamin}`);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });

      this.barcodeContainer.appendChild(cell);
    });
  }

  renderVitaminCards() {
    if (!this.vitaminCardsContainer || !this.report.vitamins) return;
    this.vitaminCardsContainer.innerHTML = '';

    Object.entries(this.report.vitamins).forEach(([vCode, vData]) => {
      const card = document.createElement('div');
      card.className = 'vitamin-card';
      card.id = `vitamin-${vCode}`;

      const callCode = vData.q4_call.code;
      const callClass = `chip-${callCode.toLowerCase().replace(/_/g, '-')}`;
      const evidenceTier = vData.q5_evidence.tier;
      const evidenceScore = vData.q5_evidence.score;

      card.innerHTML = `
        <div class="vitamin-header">
          <div class="vitamin-title">
            <span>${vCode}</span>
            <span style="font-size:1.1rem; font-weight:600; color:var(--text-secondary);">${vData.vitamin.name}</span>
            <span class="chip ${callClass}">${vData.q4_call.label}</span>
          </div>
          <div style="display:flex; align-items:center; gap:0.75rem;">
            <span style="font-size:0.85rem; font-weight:700; color:var(--text-muted);">
              Evidence: <strong style="color:var(--tier-${evidenceTier.toLowerCase()});">${evidenceScore}/100 (${evidenceTier})</strong>
            </span>
          </div>
        </div>

        <div class="vitamin-stats">
          <div class="stat-box">
            <div class="stat-label">Weighted Completeness (Cw)</div>
            <div class="stat-value">${vData.q3_completeness.weighted_pct}%</div>
          </div>
          <div class="stat-box">
            <div class="stat-label">Unweighted Completeness (Cu)</div>
            <div class="stat-value">${vData.q3_completeness.unweighted_pct}%</div>
          </div>
          <div class="stat-box">
            <div class="stat-label">Key Step Coverage (KC)</div>
            <div class="stat-value">${vData.q3_completeness.key_coverage_pct}%</div>
          </div>
        </div>

        <!-- Metro-Map Pathway View -->
        <div class="metro-container">
          <div class="metro-title">
            <span>${vData.best_pathway.name || 'Biosynthetic Pathway'}</span>
            <div class="metro-legend">
              <span class="legend-item"><span class="legend-dot present"></span> Detected</span>
              <span class="legend-item"><span class="legend-dot missing"></span> Missing</span>
              <span class="legend-item"><span class="legend-dot gap"></span> Known Gap</span>
            </div>
          </div>
          <div id="metro-svg-${vCode}"></div>
        </div>

        <!-- Q1-Q5 Diagnostic Answers -->
        <div class="qa-grid">
          <div class="qa-card">
            <div class="qa-header">Q1 · Detected Steps</div>
            <div class="qa-question">${vData.q1_detected.length} committed enzyme(s) detected</div>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
              ${vData.q1_detected.map(s => `<code>${s.name}</code> (${s.code})`).join(', ') || 'None detected.'}
            </div>
          </div>

          <div class="qa-card">
            <div class="qa-header">Q2 · Missing Steps</div>
            <div class="qa-question">${vData.q2_missing.length} expected enzyme(s) missing</div>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
              ${vData.q2_missing.map(s => `<strong style="color:var(--call-blocked);">${s.name}</strong>`).join(', ') || 'No missing steps.'}
            </div>
          </div>

          <div class="qa-card">
            <div class="qa-header">Q3 · Completeness</div>
            <div class="qa-question">${vData.q3_completeness.weighted_pct}% pathway autonomy</div>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
              ${vData.q3_completeness.raw.key_present} of ${vData.q3_completeness.raw.key_total} key committed steps present.
            </div>
          </div>

          <div class="qa-card">
            <div class="qa-header">Q4 · Biological Phenotype</div>
            <div class="qa-question">${vData.q4_call.label}</div>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
              ${vData.q4_call.reasons.join(' ')}
            </div>
          </div>

          <div class="qa-card">
            <div class="qa-header">Q5 · Prediction Evidence</div>
            <div class="qa-question">${vData.q5_evidence.score}/100 (${vData.q5_evidence.tier})</div>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
              Absence reliability: ${(vData.q5_evidence.absence_reliability * 100).toFixed(0)}% · Mean step quality: ${(vData.q5_evidence.e_found * 100).toFixed(0)}%.
            </div>
          </div>
        </div>

        <!-- Limitations -->
        ${vData.limitations.length > 0 ? `
          <div class="limitations-box">
            <div class="limitations-title">Biological Caveats & Limitations</div>
            <ul class="limitations-list">
              ${vData.limitations.map(l => `<li>${escapeHtml(l)}</li>`).join('')}
            </ul>
          </div>
        ` : ''}
      `;

      this.vitaminCardsContainer.appendChild(card);

      // Render Metro Map in card
      const metroContainer = card.querySelector(`#metro-svg-${vCode}`);
      if (metroContainer) {
        const visualizer = new MetroMapVisualizer(metroContainer, (step) => {
          this.openStationDrawer(step, vData);
        });
        visualizer.render(vData);
      }
    });
  }

  openStationDrawer(step, vitaminData) {
    if (!this.drawer || !this.drawerContent) return;

    const matched = step.matched || [];
    const isPresent = step.status === 'present';
    const isGap = step.kind === 'gap';

    this.drawerContent.innerHTML = `
      <div class="drawer-header">
        <div>
          <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">${vitaminData.vitamin.name}</span>
          <h3 class="drawer-title">${escapeHtml(step.name)}</h3>
        </div>
        <button id="drawer-inner-close" class="btn btn-icon">&times;</button>
      </div>

      <div class="drawer-section">
        <div class="drawer-section-title">Enzyme Properties</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.88rem;">
          <div><strong>Step Code:</strong> <code>${step.code}</code></div>
          <div><strong>Branch:</strong> ${step.branch}</div>
          <div><strong>Kind:</strong> ${step.kind.toUpperCase()}</div>
          <div><strong>EC:</strong> ${step.ec ? `<code>${step.ec}</code>` : 'None'}</div>
          <div><strong>Status:</strong> <strong style="color:${isPresent ? 'var(--call-prototroph)' : 'var(--call-blocked)'};">${step.status.toUpperCase()}</strong></div>
        </div>
      </div>

      <div class="drawer-section">
        <div class="drawer-section-title">KEGG Orthology Options</div>
        <div style="display:flex; flex-wrap:wrap; gap:0.4rem;">
          ${(step.options || []).map(opt => `
            <a href="https://www.kegg.jp/entry/${opt.split('+')[0]}" target="_blank" rel="noopener" class="chip chip-not-required" style="text-decoration:none;">
              ${opt}
            </a>
          `).join('')}
        </div>
      </div>

      ${matched.length > 0 ? `
        <div class="drawer-section">
          <div class="drawer-section-title">Detected Evidence</div>
          ${matched.map(m => `
            <div style="background:var(--bg-subtle); padding:0.6rem; border-radius:var(--radius-md); font-size:0.85rem; margin-bottom:0.5rem;">
              <div><strong>KO:</strong> <code>${m.ko}</code> (Quality q = ${m.q})</div>
              ${m.warnings?.length > 0 ? `<div style="color:var(--call-blocked); font-size:0.8rem; margin-top:0.25rem;">${m.warnings.join(' ')}</div>` : ''}
            </div>
          `).join('')}
        </div>
      ` : ''}

      <div class="drawer-section">
        <div class="drawer-section-title">How to Verify (In Silico)</div>
        <p style="font-size:0.88rem; color:var(--text-secondary); line-height:1.5;">
          ${escapeHtml(step.verify_hint || 'BLAST the reference protein from KEGG against the genome assembly. Check contig breaks and pseudogenes.')}
        </p>
      </div>

      <div class="drawer-section">
        <div class="drawer-section-title">Wet-Lab Validation</div>
        <p style="font-size:0.88rem; color:var(--text-secondary); line-height:1.5;">
          ${escapeHtml(step.wet_lab_check || 'Perform defined minimal medium growth assay with and without vitamin supplementation.')}
        </p>
      </div>
    `;

    this.drawer.classList.add('active');
    this.drawerBackdrop.classList.add('active');

    document.getElementById('drawer-inner-close')?.addEventListener('click', () => this.closeDrawer());
  }

  closeDrawer() {
    this.drawer?.classList.remove('active');
    this.drawerBackdrop?.classList.remove('active');
  }

  async handleSave() {
    if (this.shareToken) {
      showToast('This analysis is already saved!', 'info');
      return;
    }

    const payload = JSON.parse(sessionStorage.getItem('vitagap_current_payload') || '{}');
    if (!payload.data) {
      showToast('Cannot save: missing analysis payload.', 'error');
      return;
    }

    try {
      this.btnSave.disabled = true;
      this.btnSave.textContent = 'Saving...';

      const saveResult = await api.saveAnalysis(payload);
      this.shareToken = saveResult.share_token;

      // Update browser URL
      const newUrl = `${window.location.pathname}?token=${this.shareToken}`;
      window.history.replaceState({}, '', newUrl);

      showToast('Report saved! Permalink updated.', 'success');
      this.btnSave.textContent = 'Saved ✓';
    } catch (err) {
      showToast(err.message, 'error');
      this.btnSave.disabled = false;
      this.btnSave.textContent = 'Save Permalink';
    }
  }

  async handleShare() {
    if (!this.shareToken) {
      await this.handleSave();
    }
    if (this.shareToken) {
      const shareUrl = window.location.href;
      copyToClipboard(shareUrl, 'Shareable permalink copied to clipboard!');
    }
  }

  handleExport(format) {
    if (!this.shareToken) {
      showToast('Please click "Save Permalink" first before downloading an export.', 'info');
      return;
    }

    const url = (format === 'csv')
      ? api.getExportCsvUrl(this.shareToken)
      : api.getExportJsonUrl(this.shareToken);

    window.open(url, '_blank');
  }
}

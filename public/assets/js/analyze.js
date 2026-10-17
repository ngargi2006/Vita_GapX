/**
 * Vita-Gap Analysis Wizard Controller
 */

import { api } from './api.js';
import { showToast } from './ui.js';

export class AnalyzeWizard {
  constructor() {
    this.currentStep = 1;
    this.resolutions = {};
    this.initElements();
    this.bindEvents();
    this.checkUrlParams();
  }

  initElements() {
    this.stepIndicators = document.querySelectorAll('.wizard-step');
    this.stepPanels = document.querySelectorAll('.wizard-panel');

    this.form = document.getElementById('analyze-form');
    this.organismInput = document.getElementById('organism');
    this.namingSelect = document.getElementById('naming');
    this.genomeTypeSelect = document.getElementById('genome_type');
    this.completenessInput = document.getElementById('completeness');
    this.scopeSelect = document.getElementById('scope');
    this.dataInput = document.getElementById('data');
    this.formatSelect = document.getElementById('format');
    this.fileUpload = document.getElementById('file-upload');
    this.dropzone = document.getElementById('dropzone');

    this.formatBadge = document.getElementById('format-preview-badge');
    this.btnNext = document.getElementById('btn-next');
    this.btnPrev = document.getElementById('btn-prev');
    this.btnSubmit = document.getElementById('btn-submit');

    // Ambiguity Modal
    this.ambiguityModal = document.getElementById('ambiguity-modal');
    this.ambiguityList = document.getElementById('ambiguity-list');
    this.btnApplyResolutions = document.getElementById('btn-apply-resolutions');
  }

  bindEvents() {
    this.btnNext?.addEventListener('click', () => this.nextStep());
    this.btnPrev?.addEventListener('click', () => this.prevStep());
    this.form?.addEventListener('submit', (e) => this.handleSubmit(e));

    // Live format detection on input
    this.dataInput?.addEventListener('input', () => this.detectFormatLive());

    // File drag and drop
    if (this.dropzone && this.fileUpload) {
      this.dropzone.addEventListener('click', () => this.fileUpload.click());
      this.dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        this.dropzone.classList.add('dragover');
      });
      this.dropzone.addEventListener('dragleave', () => this.dropzone.classList.remove('dragover'));
      this.dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        this.dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
          this.loadFile(e.dataTransfer.files[0]);
        }
      });
      this.fileUpload.addEventListener('change', () => {
        if (this.fileUpload.files.length > 0) {
          this.loadFile(this.fileUpload.files[0]);
        }
      });
    }

    // Demo Preset Buttons
    document.querySelectorAll('[data-demo]').forEach(btn => {
      btn.addEventListener('click', () => this.loadDemo(btn.dataset.demo));
    });
  }

  loadFile(file) {
    if (file.size > 5242880) {
      showToast('File exceeds 5 MB limit.', 'error');
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
      this.dataInput.value = e.target.result;
      this.detectFormatLive();
      showToast(`Loaded ${file.name} (${(file.size / 1024).toFixed(1)} KB)`, 'success');
    };
    reader.readAsText(file);
  }

  detectFormatLive() {
    const text = this.dataInput.value.trim();
    if (!text) {
      if (this.formatBadge) this.formatBadge.textContent = 'Auto-detect';
      return;
    }

    if (text.startsWith('#query') || text.includes('Preferred_name')) {
      if (this.formatBadge) this.formatBadge.textContent = 'Detected: eggNOG';
    } else if (text.includes('locus_tag') && text.includes('gene')) {
      if (this.formatBadge) this.formatBadge.textContent = 'Detected: Prokka';
    } else if (text.startsWith('* ') || text.includes('thrshld')) {
      if (this.formatBadge) this.formatBadge.textContent = 'Detected: KofamScan Detail';
    } else if (/^K\d{5}$/m.test(text)) {
      if (this.formatBadge) this.formatBadge.textContent = 'Detected: KO List';
    } else {
      if (this.formatBadge) this.formatBadge.textContent = 'Detected: Plain List';
    }
  }

  loadDemo(demoKey) {
    switch (demoKey) {
      case 'note':
        this.organismInput.value = 'Note Example Bacterium';
        this.namingSelect.value = 'generic';
        this.genomeTypeSelect.value = 'unknown';
        this.completenessInput.value = '';
        this.scopeSelect.value = 'open';
        this.dataInput.value = 'RibA -> RibB -> RibD -> RibH';
        break;
      case 'eco':
        this.organismInput.value = 'Escherichia coli str. K-12 substr. MG1655';
        this.namingSelect.value = 'ecoli';
        this.genomeTypeSelect.value = 'complete';
        this.completenessInput.value = '100';
        this.scopeSelect.value = 'closed';
        this.dataInput.value = 'ribA, ribB, ribD, ribE, ribC, ribF, thiC, thiE, thiG, panB, panC, pdxA, pdxJ, bioA, bioB, bioD, bioF, folA, folC, folE, folP';
        break;
      case 'bsu':
        this.organismInput.value = 'Bacillus subtilis subsp. subtilis str. 168';
        this.namingSelect.value = 'bsubtilis';
        this.genomeTypeSelect.value = 'complete';
        this.completenessInput.value = '100';
        this.scopeSelect.value = 'closed';
        this.dataInput.value = 'ribA, ribB, ribD, ribH, ribE, ribC, thiC, thiE, thiG, panB, panC, pdxS, pdxT, bioA, bioB, bioD, bioF, folA, folC, folE, folP';
        break;
      case 'mge':
        this.organismInput.value = 'Mycoplasma genitalium G37';
        this.namingSelect.value = 'generic';
        this.genomeTypeSelect.value = 'complete';
        this.completenessInput.value = '100';
        this.scopeSelect.value = 'closed';
        this.dataInput.value = 'MG_001, MG_002, MG_003 (No vitamin biosynthesis pathways)';
        break;
    }

    this.detectFormatLive();
    showToast(`Loaded ${demoKey} demo!`, 'success');
  }

  checkUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const demo = params.get('demo');
    if (demo) {
      this.loadDemo(demo);
    }
  }

  async nextStep() {
    if (this.currentStep === 1) {
      this.setStep(2);
    } else if (this.currentStep === 2) {
      if (!this.dataInput.value.trim()) {
        showToast('Please enter or paste your gene annotations.', 'error');
        return;
      }
      this.setStep(3);
    } else if (this.currentStep === 3) {
      // Step 3 -> Pre-flight ambiguity check via /api/parse
      await this.checkForAmbiguities();
    }
  }

  prevStep() {
    if (this.currentStep > 1) {
      this.setStep(this.currentStep - 1);
    }
  }

  setStep(stepNumber) {
    this.currentStep = stepNumber;

    this.stepIndicators.forEach((ind, idx) => {
      const stepIdx = idx + 1;
      ind.classList.toggle('active', stepIdx === stepNumber);
      ind.classList.toggle('completed', stepIdx < stepNumber);
    });

    this.stepPanels.forEach((p, idx) => {
      p.style.display = (idx + 1 === stepNumber) ? 'block' : 'none';
    });

    if (this.btnPrev) {
      this.btnPrev.style.display = (stepNumber > 1) ? 'inline-flex' : 'none';
    }
    if (this.btnNext) {
      this.btnNext.style.display = (stepNumber < 4) ? 'inline-flex' : 'none';
    }
    if (this.btnSubmit) {
      this.btnSubmit.style.display = (stepNumber === 4) ? 'inline-flex' : 'none';
    }
  }

  async checkForAmbiguities() {
    const payload = this.getFormData();
    try {
      this.btnNext.disabled = true;
      this.btnNext.textContent = 'Checking...';

      const parseResult = await api.parse({
        data: payload.data,
        format: payload.format,
        naming: payload.naming,
        resolve: this.resolutions,
      });

      const ambiguities = parseResult.ambiguities || [];
      if (ambiguities.length > 0) {
        this.renderAmbiguityModal(ambiguities);
      } else {
        this.setStep(4);
      }
    } catch (err) {
      showToast(err.message, 'error');
      this.setStep(4); // proceed anyway on network error
    } finally {
      this.btnNext.disabled = false;
      this.btnNext.textContent = 'Next';
    }
  }

  renderAmbiguityModal(ambiguities) {
    if (!this.ambiguityModal || !this.ambiguityList) return;
    this.ambiguityList.innerHTML = '';

    ambiguities.forEach(amb => {
      const item = document.createElement('div');
      item.className = 'ambiguity-item';
      item.innerHTML = `
        <h4 style="margin-bottom:0.4rem; color:var(--call-blocked);">Symbol: <code>${amb.symbol}</code></h4>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.75rem;">
          This gene symbol has different definitions across bacterial naming traditions. Please select the intended function:
        </p>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          ${amb.options.map(opt => `
            <label style="display:flex; align-items:flex-start; gap:0.5rem; font-size:0.88rem; cursor:pointer;">
              <input type="radio" name="res_${amb.symbol}" value="${opt.ko}" style="margin-top:0.25rem;">
              <div>
                <strong>${opt.ko}</strong> (${opt.naming.toUpperCase()}) — ${opt.definition}
              </div>
            </label>
          `).join('')}
        </div>
      `;
      this.ambiguityList.appendChild(item);
    });

    this.ambiguityModal.classList.add('active');

    this.btnApplyResolutions.onclick = () => {
      ambiguities.forEach(amb => {
        const selected = document.querySelector(`input[name="res_${amb.symbol}"]:checked`);
        if (selected) {
          this.resolutions[amb.symbol] = selected.value;
        }
      });
      this.ambiguityModal.classList.remove('active');
      this.setStep(4);
    };
  }

  getFormData() {
    const selectedVitamins = [];
    document.querySelectorAll('input[name="vitamins"]:checked').forEach(cb => {
      selectedVitamins.push(cb.value);
    });

    return {
      organism: this.organismInput.value.trim(),
      naming: this.namingSelect.value,
      genome_type: this.genomeTypeSelect.value,
      completeness: this.completenessInput.value ? parseFloat(this.completenessInput.value) : null,
      scope: this.scopeSelect.value,
      format: this.formatSelect.value,
      data: this.dataInput.value.trim(),
      vitamins: selectedVitamins,
      resolve: this.resolutions,
      store_input: document.getElementById('store_input')?.checked ?? false,
    };
  }

  async handleSubmit(e) {
    e.preventDefault();
    const payload = this.getFormData();

    try {
      this.btnSubmit.disabled = true;
      this.btnSubmit.textContent = 'Analyzing...';

      // Call API analyze (and save if store permalink is requested)
      const result = await api.analyze(payload);

      // Store in sessionStorage for instant rendering
      sessionStorage.setItem('vitagap_current_report', JSON.stringify(result));
      sessionStorage.setItem('vitagap_current_payload', JSON.stringify(payload));

      window.location.href = '/report.html';
    } catch (err) {
      showToast(err.message, 'error');
      this.btnSubmit.disabled = false;
      this.btnSubmit.textContent = 'Run Gap Analysis';
    }
  }
}

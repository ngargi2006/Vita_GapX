/**
 * Vita-Gap API Client (ES Module)
 */

export class ApiClient {
  constructor(baseUrl = '/api') {
    this.baseUrl = baseUrl;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      'Accept': 'application/json',
      ...options.headers,
    };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    try {
      const response = await fetch(url, { ...options, headers });
      const data = await response.json();

      if (!response.ok || !data.ok) {
        const error = new Error(data.error?.message || `HTTP ${response.status} Error`);
        error.code = data.error?.code || 'SERVER_ERROR';
        error.details = data.error?.details || {};
        error.status = response.status;
        throw error;
      }

      return data.data;
    } catch (err) {
      if (!err.code) {
        err.code = 'NETWORK_ERROR';
      }
      throw err;
    }
  }

  getHealth() {
    return this.request('/health');
  }

  getVitamins() {
    return this.request('/vitamins');
  }

  getPathway(code) {
    return this.request(`/pathways/${encodeURIComponent(code)}`);
  }

  lookup(query, naming = 'generic') {
    return this.request(`/lookup?q=${encodeURIComponent(query)}&naming=${encodeURIComponent(naming)}`);
  }

  parse(payload) {
    return this.request('/parse', {
      method: 'POST',
      body: payload,
    });
  }

  analyze(payload) {
    return this.request('/analyze', {
      method: 'POST',
      body: payload,
    });
  }

  saveAnalysis(payload) {
    return this.request('/analyses', {
      method: 'POST',
      body: payload,
    });
  }

  getSavedAnalysis(shareToken) {
    return this.request(`/analyses/${encodeURIComponent(shareToken)}`);
  }

  deleteAnalysis(shareToken, deleteToken) {
    return this.request(`/analyses/${encodeURIComponent(shareToken)}`, {
      method: 'DELETE',
      headers: {
        'X-Delete-Token': deleteToken,
      },
    });
  }

  getExportJsonUrl(shareToken) {
    return `${this.baseUrl}/export/${encodeURIComponent(shareToken)}.json`;
  }

  getExportCsvUrl(shareToken) {
    return `${this.baseUrl}/export/${encodeURIComponent(shareToken)}.csv`;
  }
}

export const api = new ApiClient();

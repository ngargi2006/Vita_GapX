# Vita-Gap REST API Documentation

Vita-Gap provides a research-grade RESTful API for bacterial vitamin biosynthetic pathway gap analysis. All endpoints return responses adhering to a standardized JSON envelope.

---

## 1. Base URL & Protocol

- **Local:** `http://localhost:8080/api`
- **Protocol:** HTTP/1.1 or HTTP/2
- **Data Format:** JSON (`application/json; charset=utf-8`)

---

## 2. Standard Response Envelope

Every API response follows this unified structure:

```json
{
  "ok": true,
  "data": { ... },
  "error": null,
  "meta": {
    "request_id": "9f7b1e4a3d2c8b01",
    "engine_version": "1.0.0",
    "kb_version": "1.0.0",
    "ms": 12
  }
}
```

In the event of an error:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "INVALID_INPUT",
    "message": "Missing required parameter 'data'."
  },
  "meta": {
    "request_id": "9f7b1e4a3d2c8b01",
    "engine_version": "1.0.0",
    "kb_version": "1.0.0",
    "ms": 2
  }
}
```

### Standard Error Codes
| HTTP Status | Error Code | Description |
|---|---|---|
| `400` | `INVALID_INPUT` | Malformed parameters, invalid values, or empty input data |
| `400` | `AMBIGUOUS_TOKENS` | Gene symbols have conflicting meanings across taxonomic traditions |
| `413` | `TOO_LARGE` | Input exceeds the 5 MB or 50,000 line limit |
| `429` | `RATE_LIMITED` | Rate limit of 60 analyses/hour per salted IP hash exceeded |
| `404` | `NOT_FOUND` | Requested pathway or saved analysis share token was not found |
| `500` | `SERVER_ERROR` | Internal server or unhandled runtime error |

---

## 3. Endpoints Overview

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/health` | Health check, system architecture, and version info |
| `GET` | `/api/vitamins` | List of all 9 vitamins, active forms, and pathway variants |
| `GET` | `/api/pathways/{code}` | Detailed breakdown of a specific pathway and its steps |
| `GET` | `/api/lookup` | Symbol, KO, and EC lookup with cross-convention ambiguity checks |
| `POST` | `/api/parse` | Fast dry-run parser: token extraction and ambiguity detection |
| `POST` | `/api/analyze` | Full in-memory analysis report (without saving) |
| `POST` | `/api/analyses` | Full analysis report with persistent saving (returns share token) |
| `GET` | `/api/analyses/{token}` | Load a previously saved analysis report |
| `DELETE`| `/api/analyses/{token}` | Delete a saved report (requires delete token) |
| `GET` | `/api/export/{token}.json` | Download report as a JSON file attachment |
| `GET` | `/api/export/{token}.csv` | Download summary table as a CSV spreadsheet |

---

## 4. Endpoint Reference

### 4.1 Health Check
**`GET /api/health`**

Returns service liveness and current software versions.

```bash
curl -s http://localhost:8080/api/health
```

---

### 4.2 List Vitamins
**`GET /api/vitamins`**

Returns metadata for the 9 vitamins analyzed by Vita-Gap: B1, B2, B3, B5, B6, B7, B9, B12, and K2.

```bash
curl -s http://localhost:8080/api/vitamins
```

---

### 4.3 Get Pathway Definition
**`GET /api/pathways/{code}`**

Retrieves steps, EC numbers, and KEGG Orthology options for a specific pathway (e.g., `B2_DENOVO`, `B1_TYR`, `K2_CLASSICAL`).

```bash
curl -s http://localhost:8080/api/pathways/B2_DENOVO
```

---

### 4.4 Gene / KO / EC Lookup
**`GET /api/lookup?q={query}&naming={convention}`**

Performs lookup of any gene symbol, KO ID, or EC number against the Vita-Gap Knowledge Base.

**Query Parameters:**
- `q` (required): Symbol (e.g. `ribC`, `ribE`), KO (`K01497`), or EC (`3.5.4.25`).
- `naming` (optional): `generic` (default), `ecoli`, `bsubtilis`, or `other`.

```bash
curl -s "http://localhost:8080/api/lookup?q=ribC&naming=generic"
```

---

### 4.5 Dry-Run Parse
**`POST /api/parse`**

Extracts tokens, determines input format, and identifies any taxonomic ambiguities before running full pathway scoring.

**Request Payload:**
```json
{
  "data": "RibA -> RibB -> RibC",
  "naming": "generic",
  "format": "auto"
}
```

---

### 4.6 Run Full Analysis
**`POST /api/analyze`**

Executes complete pathway evaluation, rule evaluation, and evidence scoring without saving to disk.

**Request Payload:**
```json
{
  "organism": "Bacillus subtilis 168",
  "naming": "bsubtilis",
  "genome_type": "complete",
  "completeness": 100.0,
  "scope": "closed",
  "format": "auto",
  "data": "RibA -> RibB -> RibD -> RibH -> RibE",
  "vitamins": ["B2"],
  "resolve": {}
}
```

---

### 4.7 Save Analysis
**`POST /api/analyses`**

Performs full analysis and persists the report to generate a permanent shareable permalink and a deletion token.

**Request Payload:**
Same as `/api/analyze`, with optional `"store_input": true` to persist raw input text.

**Response:**
```json
{
  "ok": true,
  "data": {
    "id": "670da2a9b401e82a93ff01c2",
    "share_token": "a1b2c3d4e5f60718293a4b5c6d7e8f90",
    "delete_token": "09f8e7d6c5b4a39281706f5e4d3c2b1a",
    "share_url": "/report.html?token=a1b2c3d4e5f60718293a4b5c6d7e8f90",
    "report": { ... }
  }
}
```

---

### 4.8 Retrieve Saved Analysis
**`GET /api/analyses/{share_token}`**

Retrieves a previously saved report by its 32-character hex share token.

---

### 4.9 Delete Saved Analysis
**`DELETE /api/analyses/{share_token}`**

Permanently deletes a saved analysis.

**Authentication:** Provide the 32-character `delete_token` via:
- HTTP Header: `X-Delete-Token: {delete_token}`
- Query parameter: `?delete_token={delete_token}`
- JSON body: `{"delete_token": "{delete_token}"}`

---

### 4.10 Export JSON / CSV
- **`GET /api/export/{share_token}.json`**: Downloads full report JSON with `Content-Disposition: attachment`.
- **`GET /api/export/{share_token}.csv`**: Downloads summary table as a CSV spreadsheet.

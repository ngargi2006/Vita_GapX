-- Vita-Gap Database Schema (MySQL 8)
-- UTF-8mb4, InnoDB, strict foreign keys

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS batch_members;
DROP TABLE IF EXISTS batches;
DROP TABLE IF EXISTS analysis_results;
DROP TABLE IF EXISTS analyses;
DROP TABLE IF EXISTS refs;
DROP TABLE IF EXISTS gene_aliases;
DROP TABLE IF EXISTS ko_terms;
DROP TABLE IF EXISTS step_options;
DROP TABLE IF EXISTS steps;
DROP TABLE IF EXISTS pathways;
DROP TABLE IF EXISTS vitamins;
DROP TABLE IF EXISTS kb_versions;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE kb_versions (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(20) NOT NULL UNIQUE,
  kegg_release VARCHAR(60),
  sha256 CHAR(64) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vitamins (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(4) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL,
  active_forms VARCHAR(80),
  summary TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pathways (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kb_version_id SMALLINT UNSIGNED NOT NULL,
  vitamin_id TINYINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  role ENUM('de_novo','precursor','activation','salvage','transport','dependency') NOT NULL,
  kegg_modules VARCHAR(60),
  loci_k TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_pw (kb_version_id, code),
  FOREIGN KEY (kb_version_id) REFERENCES kb_versions(id) ON DELETE CASCADE,
  FOREIGN KEY (vitamin_id) REFERENCES vitamins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE steps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pathway_id SMALLINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  pos TINYINT UNSIGNED NOT NULL,
  branch VARCHAR(30),
  name VARCHAR(120) NOT NULL,
  ec VARCHAR(60),
  kind ENUM('key','normal','low','gap','activation','optional') NOT NULL DEFAULT 'normal',
  note TEXT,
  UNIQUE KEY uq_step (pathway_id, code),
  FOREIGN KEY (pathway_id) REFERENCES pathways(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE step_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  step_id INT UNSIGNED NOT NULL,
  kos VARCHAR(80) NOT NULL,
  label VARCHAR(80),
  FOREIGN KEY (step_id) REFERENCES steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ko_terms (
  ko CHAR(6) PRIMARY KEY,
  symbol VARCHAR(60),
  definition VARCHAR(255),
  ec VARCHAR(80),
  verified_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gene_aliases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alias VARCHAR(40) NOT NULL,
  ko CHAR(6) NOT NULL,
  naming ENUM('generic','ecoli','bsubtilis','other') NOT NULL DEFAULT 'generic',
  source VARCHAR(80),
  INDEX ix_alias (alias)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refs (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  short_ref VARCHAR(60),
  citation TEXT,
  doi VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analyses (
  id CHAR(26) PRIMARY KEY,
  share_token CHAR(32) NOT NULL UNIQUE,
  delete_hash CHAR(64) NOT NULL,
  created_at DATETIME(3) NOT NULL,
  expires_at DATETIME NULL,
  kb_version_id SMALLINT UNSIGNED NOT NULL,
  engine_version VARCHAR(20) NOT NULL,
  organism VARCHAR(160),
  genome_type ENUM('complete','draft','mag','unknown') NOT NULL,
  completeness DECIMAL(5,2) NULL,
  input_scope ENUM('closed','open') NOT NULL,
  input_format VARCHAR(30),
  input_sha256 CHAR(64) NOT NULL,
  input_text MEDIUMTEXT NULL,
  params JSON,
  report JSON NOT NULL,
  FOREIGN KEY (kb_version_id) REFERENCES kb_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analysis_results (
  analysis_id CHAR(26) NOT NULL,
  vitamin_code VARCHAR(4) NOT NULL,
  best_pathway VARCHAR(40),
  completeness DECIMAL(5,4),
  key_coverage DECIMAL(5,4),
  call_code VARCHAR(30),
  evidence TINYINT UNSIGNED,
  PRIMARY KEY (analysis_id, vitamin_code),
  FOREIGN KEY (analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE batches (
  id CHAR(26) PRIMARY KEY,
  share_token CHAR(32) UNIQUE,
  delete_hash CHAR(64),
  name VARCHAR(120),
  created_at DATETIME(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE batch_members (
  batch_id CHAR(26) NOT NULL,
  analysis_id CHAR(26) NOT NULL,
  PRIMARY KEY (batch_id, analysis_id),
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  FOREIGN KEY (analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  ip_hash CHAR(64) NOT NULL,
  window_start DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (ip_hash, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean the copied registry year-tables into one document list.
-- Source tables (documents, archives*) stay in cdc_registry for reference.
-- Current year is the live `documents` table for 2026.
-- Archives use one canonical table per year so overlapping copies are not counted twice.

SET NAMES utf8mb4;
SET SESSION sql_mode = '';

DROP TABLE IF EXISTS registry_documents;

CREATE TABLE registry_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  archive_year SMALLINT NOT NULL,
  is_current TINYINT NOT NULL DEFAULT 0,
  legacy_id INT NOT NULL,
  source_table VARCHAR(40) NOT NULL,
  ref_no VARCHAR(100) NOT NULL,
  division_id INT NOT NULL,
  document_type_id INT NOT NULL,
  addressed_to VARCHAR(100) NOT NULL,
  document_date DATE NULL,
  date_recorded DATE NULL,
  description TEXT NOT NULL,
  signed_by VARCHAR(100) NOT NULL,
  status_id INT NOT NULL,
  recorded_by VARCHAR(100) NOT NULL DEFAULT '',
  comments TEXT NULL,
  file_name VARCHAR(200) NOT NULL DEFAULT '',
  recorded_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_year_current (archive_year, is_current),
  KEY idx_recorded (date_recorded),
  KEY idx_ref (ref_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO registry_documents (
  archive_year, is_current, legacy_id, source_table, ref_no, division_id, document_type_id,
  addressed_to, document_date, date_recorded, description, signed_by, status_id,
  recorded_by, comments, file_name, recorded_at
)
SELECT
  YEAR(d.document_date), 1, d.document_id, 'documents', d.ref_no, d.division_id, d.document_type_id,
  d.addresed_to,
  IF(d.document_date = '0000-00-00', NULL, d.document_date),
  IF(d.date_recorded = '0000-00-00', NULL, d.date_recorded),
  d.description, d.signed_by, d.status_id,
  COALESCE(u.name, ''), d.comments, d.file_name,
  IF(d.date_time = '0000-00-00 00:00:00', NULL, d.date_time)
FROM documents d
LEFT JOIN users u ON u.user_id = d.user_id
WHERE d.document_date >= '2026-01-01' AND d.document_date < '2027-01-01';

INSERT INTO registry_documents (
  archive_year, is_current, legacy_id, source_table, ref_no, division_id, document_type_id,
  addressed_to, document_date, date_recorded, description, signed_by, status_id,
  recorded_by, comments, file_name, recorded_at
)
SELECT
  2025, 0, d.document_id, 'documents', d.ref_no, d.division_id, d.document_type_id,
  d.addresed_to,
  IF(d.document_date = '0000-00-00', NULL, d.document_date),
  IF(d.date_recorded = '0000-00-00', NULL, d.date_recorded),
  d.description, d.signed_by, d.status_id,
  COALESCE(u.name, ''), d.comments, d.file_name,
  IF(d.date_time = '0000-00-00 00:00:00', NULL, d.date_time)
FROM documents d
LEFT JOIN users u ON u.user_id = d.user_id
WHERE d.document_date >= '2025-01-01' AND d.document_date < '2026-01-01';

INSERT INTO registry_documents (
  archive_year, is_current, legacy_id, source_table, ref_no, division_id, document_type_id,
  addressed_to, document_date, date_recorded, description, signed_by, status_id,
  recorded_by, comments, file_name, recorded_at
)
SELECT
  YEAR(d.document_date), 0, d.document_id, 'archives25', d.ref_no, d.division_id, d.document_type_id,
  d.addresed_to,
  IF(d.document_date = '0000-00-00', NULL, d.document_date),
  IF(d.date_recorded = '0000-00-00', NULL, d.date_recorded),
  d.description, d.signed_by, d.status_id,
  COALESCE(u.name, ''), d.comments, d.file_name,
  IF(d.date_time = '0000-00-00 00:00:00', NULL, d.date_time)
FROM archives25 d
LEFT JOIN users u ON u.user_id = d.user_id
WHERE d.document_date >= '2025-01-01' AND d.document_date < '2026-01-01';

INSERT INTO registry_documents (
  archive_year, is_current, legacy_id, source_table, ref_no, division_id, document_type_id,
  addressed_to, document_date, date_recorded, description, signed_by, status_id,
  recorded_by, comments, file_name, recorded_at
)
SELECT
  2024, 0, d.document_id, 'archives24', d.ref_no, d.division_id, d.document_type_id,
  d.addresed_to,
  IF(d.document_date = '0000-00-00', NULL, d.document_date),
  IF(d.date_recorded = '0000-00-00', NULL, d.date_recorded),
  d.description, d.signed_by, d.status_id,
  COALESCE(u.name, ''), d.comments, d.file_name,
  IF(d.date_time = '0000-00-00 00:00:00', NULL, d.date_time)
FROM archives24 d
LEFT JOIN users u ON u.user_id = d.user_id
WHERE d.document_date >= '2024-01-01' AND d.document_date < '2025-01-01';

INSERT INTO registry_documents (
  archive_year, is_current, legacy_id, source_table, ref_no, division_id, document_type_id,
  addressed_to, document_date, date_recorded, description, signed_by, status_id,
  recorded_by, comments, file_name, recorded_at
)
SELECT
  2023, 0, d.document_id, 'archives22', d.ref_no, d.division_id, d.document_type_id,
  d.addresed_to,
  IF(d.document_date = '0000-00-00', NULL, d.document_date),
  IF(d.date_recorded = '0000-00-00', NULL, d.date_recorded),
  d.description, d.signed_by, d.status_id,
  COALESCE(u.name, ''), d.comments, d.file_name,
  IF(d.date_time = '0000-00-00 00:00:00', NULL, d.date_time)
FROM archives22 d
LEFT JOIN users u ON u.user_id = d.user_id
WHERE d.document_date >= '2023-01-01' AND d.document_date < '2024-01-01';

INSERT INTO registry_documents (
  archive_year, is_current, legacy_id, source_table, ref_no, division_id, document_type_id,
  addressed_to, document_date, date_recorded, description, signed_by, status_id,
  recorded_by, comments, file_name, recorded_at
)
SELECT
  2022, 0, d.document_id, 'archives', d.ref_no, d.division_id, d.document_type_id,
  d.addresed_to,
  IF(d.document_date = '0000-00-00', NULL, d.document_date),
  IF(d.date_recorded = '0000-00-00', NULL, d.date_recorded),
  d.description, d.signed_by, d.status_id,
  COALESCE(u.name, ''), d.comments, d.file_name,
  IF(d.date_time = '0000-00-00 00:00:00', NULL, d.date_time)
FROM archives d
LEFT JOIN users u ON u.user_id = d.user_id
WHERE d.document_date >= '2022-01-01' AND d.document_date < '2023-01-01';

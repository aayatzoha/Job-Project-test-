-- Migration: Add quiz file upload and hosting features to assessments table
-- Run this SQL to add support for quiz file uploads and public hosting

ALTER TABLE assessments 
ADD COLUMN IF NOT EXISTS quiz_file VARCHAR(500) NULL COMMENT 'Path to uploaded quiz file',
ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE COMMENT 'Whether quiz is publicly accessible',
ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) NULL COMMENT 'Unique token for public quiz access',
ADD INDEX IF NOT EXISTS idx_share_token (share_token),
ADD INDEX IF NOT EXISTS idx_is_public (is_public);


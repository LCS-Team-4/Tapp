-- ==== 010_add_google_sheets_webhook_url.sql ====
-- Adds the Google Sheets Web App URL to the settings table so admins can
-- configure the sync target from the admin portal without touching code.
-- The URL is the Apps Script Web App deployment URL (e.g.
-- https://script.google.com/macros/s/AKfycb.../exec) that appends rows
-- to the Google Sheet.

ALTER TABLE `settings`
  ADD COLUMN `google_sheets_webhook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `google_sheets_sync_enabled`;
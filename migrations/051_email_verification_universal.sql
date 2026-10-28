-- ============================================================
--  Migration 051: generalize the Gmail-only mailbox check to any domain
-- ============================================================
-- The real-time mailbox-existence check (migration 050) was scoped to
-- @gmail.com addresses only. It's now generalized to validate any email
-- domain (Outlook, Yahoo, custom business domains, ...), so the cache table
-- is renamed to drop the Gmail-specific name — no column changes, the
-- `source` column already stores generic provider names
-- ('abstract' | 'emailverify' | 'mailboxlayer' | 'fail_open').
RENAME TABLE gmail_verification_cache TO email_verification_cache;

-- Caches the result of a "does this Gmail inbox actually exist?" lookup
-- (api/verify_gmail.php, includes/gmail_verify.php) so the same address is
-- never checked against Abstract API / ZeroBounce more than once within the
-- cache window — those free tiers are only 100 checks/month each, and this
-- table is what protects that quota across different admins/days, not just
-- one browser tab's own in-memory cache. Only ever populated for @gmail.com
-- addresses checked from the admin "Add User" modal (modules/admin/users.php).
CREATE TABLE IF NOT EXISTS gmail_verification_cache (
    email       VARCHAR(190) NOT NULL,
    exists_flag TINYINT(1)   NOT NULL,   -- 1 = mailbox confirmed to exist, 0 = confirmed not to
    source      VARCHAR(20)  NOT NULL,   -- 'abstract' | 'emailverify' | 'mailboxlayer' | 'fail_open'
    checked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

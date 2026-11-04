-- For older databases ONLY: run if users is missing these two columns.
-- Fresh databases already include them in fix-and-exchange.sql.
USE fix_and_exchange;
ALTER TABLE users
    ADD COLUMN intake_code VARCHAR(50),
    ADD COLUMN academic_program VARCHAR(100);

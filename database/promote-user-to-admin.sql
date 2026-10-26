-- Create the account first through the normal registration page.
-- Replace the email below with the account that should be the administrator.
UPDATE users
SET role = 'Admin'
WHERE email = 'admin@example.com';

-- Confirm the change.
SELECT user_id, full_name, email, role, account_status
FROM users
WHERE email = 'admin@example.com';

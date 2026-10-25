# Fix and Exchange

A school project using PHP, HTML, external CSS, JavaScript, and MySQL.

This is a starter structure. The shared stylesheet, database connection file, and SQL table definitions are prepared. The role CSS files contain comments only, and `main.js` is empty. Most PHP files contain purpose comments only, so the website pages are still blank. Login, access checks, forms, and saving records are not implemented yet.

The script link is in `includes/footer.php`. Pages still need to include the shared header and footer. The CSS link has not been added yet. The script link currently assumes the project is served at `/fix-and-exchange/`.

## Folders and team responsibilities

| Folder | Purpose / responsibility |
| --- | --- |
| `src/admin/` | Admin member: accounts, categories, listings, reports, volunteer approvals, repair monitoring, and statistics. |
| `src/buyer-seller/` | Buyer/seller member: marketplace, listings, buy/trade/giveaway offers, and listing reports. |
| `src/repair-requester/` | Repair requester member: submit requests and track repairs. |
| `src/repair-volunteer/` | Repair volunteer member: applications, available jobs, and repair progress. |
| `src/auth/` | Shared registration, login, and logout. |
| `src/account/` | Shared campus homepage, search, and profiles. |
| `src/messages/` | Shared conversations for listings and repairs. |
| `src/sustainability/` | Shared dashboard, activity, points, and leaderboard. |
| `includes/` | Reused header, footer, navigation, and future access checks. |
| `config/` | Shared MySQL connection settings. |
| `assets/css/` | Shared styles in `style.css`, with a separate CSS file for each main part. |
| `assets/js/` | Empty `main.js` file for JavaScript features to add later. |
| `assets/images/` | Website images such as the logo. |
| `uploads/listings/` | User-uploaded listing images. |
| `uploads/repairs/` | User-uploaded repair images. |
| `database/` | Setup file with 13 tables and their relationships. |

Agree within the group who will handle each shared part. Use one copy of shared pages instead of duplicating them in each role folder.

## Where to begin

1. Compare `database/fix-and-exchange.sql` with the ERD and data dictionary. The SQL uses `VARCHAR` for status and type fields; the current data dictionary still lists `ENUM`. Update the documents to match the final SQL before submission.
2. Check the connection settings in `config/database.php` and set up the database. `IF NOT EXISTS` does not update tables that already exist. The revised SQL was tested in a separate temporary database; it has not been applied to your existing database.
3. Add the shared page layout, external CSS link, and footer include. Check the CSS and JavaScript paths from the homepage and the role pages.
4. Build registration, login, and the checks in `includes/auth-check.php`, then build each member's pages.
5. Connect shared messaging and sustainability pages to the main parts.

The root `index.php` is the website entry page. Decide its page content when building the homepage.

## Simple coding rules

- Keep PHP form handling at the top of each page and HTML below it.
- Use normal HTML forms and PHP page links.
- Keep styling in the external CSS files. Give each CSS selector its own rule.
- Reuse files with `include` or `require`; no framework or separate API is needed.
- From a page such as `src/buyer-seller/marketplace.php`, a shared file can be loaded with `require __DIR__ . '/../../config/database.php';`.
- Folder names organise files. They do not protect pages: add login, role, and ownership checks when implementing features.
- Students can be both buyers and sellers. These folders do not introduce extra database account roles.
- Check form values in PHP, including allowed statuses, item types, and non-negative prices.
- Check who owns a record before allowing changes. A student must not make an offer for their own listing, and only conversation participants may send or read its messages.
- Use `password_hash()` and `password_verify()` for passwords, and prepared statements for SQL that uses form values.

## Reference

Current reference files are in the parent RWDD folder: `proposal.docx`, `RWDD Documentation.pdf`, `Buyer-Seller-Data-Dictionary.docx`, and `AAPP012-4-2-RWDD Group Assignment.pdf`. The structure includes all four main roles, shared messaging, sustainability tracking, and buy, trade, and giveaway offers.

The assignment requires working JavaScript features. The empty JavaScript file is only a placeholder. Record external assistance under “References / Tools Used,” and make sure each group member can explain their final code.

Existing documents, diagrams, scripts, and the `output/` folder remain in their original locations.

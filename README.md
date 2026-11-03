# Fix and Exchange

A school project using PHP, HTML, external CSS, JavaScript, and MySQL.

The Buyer/Seller pages now support marketplace filters, listings and photos, offers, listing messages, and reports. Login, registration, the student dashboard, and the shared layout use the existing team code. Most admin, repair, profile, and sustainability pages are still placeholders.

Buyer/Seller layouts follow the seven images in `../output/ERD-matched-wireframes/`, using the existing shared green style in `assets/css/style.css`. The existing shared header includes Font Awesome; no new packages or external services were added for Buyer/Seller.

## Folders and team responsibilities

| Folder | Purpose / responsibility |
| --- | --- |
| `src/admin/` | Admin member: accounts, categories, listings, reports, volunteer approvals, repair monitoring, and statistics. |
| `src/buyer-seller/` | Buyer/seller member: marketplace, listings, buy/trade/giveaway offers, and listing reports. |
| `src/repair-requester/` | Repair requester member: submit requests and track repairs. |
| `src/repair-volunteer/` | Repair volunteer member: applications, available jobs, and repair progress. |
| `src/auth/` | Shared registration, login, and logout. |
| `src/account/` | Shared campus homepage, search, and profiles. |
| `src/messages/` | Working listing conversations; repair messaging remains for the repair member. |
| `src/sustainability/` | Shared dashboard, activity, points, and leaderboard. |
| `includes/` | Shared header, footer, access checks, and small Buyer/Seller helpers. |
| `config/` | Shared MySQL connection settings. |
| `assets/css/` | Shared styles in `style.css`, with a separate CSS file for each main part. |
| `assets/js/` | Shared navigation and basic Buyer/Seller photo and form controls. |
| `assets/images/` | Website images such as the logo. |
| `uploads/listings/` | User-uploaded listing images. |
| `uploads/repairs/` | User-uploaded repair images. |
| `database/` | Setup file with 13 tables and their relationships. |

Agree within the group who will handle each shared part. Use one copy of shared pages instead of duplicating them in each role folder.

## Run locally

1. Start your local MySQL server. Check `config/database.php` for the connection settings.
2. For a fresh database, import `database/fix-and-exchange.sql`. For an existing database, inspect its columns first: `IF NOT EXISTS` does not update old tables. The optional `update-user-profile-fields.sql` adds the two missing profile columns used by login; run it only if both are missing. `update-listing-price.sql` allows a NULL price for Exchange listings. These updates were applied to the older local database during testing.
3. Run `database/seed-categories.sql` if categories are empty. Running it again leaves existing categories unchanged.
4. From the parent RWDD folder, run `php -d upload_max_filesize=5M -d post_max_size=20M -S 127.0.0.1:8080 -t .` and open `http://127.0.0.1:8080/fix-and-exchange/`. If using Apache/MAMP, serve the project at `/fix-and-exchange/` and set the same upload limits in PHP settings.
5. Register a student account and sign in. Use two student accounts to try selling and buying. PHP needs MySQLi, mysqlnd, mbstring, and fileinfo, which are available in the tested local PHP installation.

## Buyer/Seller flow

- Create a Sale, Exchange, or Giveaway listing. Photos are optional, with up to three JPEG/PNG/WebP images, each at most 5 MB. Use Edit to change an available listing and delete or add photos.
- Buyers search available items, open details, create the matching offer type, and contact the seller through Messages. Trade offers require an item description; there is no online payment.
- Sellers accept or reject Incoming Offers. Buyers can cancel Pending offers under Sent Offers. Accepting one offer rejects the other pending offers and prevents further offers for that listing.
- After agreeing through Messages, the seller uses My Listings to mark the listing Pending, then Completed after handover. Accepted agreements cannot be edited. Reject pending offers before changing a listing's type or price.
- Remove changes an active listing to Removed and cancels unfinished offers. It keeps history. Completed and Removed listings are read-only. Existing conversation participants can still read and send messages.
- Listing reports are saved for admin review. No points are awarded by this part; the sustainability member owns those rules.

## Validation

Live local checks passed for all three listing types, image upload/deletion, offer acceptance/rejection/cancellation, handover completion, removal history, messages, reports, filters, and ownership checks. Two PHP workers tested competing offer acceptance: exactly one offer was accepted. A temporary database failure test confirmed that failed image saves roll back the listing and remove uploaded files. The temporary trigger was removed afterward.

PHP syntax and JavaScript syntax checks passed. Screenshots and check results are in `../output/Buyer-Seller-Validation/`. Shared login, dashboard, landing page, and volunteer application were checked. Disposable test accounts and records are removed after validation.

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

The assignment requires working JavaScript features; basic photo previews, thumbnail selection, price fields, and shared navigation are implemented. Record external assistance under “References / Tools Used,” and make sure each group member can explain their final code.

Existing documents, diagrams, scripts, and the `output/` folder remain in their original locations.

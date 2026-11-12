<?php
// Category Management: Manage item and repair categories.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

// Handle POST actions: Add, Edit, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_category') {
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            redirect_message('adminCategories.php', 'Please provide a category name up to 100 characters.', 'danger');
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO categories (category_name, description) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $name, $desc);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message('adminCategories.php', "Category '$name' created successfully.", 'success');
        }
    } elseif ($action === 'edit_category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($categoryId <= 0 || $name === '' || mb_strlen($name) > 100) {
            redirect_message('adminCategories.php', 'Please provide a valid category name.', 'danger');
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $desc, $categoryId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message('adminCategories.php', "Category updated successfully.", 'success');
        }
    } elseif ($action === 'delete_category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($categoryId > 0) {
            // Check usage
            $chkStmt = mysqli_prepare(
                $conn,
                "SELECT
                    (SELECT COUNT(*) FROM item_listings WHERE category_id = ?) AS listing_count,
                    (SELECT COUNT(*) FROM repair_requests WHERE category_id = ?) AS repair_count"
            );
            mysqli_stmt_bind_param($chkStmt, 'ii', $categoryId, $categoryId);
            mysqli_stmt_execute($chkStmt);
            $usage = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
            mysqli_stmt_close($chkStmt);

            $listings = (int) ($usage['listing_count'] ?? 0);
            $repairs = (int) ($usage['repair_count'] ?? 0);

            if ($listings > 0 || $repairs > 0) {
                redirect_message('adminCategories.php', "Cannot delete category: It is currently used by $listings listing(s) and $repairs repair request(s).", 'danger');
            } else {
                $delStmt = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id = ?");
                mysqli_stmt_bind_param($delStmt, 'i', $categoryId);
                mysqli_stmt_execute($delStmt);
                mysqli_stmt_close($delStmt);

                redirect_message('adminCategories.php', "Category deleted successfully.", 'success');
            }
        }
    }
}

// Fetch categories with usage counts
$categories = [];
$catQuery = mysqli_query(
    $conn,
    "SELECT c.*,
        (SELECT COUNT(*) FROM item_listings WHERE category_id = c.category_id) AS listing_count,
        (SELECT COUNT(*) FROM repair_requests WHERE category_id = c.category_id) AS repair_count
     FROM categories c
     ORDER BY c.category_name ASC"
);
while ($row = mysqli_fetch_assoc($catQuery)) {
    $categories[] = $row;
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editCategory = null;
if ($editId) {
    foreach ($categories as $cat) {
        if ((int) $cat['category_id'] === $editId) {
            $editCategory = $cat;
            break;
        }
    }
}

render_admin_header('Category Management', 'categories');
?>

<main class="dashboardMain">
    <div class="listingsPageHeading">
        <div>
            <p class="listingsPageLabel">Administrator</p>
            <h1>Item &amp; Repair Categories</h1>
            <p>Manage product and service classifications across the Fix &amp; Exchange platform.</p>
        </div>

        <div class="listingsTotals">
            <span class="summaryIcon" aria-hidden="true">
                <i class="fa-solid fa-tags"></i>
            </span>
            <div>
                <strong><?= count($categories) ?> categories</strong>
                <small>Campus classifications</small>
            </div>
        </div>
    </div>

    <!-- Edit Category Form (If selected) -->
    <?php if ($editCategory): ?>
        <section class="dashboardPanel adminDetailPanel" style="margin-bottom: 24px;">
            <div class="adminDetailHeader">
                <div class="adminDetailTitle">
                    <span class="summaryIcon listingIcon"><i class="fa-solid fa-pen-to-square"></i></span>
                    <h2>Edit Category: <?= escape_html($editCategory['category_name']) ?></h2>
                </div>
                <a class="listingsButton" href="adminCategories.php">
                    <i class="fa-solid fa-xmark"></i> Cancel Edit
                </a>
            </div>

            <form method="post" style="padding: 16px 0;">
                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" value="<?= (int) $editCategory['category_id'] ?>">

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label for="edit_cat_name" style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px;">Category Name *</label>
                        <input id="edit_cat_name" type="text" name="category_name" required maxlength="100"
                               value="<?= escape_html($editCategory['category_name']) ?>"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #b5c7bb; border-radius: 6px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label for="edit_cat_desc" style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px;">Description</label>
                        <input id="edit_cat_desc" type="text" name="description"
                               value="<?= escape_html($editCategory['description'] ?? '') ?>"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #b5c7bb; border-radius: 6px; box-sizing: border-box;">
                    </div>
                </div>

                <button class="listingsButton listingsApplyButton" type="submit">
                    <i class="fa-solid fa-check"></i> Save Changes
                </button>
            </form>
        </section>
    <?php endif; ?>

    <!-- Add New Category Card -->
    <section class="dashboardPanel" style="margin-bottom: 24px;">
        <h2><i class="fa-solid fa-plus-circle" style="color: #1c7049; margin-right: 6px;"></i> Add New Category</h2>
        <p class="attentionIntro">Create a new item category for marketplace listings and repair requests.</p>

        <form method="post" style="margin-top: 16px;">
            <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
            <input type="hidden" name="action" value="create_category">

            <div style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 14px; align-items: flex-end;">
                <div>
                    <label for="new_cat_name" style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px;">Category Name *</label>
                    <input id="new_cat_name" type="text" name="category_name" required maxlength="100" placeholder="e.g. Kitchenware"
                           style="width: 100%; padding: 9px 12px; border: 1px solid #b5c7bb; border-radius: 6px; box-sizing: border-box;">
                </div>
                <div>
                    <label for="new_cat_desc" style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px;">Description</label>
                    <input id="new_cat_desc" type="text" name="description" placeholder="Brief note on what items belong here"
                           style="width: 100%; padding: 9px 12px; border: 1px solid #b5c7bb; border-radius: 6px; box-sizing: border-box;">
                </div>
                <button class="listingsButton listingsApplyButton" type="submit" style="padding: 10px 18px; margin: 0;">
                    <i class="fa-solid fa-plus"></i> Add Category
                </button>
            </div>
        </form>
    </section>

    <!-- Categories List Table -->
    <section class="listingsResults">
        <div class="listingsResultsHeading">
            <h2>Current Categories</h2>
            <span><?= count($categories) ?> total</span>
        </div>

        <div class="listingsTableContainer">
            <table class="listingsTable">
                <thead>
                    <tr>
                        <th scope="col">Category Name</th>
                        <th scope="col">Description</th>
                        <th scope="col" style="text-align: center;">Listings</th>
                        <th scope="col" style="text-align: center;">Repair Requests</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <th scope="row">
                                <strong><?= escape_html($cat['category_name']) ?></strong>
                            </th>
                            <td>
                                <?= escape_html($cat['description'] ?: '—') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="listingBadge" style="background: #e0f2fe; color: #0369a1;">
                                    <?= (int) $cat['listing_count'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="repairStatusBadge" style="background: #fef3c7; color: #92400e;">
                                    <?= (int) $cat['repair_count'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="adminDetailActions">
                                    <a class="listingsButton" href="adminCategories.php?edit=<?= (int) $cat['category_id'] ?>">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>

                                    <?php if ((int) $cat['listing_count'] === 0 && (int) $cat['repair_count'] === 0): ?>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete category <?= escape_html($cat['category_name']) ?>?');">
                                            <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= (int) $cat['category_id'] ?>">
                                            <button class="listingsButton" type="submit" style="color: #b91c1c;">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php render_admin_footer(); ?>

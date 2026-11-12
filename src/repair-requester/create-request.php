<?php
// Submit or edit an item repair request with its problem details and photos.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/repair-helpers.php';

$userId = (int) current_user()['user_id'];
$categories = get_repair_categories($conn);
$errors = [];

// Determine whether we are editing an existing request
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$editing = false;
$existingRequest = null;
$existingImages = [];

$form = [
    'title' => '',
    'category_id' => 0,
    'problem_description' => '',
];

if ($requestId > 0) {
    $existingRequest = get_repair_request($conn, $requestId);

    if (!$existingRequest || (int) $existingRequest['requester_id'] !== $userId) {
        http_response_code(404);
        exit('Repair request not found or access denied.');
    }

    if (!in_array($existingRequest['status'], ['Open', 'Verified'], true)) {
        redirect_message('request-details.php?request_id=' . $requestId, 'This request can no longer be edited because work has already begun or it was closed.', 'warning');
    }

    $editing = true;
    $existingImages = get_repair_images($conn, $requestId);

    $form['title'] = $existingRequest['title'];
    $form['category_id'] = (int) $existingRequest['category_id'];
    $form['problem_description'] = $existingRequest['problem_description'];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();

    $form['title'] = trim($_POST['title'] ?? '');
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['problem_description'] = trim($_POST['problem_description'] ?? '');

    // Validate fields
    if ($form['title'] === '' || mb_strlen($form['title']) > 255) {
        $errors[] = 'Please provide an item title using 255 characters or fewer.';
    }

    $validCategoryIds = array_column($categories, 'category_id');
    if (!in_array($form['category_id'], array_map('intval', $validCategoryIds), true)) {
        $errors[] = 'Please select a valid item category.';
    }

    if ($form['problem_description'] === '' || mb_strlen($form['problem_description']) > 10000) {
        $errors[] = 'Please describe the problem using 10,000 characters or fewer.';
    }

    // Process photo removals in edit mode
    $removedImageIds = array_map('intval', (array) ($_POST['remove_images'] ?? []));
    $imagesToKeepCount = count($existingImages);
    $imagesToDelete = [];

    if ($editing && $removedImageIds) {
        foreach ($existingImages as $img) {
            if (in_array((int) $img['repair_image_id'], $removedImageIds, true)) {
                $imagesToDelete[] = $img;
                $imagesToKeepCount--;
            }
        }
    }

    // Process new photo uploads
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $newUploads = [];

    // Ensure uploads/repairs directory exists
    $uploadDir = __DIR__ . '/../../uploads/repairs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $photoErrors = (array) ($_FILES['photos']['error'] ?? []);
    foreach ($photoErrors as $index => $uploadError) {
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = 'One of the photos failed to upload. Check file size and try again.';
            continue;
        }

        $tmpName = $_FILES['photos']['tmp_name'][$index];
        $imgInfo = @getimagesize($tmpName);
        $mime = $imgInfo['mime'] ?? '';
        $fileSize = filesize($tmpName);

        if (!isset($allowedMimes[$mime]) || $fileSize > 5 * 1024 * 1024) {
            $errors[] = 'Each photo must be JPEG, PNG, or WebP format, up to 5 MB.';
        } else {
            $extension = $allowedMimes[$mime];
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $newUploads[] = [
                'tmp_name' => $tmpName,
                'path' => 'uploads/repairs/' . $filename,
                'full_dest' => $uploadDir . $filename,
            ];
        }
    }

    if (($imagesToKeepCount + count($newUploads)) > 3) {
        $errors[] = 'You can have at most 3 photos per repair request.';
    }

    // Perform DB operations if no errors
    if (!$errors) {
        $movedFiles = [];
        mysqli_begin_transaction($conn);

        try {
            if ($editing) {
                // Lock and verify
                $verifyStmt = mysqli_prepare($conn, 'SELECT status FROM repair_requests WHERE request_id = ? AND requester_id = ? FOR UPDATE');
                mysqli_stmt_bind_param($verifyStmt, 'ii', $requestId, $userId);
                mysqli_stmt_execute($verifyStmt);
                $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($verifyStmt));
                mysqli_stmt_close($verifyStmt);

                if (!$cur || !in_array($cur['status'], ['Open', 'Verified'], true)) {
                    throw new RuntimeException('This request is no longer open for editing.');
                }

                $updateStmt = mysqli_prepare(
                    $conn,
                    'UPDATE repair_requests SET title = ?, category_id = ?, problem_description = ? WHERE request_id = ? AND requester_id = ?'
                );
                mysqli_stmt_bind_param($updateStmt, 'sisii', $form['title'], $form['category_id'], $form['problem_description'], $requestId, $userId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);

                // Remove deleted photos
                foreach ($imagesToDelete as $delImg) {
                    $delStmt = mysqli_prepare($conn, 'DELETE FROM repair_images WHERE repair_image_id = ? AND request_id = ?');
                    mysqli_stmt_bind_param($delStmt, 'ii', $delImg['repair_image_id'], $requestId);
                    mysqli_stmt_execute($delStmt);
                    mysqli_stmt_close($delStmt);
                    delete_repair_photo($delImg['image_path']);
                }
            } else {
                // Insert new request
                $insertStmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO repair_requests (requester_id, category_id, title, problem_description, status)
                     VALUES (?, ?, ?, ?, 'Open')"
                );
                mysqli_stmt_bind_param($insertStmt, 'iiss', $userId, $form['category_id'], $form['title'], $form['problem_description']);
                mysqli_stmt_execute($insertStmt);
                $requestId = mysqli_insert_id($conn);
                mysqli_stmt_close($insertStmt);
            }

            // Move uploaded files and insert image records
            foreach ($newUploads as $upload) {
                if (!move_uploaded_file($upload['tmp_name'], $upload['full_dest'])) {
                    throw new RuntimeException('Failed to save an uploaded photo.');
                }
                $movedFiles[] = $upload['full_dest'];

                $imgStmt = mysqli_prepare($conn, 'INSERT INTO repair_images (request_id, image_path) VALUES (?, ?)');
                mysqli_stmt_bind_param($imgStmt, 'is', $requestId, $upload['path']);
                mysqli_stmt_execute($imgStmt);
                mysqli_stmt_close($imgStmt);
            }

            mysqli_commit($conn);
            $msg = $editing ? 'Repair request updated successfully.' : 'Repair request submitted successfully! Campus volunteers can now view your job.';
            redirect_message('request-details.php?request_id=' . $requestId, $msg, 'success');
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            // Clean up newly moved files
            foreach ($movedFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $errors[] = 'A database error occurred: ' . $ex->getMessage();
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="section repair-page">
    <div class="container container-sm">
        <?php show_repair_requester_nav($editing ? '' : 'create-request'); ?>

        <div class="repair-header-row">
            <div>
                <p class="repair-kicker"><?= $editing ? 'EDIT REQUEST' : 'NEW REPAIR REQUEST' ?></p>
                <h1><?= $editing ? 'Update Repair Request' : 'Submit an Item for Repair' ?></h1>
                <p>Provide details and clear photos of the damage so student repair volunteers can assess the fix.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= $editing ? 'request-details.php?request_id=' . $requestId : 'my-requests.php' ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?= $editing ? 'Back to Details' : 'View My Requests' ?>
            </a>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <ul style="margin: 0; padding-left: 18px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape_html($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="repair-card">
            <form method="post" enctype="multipart/form-data" novalidate id="repair-request-form">
                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="request_id" value="<?= $requestId ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="repair-title">
                        Item Title <span class="form-required">*</span>
                    </label>
                    <input class="form-control" type="text" id="repair-title" name="title" maxlength="255" required
                           placeholder="e.g. Dell Inspiron 15 (Broken hinge / power jack issue)"
                           value="<?= escape_html($form['title']) ?>">
                    <span class="form-hint">State the item brand, model, and brief issue.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="repair-category">
                        Category <span class="form-required">*</span>
                    </label>
                    <select class="form-control" id="repair-category" name="category_id" required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['category_id'] ?>" <?= (int) $form['category_id'] === (int) $cat['category_id'] ? 'selected' : '' ?>>
                                <?= escape_html($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="repair-description">
                        Problem Description <span class="form-required">*</span>
                    </label>
                    <textarea class="form-control form-textarea" id="repair-description" name="problem_description" rows="5" maxlength="10000" required
                              placeholder="Describe what is wrong in detail: When did it stop working? Are there visible damages, weird sounds, or loose parts? Have you tried any fixes?"><?= escape_html($form['problem_description']) ?></textarea>
                    <span class="form-hint">Detailed symptoms help volunteers diagnose required tools and parts faster.</span>
                </div>

                <?php if ($editing && $existingImages): ?>
                    <div class="form-group">
                        <label class="form-label">Current Photos (Check to remove)</label>
                        <?php foreach ($existingImages as $img): ?>
                            <div class="repair-existing-photo">
                                <img src="/fix-and-exchange/<?= escape_html($img['image_path']) ?>" alt="Uploaded repair image">
                                <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px; color: #b82d2d;">
                                    <input type="checkbox" name="remove_images[]" value="<?= (int) $img['repair_image_id'] ?>">
                                    Remove this photo
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="repair-photos">
                        <?= $editing ? 'Add More Photos (Max 3 total)' : 'Item Photos (Up to 3 images)' ?>
                    </label>
                    <input class="form-control" type="file" id="repair-photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <span class="form-hint">Upload JPEG, PNG, or WebP images up to 5 MB each. Clear photos of the damage increase acceptance rates.</span>
                    <div id="repair-photo-preview" class="repair-photo-preview-container"></div>
                </div>

                <div class="card" style="background: #f7faf8; border: 1px solid #d6e3da; margin-bottom: 20px;">
                    <div class="card-body" style="font-size: 13px; color: #43594b;">
                        <p style="margin: 0;">
                            <i class="fa-solid fa-circle-info" aria-hidden="true" style="color: #1c7049;"></i>
                            <strong>Campus Reminder:</strong> Once a volunteer accepts your request, you can arrange a meetup in the APU Makerspace or Library via built-in direct messages.
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <?= $editing ? 'Save Changes' : 'Submit Repair Request' ?>
                    </button>
                    <a class="btn btn-secondary" href="<?= $editing ? 'request-details.php?request_id=' . $requestId : 'my-requests.php' ?>">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const photoInput = document.getElementById('repair-photos');
    const previewContainer = document.getElementById('repair-photo-preview');
    if (photoInput && previewContainer) {
        let objectUrls = [];
        photoInput.addEventListener('change', function () {
            objectUrls.forEach(url => URL.revokeObjectURL(url));
            objectUrls = [];
            previewContainer.innerHTML = '';
            const files = Array.from(photoInput.files).slice(0, 3);
            files.forEach(file => {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
                const url = URL.createObjectURL(file);
                objectUrls.push(url);
                const item = document.createElement('div');
                item.className = 'repair-photo-preview-item';
                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Preview';
                item.appendChild(img);
                previewContainer.appendChild(item);
            });
        });
    }
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Repair Hub: Central portal for campus repairs, repair education, and community help.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/repair-helpers.php';

// Fetch community repair statistics
$statsQuery = "SELECT
    (SELECT COUNT(*) FROM volunteer_profiles WHERE approval_status = 'Approved') AS total_volunteers,
    (SELECT COUNT(*) FROM repair_requests WHERE status = 'Completed') AS completed_repairs,
    (SELECT COUNT(*) FROM repair_requests WHERE status = 'In Progress') AS in_progress_repairs,
    (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions WHERE source_type = 'Repair') AS total_points";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult) ?: [
    'total_volunteers' => 0,
    'completed_repairs' => 0,
    'in_progress_repairs' => 0,
    'total_points' => 0,
];

// Fetch categories
$categories = get_repair_categories($conn);

// Fetch recently completed repair success stories
$showcaseStmt = mysqli_prepare(
    $conn,
    "SELECT r.request_id, r.title, r.problem_description, c.category_name, a.completed_at,
            (SELECT image_path FROM repair_images WHERE request_id = r.request_id ORDER BY repair_image_id ASC LIMIT 1) AS image_path
     FROM repair_requests r
     JOIN categories c ON c.category_id = r.category_id
     JOIN repair_assignments a ON a.request_id = r.request_id
     WHERE r.status = 'Completed'
     ORDER BY a.completed_at DESC
     LIMIT 3"
);
mysqli_stmt_execute($showcaseStmt);
$showcase = mysqli_fetch_all(mysqli_stmt_get_result($showcaseStmt), MYSQLI_ASSOC);
mysqli_stmt_close($showcaseStmt);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section repair-page">
    <div class="container">
        <?php show_repair_requester_nav('repair-hub'); ?>

        <div class="repair-hub-hero">
            <p class="repair-kicker"><i class="fa-solid fa-leaf" aria-hidden="true"></i> Campus Sustainability Initiative</p>
            <h1>Repair Before You Replace</h1>
            <p>
                Got broken tech, loose charging ports, damaged cables, or torn dorm items?
                Connect with skilled student volunteers for peer-to-peer repairs. Reduce campus electronic waste,
                save money, and learn sustainable habits together.
            </p>
            <div class="repair-hub-actions">
                <a class="btn btn-primary" href="create-request.php">
                    <i class="fa-solid fa-plus-circle" aria-hidden="true"></i> Request a Repair
                </a>
                <a class="btn-outline" href="my-requests.php">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Track My Requests
                </a>
                <a class="btn-outline" href="../repair-volunteer/apply.php">
                    <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Join as Repair Volunteer
                </a>
            </div>
        </div>

        <!-- Community Impact Stats -->
        <div class="repair-stat-grid">
            <div class="repair-stat-card">
                <div class="repair-stat-icon green">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['completed_repairs'] ?></strong>
                    <span>Items Saved & Repaired</span>
                </div>
            </div>

            <div class="repair-stat-card">
                <div class="repair-stat-icon orange">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['in_progress_repairs'] ?></strong>
                    <span>Active Repairs In Progress</span>
                </div>
            </div>

            <div class="repair-stat-card">
                <div class="repair-stat-icon blue">
                    <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['total_volunteers'] ?></strong>
                    <span>Approved Volunteers</span>
                </div>
            </div>

            <div class="repair-stat-card">
                <div class="repair-stat-icon purple">
                    <i class="fa-solid fa-award" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['total_points'] ?></strong>
                    <span>Eco Points Distributed</span>
                </div>
            </div>
        </div>

        <!-- How It Works Workflow -->
        <section class="repair-workflow-section" aria-labelledby="workflow-title">
            <h2 class="repair-section-title" id="workflow-title">How Peer Repair Works</h2>
            <div class="repair-steps-grid">
                <div class="repair-step-card">
                    <span class="repair-step-number">01</span>
                    <div class="repair-step-icon"><i class="fa-solid fa-camera" aria-hidden="true"></i></div>
                    <h3>1. Submit Request</h3>
                    <p>Describe your issue, choose the item category, and upload clear photos of what's broken.</p>
                </div>

                <div class="repair-step-card">
                    <span class="repair-step-number">02</span>
                    <div class="repair-step-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></div>
                    <h3>2. Volunteer Matches</h3>
                    <p>Certified student volunteers review open repair jobs and accept requests that match their skills.</p>
                </div>

                <div class="repair-step-card">
                    <span class="repair-step-number">03</span>
                    <div class="repair-step-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></div>
                    <h3>3. Campus Handover</h3>
                    <p>Coordinate securely using in-app messaging to arrange a convenient diagnostic meetup on campus.</p>
                </div>

                <div class="repair-step-card">
                    <span class="repair-step-number">04</span>
                    <div class="repair-step-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></div>
                    <h3>4. Fixed & Handed Back</h3>
                    <p>Track progress from Diagnosing to Repaired. Collect your item with volunteer completion notes!</p>
                </div>
            </div>
        </section>

        <!-- Categories Supported -->
        <section class="repair-workflow-section" aria-labelledby="categories-title">
            <h2 class="repair-section-title" id="categories-title">Item Categories Accepted for Repair</h2>
            <div class="repair-categories-grid">
                <?php foreach ($categories as $cat): ?>
                    <div class="repair-cat-card">
                        <h4><i class="fa-solid fa-wrench" aria-hidden="true"></i> <?= escape_html($cat['category_name']) ?></h4>
                        <p>Common items, minor fixes, diagnostic testing, and maintenance assistance.</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Completed Repair Showcase -->
        <?php if ($showcase): ?>
            <section class="repair-workflow-section" aria-labelledby="showcase-title">
                <h2 class="repair-section-title" id="showcase-title">Recent Community Success Stories</h2>
                <div class="repair-steps-grid">
                    <?php foreach ($showcase as $item): ?>
                        <div class="repair-step-card">
                            <?php if ($item['image_path']): ?>
                                <img src="/fix-and-exchange/<?= escape_html($item['image_path']) ?>" alt="<?= escape_html($item['title']) ?>" class="repair-thumb-img" style="width: 100%; height: 140px; margin-bottom: 10px;">
                            <?php endif; ?>
                            <span class="repair-badge repair-badge-completed"><i class="fa-solid fa-check" aria-hidden="true"></i> Repaired</span>
                            <h3><?= escape_html($item['title']) ?></h3>
                            <p><?= escape_html(mb_strimwidth($item['problem_description'], 0, 110, '...')) ?></p>
                            <small class="text-muted"><i class="fa-regular fa-clock" aria-hidden="true"></i> Completed <?= date('M j, Y', strtotime($item['completed_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Campus Safety & Guidelines Notice -->
        <div class="card" style="border-left: 4px solid #1c7049; margin-top: 16px;">
            <div class="card-body">
                <h3 style="margin-top: 0; color: #123c2a;"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i> Campus Safety &amp; Ethics</h3>
                <p style="margin: 0; color: #44594c; font-size: 14px; line-height: 1.5;">
                    Fix &amp; Exchange peer repair is completely free for students. Volunteers earn university sustainability points for their time and expertise.
                    Please remember to back up any data on computing devices and remove memory cards before handover. Hazardous mains electrical appliances with damaged transformers should be recycled via campus e-waste bins rather than repaired in dorms.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

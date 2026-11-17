<?php
// About Us Page - Fix & Exchange
// APU Campus Sustainability & Student Development Team Showcase

require_once __DIR__ . '/includes/header.php';

// Team members array: only image, TP number, name, and gmail as requested.
$teamMembers = [
    [
        'id' => 1,
        'image' => '/fix-and-exchange/assets/images/hkp.jpg',
        'tp_number' => 'TP089683',
        'name' => 'Hein Khant Phyoe',
        'email' => 'tp089683@mail.apu.edu.my',
    ],
    [
        'id' => 2,
        'image' => '/fix-and-exchange/assets/images/tth.jpeg',
        'tp_number' => 'TP090269',
        'name' => 'Thant Thurein Htut',
        'email' => 'tp090269@mail.apu.e',
    ],
    [
        'id' => 3,
        'image' => '/fix-and-exchange/assets/images/moy.jpeg',
        'tp_number' => 'TP090255',
        'name' => 'Mye Oo Yan',
        'email' => 'tp090255@mail.apu.edu.my',
    ],
    [
        'id' => 4,
        'image' => '/fix-and-exchange/assets/images/htz.png',
        'tp_number' => 'TP089303',
        'name' => 'Hein Thant Zan',
        'email' => 'tp089303@mail.apu.edu.my',
    ],
];
?>

<div class="about-page">
    <!-- Hero Banner -->
    <section class="about-hero" aria-labelledby="about-hero-title">
        <div class="about-hero-container">
            <span class="about-badge">
                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> APU Campus Sustainability Initiative
            </span>
            <h1 id="about-hero-title">About Fix &amp; Exchange</h1>
            <p class="about-hero-lead">
                A university platform dedicated to reducing campus electronic and material waste.
                We empower students to trade pre-loved belongings, request peer repairs,
                and earn eco-points for sustainable campus living.
            </p>

            <div class="about-hero-stats">
                <div class="about-stat-item">
                    <strong>4</strong>
                    <span>Team Members</span>
                </div>
                <div class="about-stat-item">
                    <strong>4</strong>
                    <span>Core Subsystems</span>
                </div>
                <div class="about-stat-item">
                    <strong>100%</strong>
                    <span>Student-Led Project</span>
                </div>
                <div class="about-stat-item">
                    <strong>APU</strong>
                    <span>Campus Community</span>
                </div>
            </div>
        </div>
    </section>

    <div class="about-container">
        <!-- Mission & Values Section -->
        <section class="about-section" aria-labelledby="mission-title">
            <div class="about-section-header">
                <span class="about-section-eyebrow">Our Mission &amp; Purpose</span>
                <h2 id="mission-title">Why We Built Fix &amp; Exchange</h2>
                <p>
                    Every semester, countless functional electronics, textbooks, and dorm appliances are discarded.
                    Fix &amp; Exchange bridges students who have repair skills with those who need help, while fostering an affordable circular marketplace.
                </p>
            </div>

            <div class="about-pillars-grid">
                <div class="about-pillar-card">
                    <div class="about-pillar-icon">
                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                    </div>
                    <h3>Repair Before Replace</h3>
                    <p>
                        Encourage peer repairs for student laptops, calculators, fans, and accessories before sending them to landfills.
                    </p>
                </div>

                <div class="about-pillar-card">
                    <div class="about-pillar-icon">
                        <i class="fa-solid fa-recycle" aria-hidden="true"></i>
                    </div>
                    <h3>Circular Campus Market</h3>
                    <p>
                        Buy, sell, exchange, or give away items safely within the verified APU student body with zero middleman fees.
                    </p>
                </div>

                <div class="about-pillar-card">
                    <div class="about-pillar-icon">
                        <i class="fa-solid fa-medal" aria-hidden="true"></i>
                    </div>
                    <h3>Gamified Eco-Points</h3>
                    <p>
                        Earn points for successful repairs and circular trades. Climb the campus leaderboard from Eco Starter to Planet Hero.
                    </p>
                </div>

                <div class="about-pillar-card">
                    <div class="about-pillar-icon">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    </div>
                    <h3>Safe &amp; Governed</h3>
                    <p>
                        Role-based access control, verified TP numbers, admin moderation, and incident reporting guarantee platform safety.
                    </p>
                </div>
            </div>
        </section>

        <!-- 4-Member Project Team Section -->
        <section class="team-section" aria-labelledby="team-title">
            <div class="about-section-header">
                <span class="about-section-eyebrow">The Development Team</span>
                <h2 id="team-title">Meet Our Team</h2>
                <p>
                    Our project is proudly developed by a team of 4 APU computing students.
                    Each member engineered a dedicated subsystem to form this comprehensive campus sustainability ecosystem.
                </p>
            </div>

            <!-- Team Grid: 4 Members with Image, TP, Name, and Gmail only -->
            <div class="team-grid">
                <?php foreach ($teamMembers as $member): ?>
                    <article class="team-card" id="member-<?= (int) $member['id'] ?>">
                        <!-- Sample Profile Image -->
                        <div class="team-avatar-wrapper">
                            <img
                                class="team-avatar-img"
                                src="<?= escape_html($member['image']) ?>"
                                alt="Photo of <?= escape_html($member['name']) ?>"
                                width="120"
                                height="120"
                                loading="lazy"
                            >
                        </div>

                        <!-- Card Body: TP, Name, Gmail -->
                        <div class="team-card-body">
                            <!-- Student TP Number -->
                            <div class="team-tp-badge" title="Student TP Number">
                                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                                <span><?= escape_html($member['tp_number']) ?></span>
                            </div>

                            <!-- Member Name -->
                            <h3 class="team-name"><?= escape_html($member['name']) ?></h3>

                            <!-- Gmail -->
                            <div class="team-card-contact">
                                <a class="team-email-link" href="mailto:<?= escape_html($member['email']) ?>">
                                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                    <span><?= escape_html($member['email']) ?></span>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Call to Action Banner -->
        <section class="about-cta" aria-labelledby="cta-title">
            <h2 id="cta-title">Join the APU Sustainability Movement Today</h2>
            <p>
                Whether you have an item to pass on, a broken appliance that needs fixing,
                or skills you want to volunteer, Fix &amp; Exchange welcomes you!
            </p>
            <div class="about-cta-buttons">
                <a class="btn btn-light" href="/fix-and-exchange/src/buyer-seller/marketplace.php">
                    <i class="fa-solid fa-store" aria-hidden="true"></i> Explore Marketplace
                </a>
                <a class="btn btn-outline" href="/fix-and-exchange/src/repair-requester/repair-hub.php">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Visit Repair Hub
                </a>
                <a class="btn btn-outline" href="/fix-and-exchange/src/sustainability/leaderboard.php">
                    <i class="fa-solid fa-ranking-star" aria-hidden="true"></i> View Eco Leaderboard
                </a>
            </div>
        </section>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

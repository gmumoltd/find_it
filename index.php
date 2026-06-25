<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';

$page_title = SITE_NAME . ' — Reuniting people with what they lost';

// Pull a handful of the most recent open listings for the homepage grid.
$recent_sql = "SELECT i.*, c.name AS category_name
               FROM items i
               LEFT JOIN categories c ON i.category_id = c.id
               WHERE i.status = 'open'
               ORDER BY i.created_at DESC
               LIMIT 6";
$recent_result = mysqli_query($conn, $recent_sql);

require 'includes/header.php';
?>

<!-- ============================================================ -->
<!-- HERO                                                          -->
<!-- ============================================================ -->
<style>
    .hero {
        position: relative; /* Keeps the absolute background image bounded inside this section */
        overflow: hidden;
        padding: 80px 0;    /* Optional: Adds comfortable vertical spacing for the content */
    }

    .hero-main-img {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        min-width: 100%;
        min-height: 100%;
        width: auto;
        height: auto;
        object-fit: cover;
        
        z-index: -1;          /* Safely pushes the image behind your container text and buttons */
        opacity: 0.35;        /* Adjust between 0.05 and 0.25 depending on text legibility */
        pointer-events: none; /* Allows users to highlight text and click buttons through the image */
    }
</style>

<section class="hero">
    
    <div class="container">
        <img src="assets\images\id lost .jpeg" alt="Illustrated scene showing people helping each other with lost and found items" class="img-fluid hero-main-img">
        <div class="row align-items-center gy-5">
             
            <div class="col-lg-6">
                <h1 class="mb-3">Lost something? Found something?<br>Let's get it back home.</h1>
                <p class="text-soft fs-5 mb-4" style="max-width: 480px;">
                    <?php echo h(SITE_NAME); ?> connects individuals, schools, churches and organisations
                    across Kenya so lost items find their way back to their owners — fast.
                </p>

                <form action="browse.php" method="get" class="hero-search d-flex align-items-center mb-4">
                    <i class="bi bi-search text-soft me-2"></i>
                    <input type="text" name="q" class="form-control" placeholder="Search by item, location, or keyword...">
                    <button type="submit" class="btn btn-brand rounded-pill px-4">Search</button>
                </form>

                <div class="d-flex flex-wrap gap-3">
                    <a href="post-item.php?type=lost" class="btn btn-soft-danger px-4 py-2">
                        <i class="bi bi-exclamation-circle me-1"></i> Report a Lost Item
                    </a>
                    <a href="post-item.php?type=found" class="btn btn-brand px-4 py-2">
                        <i class="bi bi-check-circle me-1"></i> Report a Found Item
                    </a>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="hero-stack">
                    <div class="mini-ticket">
                        <span class="ticket-tag tag-lost" style="position: static; transform: rotate(0); display: inline-block; margin-bottom: 0.6rem;">Lost</span>
                        <div class="ticket-title">Black Samsung phone, cracked screen</div>
                        <div class="ticket-meta">
                            <span><i class="bi bi-geo-alt"></i> Wote town, Makueni</span>
                        </div>
                        <div class="ticket-footer">
                            <span class="ref-code">LT-2606-0001</span>
                            <span class="status-pill status-open">open</span>
                        </div>
                    </div>
                    <div class="mini-ticket">
                        <span class="ticket-tag tag-found" style="position: static; transform: rotate(0); display: inline-block; margin-bottom: 0.6rem;">Found</span>
                        <div class="ticket-title">National ID card at church gate</div>
                        <div class="ticket-meta">
                            <span><i class="bi bi-geo-alt"></i> ACK Jericho, Nairobi</span>
                        </div>
                        <div class="ticket-footer">
                            <span class="ref-code">FD-2606-0002</span>
                            <span class="status-pill status-open">open</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- HOW IT WORKS                                                  -->
<!-- ============================================================ -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">How it works</h2>
        <div class="row gy-4 text-center">
            <div class="col-md-4">
                <div class="step-number mb-2">1</div>
                <h5>Post a report</h5>
                <p class="text-soft">Report what you lost, or what you found, with a photo and a few details.</p>
            </div>
            <div class="col-md-4">
                <div class="step-number mb-2">2</div>
                <h5>Browse &amp; match</h5>
                <p class="text-soft">Search active listings, or wait for someone to spot a match to your report.</p>
            </div>
            <div class="col-md-4">
                <div class="step-number mb-2">3</div>
                <h5>Chat &amp; reunite</h5>
                <p class="text-soft">Message the other person directly on the platform to arrange the hand-over.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- RECENT LISTINGS                                               -->
<!-- ============================================================ -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Recent listings</h2>
            <a href="browse.php" class="btn btn-outline-brand">View All <i class="bi bi-arrow-right"></i></a>
        </div>

        <?php if (mysqli_num_rows($recent_result) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <p class="mb-0">No listings yet — be the first to report a lost or found item.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php while ($item = mysqli_fetch_assoc($recent_result)): ?>
                    <?php include 'includes/ticket-card.php'; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================ -->
<!-- INSTITUTION CALLOUT                                           -->
<!-- ============================================================ -->
<section class="py-4">
    <div class="container">
        <div class="institution-band p-4 p-md-5">
            <div class="row align-items-center gy-3">
                <div class="col-lg-8">
                    <h3 class="text-white mb-2"><i class="bi bi-building me-2"></i>Running a school, church, or office?</h3>
                    <p class="text-soft mb-0">
                        Register as an institution account to manage lost-and-found reports for your whole
                        community — perfect for schools, churches, NGOs, and front-desk offices.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-accent px-4 py-2">Register Your Institution</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

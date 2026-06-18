<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

// Quick stats for the four cards at the top of the dashboard
$items_count_sql = "SELECT COUNT(*) AS total FROM items WHERE user_id = ?";
$items_count_stmt = mysqli_prepare($conn, $items_count_sql);
mysqli_stmt_bind_param($items_count_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($items_count_stmt);
$items_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($items_count_stmt))['total'];
mysqli_stmt_close($items_count_stmt);

$pending_received = count_pending_claims_received($conn, $_SESSION['user_id']);

$my_claims_sql = "SELECT COUNT(*) AS total FROM claims WHERE claimant_id = ?";
$my_claims_stmt = mysqli_prepare($conn, $my_claims_sql);
mysqli_stmt_bind_param($my_claims_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($my_claims_stmt);
$my_claims_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($my_claims_stmt))['total'];
mysqli_stmt_close($my_claims_stmt);

$unread = count_unread_messages($conn, $_SESSION['user_id']);

// A small preview of this user's most recent listings
$recent_sql = "SELECT i.*, c.name AS category_name
               FROM items i
               LEFT JOIN categories c ON i.category_id = c.id
               WHERE i.user_id = ?
               ORDER BY i.created_at DESC
               LIMIT 3";
$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
$recent_count = mysqli_num_rows($recent_result);

$page_title = "Dashboard — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <h1 class="mb-1">
            Welcome back, <?php echo h($_SESSION['full_name']); ?>
            <?php if ($_SESSION['account_type'] === 'institution'): ?>
                <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i> Institution</span>
            <?php endif; ?>
        </h1>
        <p class="text-soft mb-4">Here's what's happening with your reports and messages.</p>

        <div class="row g-3 mb-5">
            <div class="col-sm-6 col-lg-3">
                <div class="form-card text-center py-4">
                    <div class="fs-2 fw-bold" style="color: var(--color-primary);"><?php echo $items_count; ?></div>
                    <div class="text-soft small">Items Posted</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="claims.php" class="text-decoration-none">
                    <div class="form-card text-center py-4">
                        <div class="fs-2 fw-bold" style="color: var(--color-accent-dark);"><?php echo $pending_received; ?></div>
                        <div class="text-soft small">Pending Claims Received</div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="claims.php" class="text-decoration-none">
                    <div class="form-card text-center py-4">
                        <div class="fs-2 fw-bold" style="color: var(--color-ink);"><?php echo $my_claims_count; ?></div>
                        <div class="text-soft small">Claims You've Made</div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="inbox.php" class="text-decoration-none">
                    <div class="form-card text-center py-4">
                        <div class="fs-2 fw-bold" style="color: var(--color-found);"><?php echo $unread; ?></div>
                        <div class="text-soft small">Unread Messages</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-5">
            <a href="post-item.php?type=lost" class="btn btn-soft-danger"><i class="bi bi-exclamation-circle"></i> Report Lost</a>
            <a href="post-item.php?type=found" class="btn btn-brand"><i class="bi bi-check-circle"></i> Report Found</a>
            <a href="browse.php" class="btn btn-outline-brand"><i class="bi bi-search"></i> Browse Items</a>
            <a href="profile.php" class="btn btn-outline-brand"><i class="bi bi-person"></i> Edit Profile</a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Your Recent Listings</h4>
            <a href="my-items.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
        </div>

        <?php if ($recent_count === 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard"></i></div>
                <p class="mb-1">You haven't posted anything yet.</p>
                <a href="post-item.php" class="btn btn-brand mt-2">Report Your First Item</a>
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

<?php require 'includes/footer.php'; ?>

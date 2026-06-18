<?php
// =====================================================================
// Shared page header: <head> tag + top navbar.
// Every page must call session_start() and require config/db.php +
// includes/functions.php BEFORE including this file, and should set
// $page_title before including it.
// =====================================================================

if (!isset($page_title)) {
    $page_title = SITE_NAME;
}

// Small counts used for navbar badges — only needed when logged in.
$unread_count = 0;
$pending_claims_count = 0;
if (is_logged_in()) {
    $unread_count = count_unread_messages($conn, $_SESSION['user_id']);
    $pending_claims_count = count_pending_claims_received($conn, $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?></title>

    <!-- Bootstrap 5.3.3 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Our own styles (loaded last so they can override Bootstrap defaults) -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg site-navbar sticky-top py-3">
    <div class="container">
        <a class="navbar-brand brand-wordmark" href="index.php">
            <i class="bi bi-binoculars-fill"></i> Find<span class="brand-dot">Point</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item">
                    <a class="nav-link" href="browse.php">Browse Items</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="post-item.php?type=lost">Report Lost</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="post-item.php?type=found">Report Found</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="inbox.php">
                            Inbox
                            <?php if ($unread_count > 0): ?>
                                <span class="badge rounded-pill badge-notify"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?php echo h($_SESSION['full_name']); ?>
                            <?php if ($pending_claims_count > 0): ?>
                                <span class="badge rounded-pill badge-notify"><?php echo $pending_claims_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="my-items.php"><i class="bi bi-card-list me-2"></i>My Items</a></li>
                            <li>
                                <a class="dropdown-item" href="claims.php">
                                    <i class="bi bi-hand-index-thumb me-2"></i>Claims
                                    <?php if ($pending_claims_count > 0): ?>
                                        <span class="badge rounded-pill badge-notify"><?php echo $pending_claims_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-accent btn-sm px-3" href="register.php">Sign Up Free</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main>

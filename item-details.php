<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: browse.php");
    exit();
}

// 1. FETCH THE ITEM, JOINED WITH ITS CATEGORY AND POSTER
$sql = "SELECT i.*, c.name AS category_name,
               u.id AS poster_id, u.full_name AS poster_name,
               u.account_type AS poster_account_type, u.location AS poster_location
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        INNER JOIN users u ON i.user_id = u.id
        WHERE i.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$item) {
    header("Location: browse.php");
    exit();
}

$is_owner = is_logged_in() && ((int) $_SESSION['user_id'] === (int) $item['user_id']);

// 2. IF THE CURRENT USER HAS ALREADY CLAIMED THIS ITEM, FIND THEIR CLAIM + CONVERSATION
//    so we can send them straight back to their chat instead of a duplicate claim form.
$existing_claim = null;
$conversation_id = null;
if (is_logged_in() && !$is_owner) {
    $claim_sql = "SELECT id, status FROM claims WHERE item_id = ? AND claimant_id = ? ORDER BY created_at DESC LIMIT 1";
    $claim_stmt = mysqli_prepare($conn, $claim_sql);
    mysqli_stmt_bind_param($claim_stmt, "ii", $id, $_SESSION['user_id']);
    mysqli_stmt_execute($claim_stmt);
    $existing_claim = mysqli_fetch_assoc(mysqli_stmt_get_result($claim_stmt));
    mysqli_stmt_close($claim_stmt);

    if ($existing_claim) {
        $conv_sql = "SELECT id FROM conversations WHERE item_id = ? AND claimant_id = ?";
        $conv_stmt = mysqli_prepare($conn, $conv_sql);
        mysqli_stmt_bind_param($conv_stmt, "ii", $id, $_SESSION['user_id']);
        mysqli_stmt_execute($conv_stmt);
        $conv_row = mysqli_fetch_assoc(mysqli_stmt_get_result($conv_stmt));
        $conversation_id = $conv_row['id'] ?? null;
        mysqli_stmt_close($conv_stmt);
    }
}

$tag_class = ($item['item_type'] === 'lost') ? 'tag-lost' : 'tag-found';
$tag_label = ($item['item_type'] === 'lost') ? 'Lost' : 'Found';

$page_title = $item['title'] . " — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <a href="browse.php" class="text-soft d-inline-block mb-4"><i class="bi bi-arrow-left"></i> Back to Browse</a>

        <div class="row g-5">
            <!-- Photo -->
            <div class="col-lg-6">
                <div class="ticket-photo" style="border-radius: 14px; aspect-ratio: 4/3;">
                    <span class="ticket-tag <?php echo $tag_class; ?>"><?php echo $tag_label; ?></span>
                    <?php if (!empty($item['photo'])): ?>
                        <img src="uploads/items/<?php echo h($item['photo']); ?>" alt="<?php echo h($item['title']); ?>">
                    <?php else: ?>
                        <div class="no-photo"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details -->
            <div class="col-lg-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="status-pill status-<?php echo $item['status']; ?>"><?php echo h($item['status']); ?></span>
                    <span class="ref-code"><?php echo item_ref_code($item); ?></span>
                </div>

                <h1 class="mb-3"><?php echo h($item['title']); ?></h1>

                <ul class="list-unstyled text-soft mb-4">
                    <li class="mb-2"><i class="bi bi-geo-alt me-2"></i><?php echo h($item['location']); ?></li>
                    <li class="mb-2"><i class="bi bi-calendar3 me-2"></i><?php echo ($item['item_type'] === 'lost') ? 'Lost on' : 'Found on'; ?> <?php echo date('d M Y', strtotime($item['item_date'])); ?></li>
                    <?php if (!empty($item['category_name'])): ?>
                        <li class="mb-2"><i class="bi bi-tag me-2"></i><?php echo h($item['category_name']); ?></li>
                    <?php endif; ?>
                </ul>

                <h6>Description</h6>
                <p class="mb-4"><?php echo nl2br(h($item['description'])); ?></p>

                <!-- Poster card -->
                <div class="form-card mb-4 py-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="text-soft small mb-1"><?php echo ($item['item_type'] === 'lost') ? 'Reported lost by' : 'Reported found by'; ?></div>
                            <div class="fw-semibold">
                                <?php echo h($item['poster_name']); ?>
                                <?php if ($item['poster_account_type'] === 'institution'): ?>
                                    <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i> Institution</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['poster_location'])): ?>
                                <div class="text-soft small"><i class="bi bi-geo-alt"></i> <?php echo h($item['poster_location']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action area -->
                <?php if ($is_owner): ?>
                    <div class="form-card py-3">
                        <h6 class="mb-3">Manage Your Listing</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="post-item.php?id=<?php echo (int) $item['id']; ?>" class="btn btn-outline-brand"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="claims.php" class="btn btn-outline-brand"><i class="bi bi-people"></i> View Claims</a>
                            <?php if ($item['status'] !== 'resolved'): ?>
                                <a href="item-action.php?id=<?php echo (int) $item['id']; ?>&do=resolve" class="btn btn-brand" onclick="return confirm('Mark this item as resolved? This means it has been successfully returned.');"><i class="bi bi-check2-circle"></i> Mark Resolved</a>
                            <?php endif; ?>
                            <a href="item-action.php?id=<?php echo (int) $item['id']; ?>&do=delete" class="btn btn-soft-danger" onclick="return confirm('Delete this listing permanently? This cannot be undone.');"><i class="bi bi-trash"></i> Delete</a>
                        </div>
                    </div>
                <?php elseif ($existing_claim): ?>
                    <div class="alert alert-info">
                        You've already submitted a claim on this item — status:
                        <strong><?php echo h($existing_claim['status']); ?></strong>.
                    </div>
                    <?php if ($conversation_id): ?>
                        <a href="chat.php?id=<?php echo (int) $conversation_id; ?>" class="btn btn-brand w-100 py-2"><i class="bi bi-chat-dots"></i> Go to Chat</a>
                    <?php endif; ?>
                <?php elseif ($item['status'] !== 'open'): ?>
                    <div class="alert alert-secondary mb-0">This item has already been <?php echo h($item['status']); ?> and is no longer accepting new claims.</div>
                <?php elseif (!is_logged_in()): ?>
                    <a href="login.php" class="btn btn-brand w-100 py-2">Login to Claim This Item</a>
                <?php else: ?>
                    <a href="claim.php?item_id=<?php echo (int) $item['id']; ?>" class="btn btn-brand w-100 py-2">
                        <i class="bi bi-hand-index-thumb"></i> This Is Mine — Claim It
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

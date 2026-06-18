<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

// 1. CLAIMS RECEIVED — claims other people have filed on items I posted
$received_sql = "SELECT cl.*, i.title AS item_title, i.id AS item_id, i.status AS item_status,
                         u.full_name AS claimant_name, u.account_type AS claimant_account_type,
                         conv.id AS conversation_id
                  FROM claims cl
                  INNER JOIN items i ON cl.item_id = i.id
                  INNER JOIN users u ON cl.claimant_id = u.id
                  LEFT JOIN conversations conv ON conv.item_id = cl.item_id AND conv.claimant_id = cl.claimant_id
                  WHERE i.user_id = ?
                  ORDER BY cl.created_at DESC";
$received_stmt = mysqli_prepare($conn, $received_sql);
mysqli_stmt_bind_param($received_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($received_stmt);
$received_result = mysqli_stmt_get_result($received_stmt);

// 2. CLAIMS MADE — claims I have filed on items other people posted
$made_sql = "SELECT cl.*, i.title AS item_title, i.id AS item_id, i.status AS item_status,
                    u.full_name AS poster_name, u.account_type AS poster_account_type,
                    conv.id AS conversation_id
             FROM claims cl
             INNER JOIN items i ON cl.item_id = i.id
             INNER JOIN users u ON i.user_id = u.id
             LEFT JOIN conversations conv ON conv.item_id = cl.item_id AND conv.claimant_id = cl.claimant_id
             WHERE cl.claimant_id = ?
             ORDER BY cl.created_at DESC";
$made_stmt = mysqli_prepare($conn, $made_sql);
mysqli_stmt_bind_param($made_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($made_stmt);
$made_result = mysqli_stmt_get_result($made_stmt);

$page_title = "Claims — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <h1 class="mb-4">Claims</h1>

        <h5 class="mb-3"><i class="bi bi-inbox-fill me-2"></i>Claims Received <span class="text-soft">(on items you posted)</span></h5>
        <?php if (mysqli_num_rows($received_result) === 0): ?>
            <div class="empty-state mb-5">
                <p class="mb-0">No one has claimed any of your items yet.</p>
            </div>
        <?php else: ?>
            <div class="mb-5">
                <?php while ($claim = mysqli_fetch_assoc($received_result)): ?>
                    <div class="form-card mb-3 py-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                            <div>
                                <a href="item-details.php?id=<?php echo (int) $claim['item_id']; ?>" class="fw-semibold text-decoration-none"><?php echo h($claim['item_title']); ?></a>
                                <div class="text-soft small">
                                    Claimed by <?php echo h($claim['claimant_name']); ?>
                                    <?php if ($claim['claimant_account_type'] === 'institution'): ?>
                                        <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i> Institution</span>
                                    <?php endif; ?>
                                    &middot; <?php echo time_ago($claim['created_at']); ?>
                                </div>
                            </div>
                            <span class="status-pill status-<?php echo $claim['status']; ?>"><?php echo h($claim['status']); ?></span>
                        </div>
                        <p class="text-soft small mb-3">"<?php echo h($claim['proof_message']); ?>"</p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($claim['status'] === 'pending'): ?>
                                <a href="claim-action.php?id=<?php echo (int) $claim['id']; ?>&do=approve" class="btn btn-brand btn-sm" onclick="return confirm('Approve this claim? Other pending claims on this item will be automatically rejected.');"><i class="bi bi-check-lg"></i> Approve</a>
                                <a href="claim-action.php?id=<?php echo (int) $claim['id']; ?>&do=reject" class="btn btn-soft-danger btn-sm" onclick="return confirm('Reject this claim?');"><i class="bi bi-x-lg"></i> Reject</a>
                            <?php endif; ?>
                            <?php if ($claim['conversation_id']): ?>
                                <a href="chat.php?id=<?php echo (int) $claim['conversation_id']; ?>" class="btn btn-outline-brand btn-sm"><i class="bi bi-chat-dots"></i> Chat</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <h5 class="mb-3"><i class="bi bi-send-fill me-2"></i>Claims You've Made <span class="text-soft">(on others' items)</span></h5>
        <?php if (mysqli_num_rows($made_result) === 0): ?>
            <div class="empty-state">
                <p class="mb-0">You haven't claimed any items yet. <a href="browse.php">Browse listings</a> to find yours.</p>
            </div>
        <?php else: ?>
            <div>
                <?php while ($claim = mysqli_fetch_assoc($made_result)): ?>
                    <div class="form-card mb-3 py-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                            <div>
                                <a href="item-details.php?id=<?php echo (int) $claim['item_id']; ?>" class="fw-semibold text-decoration-none"><?php echo h($claim['item_title']); ?></a>
                                <div class="text-soft small">
                                    Posted by <?php echo h($claim['poster_name']); ?>
                                    <?php if ($claim['poster_account_type'] === 'institution'): ?>
                                        <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i> Institution</span>
                                    <?php endif; ?>
                                    &middot; <?php echo time_ago($claim['created_at']); ?>
                                </div>
                            </div>
                            <span class="status-pill status-<?php echo $claim['status']; ?>"><?php echo h($claim['status']); ?></span>
                        </div>
                        <?php if ($claim['conversation_id']): ?>
                            <a href="chat.php?id=<?php echo (int) $claim['conversation_id']; ?>" class="btn btn-outline-brand btn-sm"><i class="bi bi-chat-dots"></i> Chat</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

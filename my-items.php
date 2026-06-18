<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

// Pull this user's own items, plus a live count of pending claims on each one.
$sql = "SELECT i.*, c.name AS category_name,
               (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.id AND cl.status = 'pending') AS pending_claims_count
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.user_id = ?
        ORDER BY i.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);
$total_items = mysqli_num_rows($items_result);

$page_title = "My Items — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <h1 class="mb-0">My Items</h1>
            <div class="d-flex gap-2">
                <a href="post-item.php?type=lost" class="btn btn-soft-danger">Report Lost</a>
                <a href="post-item.php?type=found" class="btn btn-brand">Report Found</a>
            </div>
        </div>

        <?php if ($total_items === 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard"></i></div>
                <p class="mb-1">You haven't posted anything yet.</p>
                <a href="post-item.php" class="btn btn-brand mt-2">Report Your First Item</a>
            </div>
        <?php else: ?>
            <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                <?php
                $tag_class = ($item['item_type'] === 'lost') ? 'tag-lost' : 'tag-found';
                $tag_label = ($item['item_type'] === 'lost') ? 'Lost' : 'Found';
                ?>
                <div class="form-card mb-3 py-3">
                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <div style="width: 84px; height: 84px; flex-shrink: 0; border-radius: 10px; overflow: hidden; background: var(--color-bg);">
                            <?php if (!empty($item['photo'])): ?>
                                <img src="uploads/items/<?php echo h($item['photo']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 text-soft"><i class="bi bi-image fs-4"></i></div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-grow-1" style="min-width: 200px;">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="ticket-tag <?php echo $tag_class; ?>" style="position: static; transform: none; display: inline-block;"><?php echo $tag_label; ?></span>
                                <span class="status-pill status-<?php echo $item['status']; ?>"><?php echo h($item['status']); ?></span>
                                <span class="ref-code"><?php echo item_ref_code($item); ?></span>
                            </div>
                            <a href="item-details.php?id=<?php echo (int) $item['id']; ?>" class="fw-semibold text-decoration-none"><?php echo h($item['title']); ?></a>
                            <div class="text-soft small"><i class="bi bi-geo-alt"></i> <?php echo h($item['location']); ?> &middot; <?php echo time_ago($item['created_at']); ?></div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="claims.php" class="btn btn-outline-brand btn-sm">
                                <i class="bi bi-people"></i> Claims
                                <?php if ($item['pending_claims_count'] > 0): ?>
                                    <span class="badge rounded-pill badge-notify"><?php echo (int) $item['pending_claims_count']; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="post-item.php?id=<?php echo (int) $item['id']; ?>" class="btn btn-outline-brand btn-sm"><i class="bi bi-pencil"></i></a>
                            <?php if ($item['status'] !== 'resolved'): ?>
                                <a href="item-action.php?id=<?php echo (int) $item['id']; ?>&do=resolve" class="btn btn-outline-brand btn-sm" onclick="return confirm('Mark this item as resolved?');"><i class="bi bi-check2-circle"></i></a>
                            <?php endif; ?>
                            <a href="item-action.php?id=<?php echo (int) $item['id']; ?>&do=delete" class="btn btn-soft-danger btn-sm" onclick="return confirm('Delete this listing permanently? This cannot be undone.');"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

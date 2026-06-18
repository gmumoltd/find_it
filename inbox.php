<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

$sql = "SELECT conv.id AS conversation_id,
               conv.item_id,
               i.title AS item_title,
               i.photo AS item_photo,
               conv.poster_id,
               conv.claimant_id,
               poster.full_name AS poster_name,
               poster.account_type AS poster_account_type,
               claimant.full_name AS claimant_name,
               claimant.account_type AS claimant_account_type,
               (SELECT m.message FROM messages m WHERE m.conversation_id = conv.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM messages m WHERE m.conversation_id = conv.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = conv.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count
        FROM conversations conv
        INNER JOIN items i ON conv.item_id = i.id
        INNER JOIN users poster ON conv.poster_id = poster.id
        INNER JOIN users claimant ON conv.claimant_id = claimant.id
        WHERE conv.poster_id = ? OR conv.claimant_id = ?
        ORDER BY last_message_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$conversations_result = mysqli_stmt_get_result($stmt);
$total_conversations = mysqli_num_rows($conversations_result);

$page_title = "Inbox — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 760px;">
        <h1 class="mb-4">Inbox</h1>

        <?php if ($total_conversations === 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-chat-dots"></i></div>
                <p class="mb-1">No conversations yet.</p>
                <p class="text-soft small mb-0">Chats open automatically once you claim an item, or once someone claims yours.</p>
            </div>
        <?php else: ?>
            <?php while ($conv = mysqli_fetch_assoc($conversations_result)): ?>
                <?php
                $is_poster = ((int) $conv['poster_id'] === (int) $_SESSION['user_id']);
                $other_name = $is_poster ? $conv['claimant_name'] : $conv['poster_name'];
                $other_account_type = $is_poster ? $conv['claimant_account_type'] : $conv['poster_account_type'];
                $unread = (int) $conv['unread_count'];
                ?>
                <a href="chat.php?id=<?php echo (int) $conv['conversation_id']; ?>" class="text-decoration-none">
                    <div class="form-card mb-3 py-3 <?php echo $unread > 0 ? 'border-0' : ''; ?>" style="<?php echo $unread > 0 ? 'box-shadow: 0 0 0 2px var(--color-primary) inset;' : ''; ?>">
                        <div class="d-flex gap-3 align-items-center">
                            <div style="width: 56px; height: 56px; flex-shrink: 0; border-radius: 10px; overflow: hidden; background: var(--color-bg);">
                                <?php if (!empty($conv['item_photo'])): ?>
                                    <img src="uploads/items/<?php echo h($conv['item_photo']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 text-soft"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="d-flex justify-content-between align-items-baseline gap-2">
                                    <span class="fw-semibold">
                                        <?php echo h($other_name); ?>
                                        <?php if ($other_account_type === 'institution'): ?>
                                            <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-soft small flex-shrink-0"><?php echo $conv['last_message_at'] ? time_ago($conv['last_message_at']) : ''; ?></span>
                                </div>
                                <div class="text-soft small mb-1"><?php echo h($conv['item_title']); ?></div>
                                <div class="text-truncate" style="max-width: 100%; <?php echo $unread > 0 ? 'font-weight: 600;' : 'color: var(--color-ink-soft);'; ?>">
                                    <?php echo h($conv['last_message'] ?? 'No messages yet.'); ?>
                                </div>
                            </div>
                            <?php if ($unread > 0): ?>
                                <span class="badge rounded-pill badge-notify flex-shrink-0"><?php echo $unread; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

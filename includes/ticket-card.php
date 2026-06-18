<?php
// =====================================================================
// Reusable "claim ticket" card for one item.
// Expects a $item array already in scope, with the joined fields:
//   id, item_type, title, location, item_date, photo, status,
//   created_at, category_name (nullable)
// Used on: index.php, browse.php, my-items.php
// =====================================================================
$tag_class = ($item['item_type'] === 'lost') ? 'tag-lost' : 'tag-found';
$tag_label = ($item['item_type'] === 'lost') ? 'Lost' : 'Found';
?>
<div class="col-sm-6 col-lg-4">
    <a href="item-details.php?id=<?php echo (int) $item['id']; ?>" class="text-decoration-none">
        <div class="ticket-card">
            <div class="ticket-photo">
                <span class="ticket-tag <?php echo $tag_class; ?>"><?php echo $tag_label; ?></span>
                <?php if (!empty($item['photo'])): ?>
                    <img src="uploads/items/<?php echo h($item['photo']); ?>" alt="<?php echo h($item['title']); ?>" loading="lazy">
                <?php else: ?>
                    <div class="no-photo"><i class="bi bi-image"></i></div>
                <?php endif; ?>
            </div>
            <div class="ticket-divider"></div>
            <div class="ticket-body">
                <div class="ticket-title"><?php echo h($item['title']); ?></div>
                <div class="ticket-meta">
                    <span><i class="bi bi-geo-alt"></i> <?php echo h($item['location']); ?></span>
                    <span><i class="bi bi-calendar3"></i> <?php echo date('d M Y', strtotime($item['item_date'])); ?></span>
                    <?php if (!empty($item['category_name'])): ?>
                        <span><i class="bi bi-tag"></i> <?php echo h($item['category_name']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="ticket-footer">
                    <span class="ref-code"><?php echo item_ref_code($item); ?></span>
                    <span class="status-pill status-<?php echo $item['status']; ?>"><?php echo $item['status']; ?></span>
                </div>
            </div>
        </div>
    </a>
</div>

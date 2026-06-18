<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';

// -----------------------------------------------------------------
// 1. READ FILTERS FROM THE URL (all optional)
// -----------------------------------------------------------------
$keyword         = trim($_GET['q'] ?? '');
$type_filter     = $_GET['type'] ?? '';
$category_filter = $_GET['category_id'] ?? '';
$status_filter   = $_GET['status'] ?? 'open';

// -----------------------------------------------------------------
// 2. BUILD A SAFE, DYNAMIC WHERE CLAUSE
//    Every value the user controls goes through a placeholder (?) —
//    never directly into the SQL string.
// -----------------------------------------------------------------
$conditions = [];
$params = [];
$types = '';

if ($keyword !== '') {
    $conditions[] = "(i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($type_filter === 'lost' || $type_filter === 'found') {
    $conditions[] = "i.item_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if ($category_filter !== '' && ctype_digit((string) $category_filter)) {
    $conditions[] = "i.category_id = ?";
    $params[] = (int) $category_filter;
    $types .= 'i';
}

if (in_array($status_filter, ['open', 'claimed', 'resolved'], true)) {
    $conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= 's';
} else {
    $status_filter = 'all';
}

$where_sql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// -----------------------------------------------------------------
// 3. COUNT TOTAL MATCHES (for pagination), then fetch one page of results
// -----------------------------------------------------------------
$count_sql = "SELECT COUNT(*) AS total FROM items i $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($params) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_items = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);

$per_page = 9;
$total_pages = max(1, (int) ceil($total_items / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $per_page;

$list_sql = "SELECT i.*, c.name AS category_name
             FROM items i
             LEFT JOIN categories c ON i.category_id = c.id
             $where_sql
             ORDER BY i.created_at DESC
             LIMIT ? OFFSET ?";
$list_types = $types . 'ii';
$list_params = array_merge($params, [$per_page, $offset]);

$list_stmt = mysqli_prepare($conn, $list_sql);
mysqli_stmt_bind_param($list_stmt, $list_types, ...$list_params);
mysqli_stmt_execute($list_stmt);
$items_result = mysqli_stmt_get_result($list_stmt);

// All categories, for the filter dropdown
$categories_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");

// Helper to build a link that keeps the current filters but changes one thing (e.g. page)
function filter_url($overrides)
{
    $query = array_filter(array_merge($_GET, $overrides), function ($v) {
        return $v !== '' && $v !== null;
    });
    return 'browse.php?' . http_build_query($query);
}

$page_title = "Browse Items — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <h1 class="mb-4">Browse Listings</h1>

        <form method="GET" action="browse.php" class="form-card mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label">Keyword</label>
                    <input type="text" name="q" class="form-control" placeholder="Item, location..." value="<?php echo h($keyword); ?>">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="" <?php echo $type_filter === '' ? 'selected' : ''; ?>>All</option>
                        <option value="lost" <?php echo $type_filter === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="found" <?php echo $type_filter === 'found' ? 'selected' : ''; ?>>Found</option>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo (int) $cat['id']; ?>" <?php echo ((string) $category_filter === (string) $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo h($cat['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="claimed" <?php echo $status_filter === 'claimed' ? 'selected' : ''; ?>>Claimed</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="col-md-12 col-lg-1">
                    <button type="submit" class="btn btn-brand w-100">Filter</button>
                </div>
            </div>
        </form>

        <p class="text-soft mb-4">
            <?php echo $total_items; ?> item<?php echo $total_items === 1 ? '' : 's'; ?> found
        </p>

        <?php if ($total_items === 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-search"></i></div>
                <p class="mb-1">No items match those filters.</p>
                <a href="browse.php" class="btn btn-outline-brand mt-2">Clear Filters</a>
            </div>
        <?php else: ?>
            <div class="row g-4 mb-5">
                <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                    <?php include 'includes/ticket-card.php'; ?>
                <?php endwhile; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo h(filter_url(['page' => $page - 1])); ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo h(filter_url(['page' => $p])); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo h(filter_url(['page' => $page + 1])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

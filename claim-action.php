<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['do'] ?? '';

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    header("Location: claims.php");
    exit();
}

// Only the poster who owns the item this claim is against may act on it.
$sql = "SELECT cl.id, cl.item_id, cl.status, i.user_id AS poster_id
        FROM claims cl
        INNER JOIN items i ON cl.item_id = i.id
        WHERE cl.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$claim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$claim || (int) $claim['poster_id'] !== (int) $_SESSION['user_id']) {
    header("Location: claims.php");
    exit();
}

// Already decided — nothing to do (also stops a refreshed/replayed link from
// re-triggering the reject-others side effect a second time).
if ($claim['status'] !== 'pending') {
    header("Location: claims.php");
    exit();
}

if ($action === 'approve') {
    $approve_sql = "UPDATE claims SET status = 'approved' WHERE id = ?";
    $approve_stmt = mysqli_prepare($conn, $approve_sql);
    mysqli_stmt_bind_param($approve_stmt, "i", $id);
    mysqli_stmt_execute($approve_stmt);
    mysqli_stmt_close($approve_stmt);

    // Approving one claim automatically closes out any other pending claims
    // on the same item — only one person can end up with it.
    $reject_others_sql = "UPDATE claims SET status = 'rejected' WHERE item_id = ? AND id != ? AND status = 'pending'";
    $reject_others_stmt = mysqli_prepare($conn, $reject_others_sql);
    mysqli_stmt_bind_param($reject_others_stmt, "ii", $claim['item_id'], $id);
    mysqli_stmt_execute($reject_others_stmt);
    mysqli_stmt_close($reject_others_stmt);

    $item_update_sql = "UPDATE items SET status = 'claimed', updated_at = NOW() WHERE id = ?";
    $item_update_stmt = mysqli_prepare($conn, $item_update_sql);
    mysqli_stmt_bind_param($item_update_stmt, "i", $claim['item_id']);
    mysqli_stmt_execute($item_update_stmt);
    mysqli_stmt_close($item_update_stmt);
} elseif ($action === 'reject') {
    $reject_sql = "UPDATE claims SET status = 'rejected' WHERE id = ?";
    $reject_stmt = mysqli_prepare($conn, $reject_sql);
    mysqli_stmt_bind_param($reject_stmt, "i", $id);
    mysqli_stmt_execute($reject_stmt);
    mysqli_stmt_close($reject_stmt);
}

header("Location: claims.php");
exit();

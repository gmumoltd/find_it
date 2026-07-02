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
// Fetching claimant (user_id from claims) so we know who pays later.
$sql = "SELECT cl.id, cl.item_id, cl.status, cl.user_id AS claimant_id, i.user_id AS poster_id
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

// Already decided — nothing to do
if ($claim['status'] !== 'pending') {
    header("Location: claims.php");
    exit();
}

if ($action === 'approve') {
    $approve_sql = "UPDATE claims SET status = 'approved' WHERE id = ?";
    $approve_stmt = mysqli_prepare($conn, $approve_sql);
    $approve_stmt = mysqli_prepare($conn, $approve_sql);
    mysqli_stmt_bind_param($approve_stmt, "i", $id);
    mysqli_stmt_execute($approve_stmt);
    mysqli_stmt_close($approve_stmt);

    // MONETIZATION STEP: Create a record tracking that this approved chat is locked and unpaid
    $fee_amount = 20.00; // Your system maintenance access fee
    $init_payment_sql = "INSERT INTO item_claims (item_id, loser_id, finder_id, amount_paid, payment_status) VALUES (?, ?, ?, ?, 'unpaid')";
    $pay_stmt = mysqli_prepare($conn, $init_payment_sql);
    mysqli_stmt_bind_param($pay_stmt, "iiid", $claim['item_id'], $claim['claimant_id'], $claim['poster_id'], $fee_amount);
    mysqli_stmt_execute($pay_stmt);
    mysqli_stmt_close($pay_stmt);

    // Approving one claim automatically closes out any other pending claims
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
    mysqli_stmt_execute($reject_sql);
    mysqli_stmt_close($reject_sql);
}

header("Location: claims.php");
exit();
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

// 1. FETCH THE RAW CLAIM DETAILS FIRST
$sql = "SELECT * FROM claims WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$claim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$claim) {
    header("Location: claims.php");
    exit();
}

// 2. FETCH THE ITEM'S POSTER ID SEPARATELY TO PREVENT INNER JOIN FAILURES
$item_id = (int)$claim['item_id'];
$item_sql = "SELECT user_id FROM items WHERE id = ?";
$item_stmt = mysqli_prepare($conn, $item_sql);
mysqli_stmt_bind_param($item_stmt, "i", $item_id);
mysqli_stmt_execute($item_stmt);
$item_data = mysqli_fetch_assoc(mysqli_stmt_get_result($item_stmt));
mysqli_stmt_close($item_stmt);

$poster_id = $item_data ? (int)$item_data['user_id'] : 0;
$claimant_id = (int)$claim['user_id']; 

// Only the poster who owns the item this claim is against may act on it.
if ($poster_id !== (int) $_SESSION['user_id']) {
    header("Location: claims.php");
    exit();
}

// Already decided — nothing to do
if ($claim['status'] !== 'pending') {
    header("Location: claims.php");
    exit();
}

if ($action === 'approve') {
    // Approve the current claim
    $approve_sql = "UPDATE claims SET status = 'approved' WHERE id = ?";
    $approve_stmt = mysqli_prepare($conn, $approve_sql);
    mysqli_stmt_bind_param($approve_stmt, "i", $id);
    mysqli_stmt_execute($approve_stmt);
    mysqli_stmt_close($approve_stmt);

    // MONETIZATION STEP: Create a record tracking that this approved chat is locked and unpaid,
    // but only if a pre-claim payment record does not already exist.
    $fee_amount = 20.00; // Your system maintenance access fee
    $check_payment_sql = "SELECT id FROM item_claims WHERE item_id = ? AND loser_id = ? AND finder_id = ?";
    $check_payment_stmt = mysqli_prepare($conn, $check_payment_sql);
    mysqli_stmt_bind_param($check_payment_stmt, "iii", $item_id, $claimant_id, $poster_id);
    mysqli_stmt_execute($check_payment_stmt);
    $existing_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($check_payment_stmt));
    mysqli_stmt_close($check_payment_stmt);

    if (!$existing_payment) {
        $init_payment_sql = "INSERT INTO item_claims (item_id, loser_id, finder_id, amount_paid, payment_status) VALUES (?, ?, ?, ?, 'unpaid')";
        $pay_stmt = mysqli_prepare($conn, $init_payment_sql);
        mysqli_stmt_bind_param($pay_stmt, "iiid", $item_id, $claimant_id, $poster_id, $fee_amount);
        mysqli_stmt_execute($pay_stmt);
        mysqli_stmt_close($pay_stmt);
    }

    // Approving one claim automatically closes out any other pending claims on the same item
    $reject_others_sql = "UPDATE claims SET status = 'rejected' WHERE item_id = ? AND id != ? AND status = 'pending'";
    $reject_others_stmt = mysqli_prepare($conn, $reject_others_sql);
    mysqli_stmt_bind_param($reject_others_stmt, "ii", $item_id, $id);
    mysqli_stmt_execute($reject_others_stmt);
    mysqli_stmt_close($reject_others_stmt);

    // Update item status to claimed
    $item_update_sql = "UPDATE items SET status = 'claimed', updated_at = NOW() WHERE id = ?";
    $item_update_stmt = mysqli_prepare($conn, $item_update_sql);
    mysqli_stmt_bind_param($item_update_stmt, "i", $item_id);
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
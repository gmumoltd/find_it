<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

$claimId = (int)($_GET['claim_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($claimId <= 0) {
    header("Location: inbox.php");
    exit();
}

// Locate matching dynamic asset
$query = "SELECT * FROM item_claims WHERE id = ? AND loser_id = ? AND payment_status = 'paid'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $claimId, $userId);
mysqli_stmt_execute($stmt);
$claim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$claim) {
    header("Location: inbox.php");
    exit();
}

$originalAmount = $claim['amount_paid'];
$maintenanceFee = $originalAmount * 0.10; // Retain KES 2
$refundAmount = $originalAmount - $maintenanceFee; // Refund KES 18

// Use atomic database tracking
mysqli_begin_transaction($conn);

try {
    // 1. Credit the virtual balance
    $walletQuery = "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?";
    $walletStmt = mysqli_prepare($conn, $walletQuery);
    mysqli_stmt_bind_param($walletStmt, "di", $refundAmount, $userId);
    mysqli_stmt_execute($walletStmt);
    mysqli_stmt_close($walletStmt);

    // 2. Change claim log state to 'refunded'
    $claimQuery = "UPDATE item_claims SET payment_status = 'refunded' WHERE id = ?";
    $claimStmt = mysqli_prepare($conn, $claimQuery);
    mysqli_stmt_bind_param($claimStmt, "i", $claimId);
    mysqli_stmt_execute($claimStmt);
    mysqli_stmt_close($claimStmt);

    mysqli_commit($conn);
    
    // Redirect cleanly to the inbox with a confirmation flag
    header("Location: inbox.php?status=refund_success");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Deduction script execution exception error.");
}
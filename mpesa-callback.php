<?php
// Include your existing database connection script
require_once 'config/db.php'; 

$response = file_get_contents('php://input');
$data = json_decode($response, true);
$claimId = isset($_GET['claim_id']) ? intval($_GET['claim_id']) : null;

if ($data && $claimId) {
    $resultCode = $data['Body']['stkCallback']['ResultCode'];
    
    if ($resultCode == 0) { // 0 means transaction was successful
        // Extract M-Pesa Receipt Number
        $mpesaReceipt = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
        
        // Generate a random 4-digit secure handoff PIN
        $handoffPin = strval(rand(1000, 9999));

        // Update database using your standard procedural style
        $query = "UPDATE item_claims SET payment_status = 'paid', mpesa_receipt = ?, handoff_pin = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $mpesaReceipt, $handoffPin, $claimId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // Transaction failed or cancelled by user
        $query = "UPDATE item_claims SET payment_status = 'unpaid' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $claimId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
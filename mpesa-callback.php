<?php
require_once 'config/constants.php';
require_once 'config/db.php';

$response = file_get_contents('php://input');
$data = json_decode($response, true);
$claimId = isset($_GET['claim_id']) ? intval($_GET['claim_id']) : null;

if (!$data || !is_array($data) || !$claimId) {
    http_response_code(400);
    exit('Invalid callback payload or missing claim_id.');
}

$callback = $data['Body']['stkCallback'] ?? null;
if (!is_array($callback)) {
    http_response_code(400);
    exit('Invalid STK callback structure.');
}

$resultCode = intval($callback['ResultCode'] ?? -1);
$metadata = $callback['CallbackMetadata']['Item'] ?? [];

$mpesaReceipt = null;
$checkoutRequestId = $callback['CheckoutRequestID'] ?? null;

if (is_array($metadata)) {
    foreach ($metadata as $item) {
        if (!isset($item['Name'], $item['Value'])) {
            continue;
        }

        $name = strtolower(str_replace('_', '', $item['Name']));
        if ($name === 'mpesareceiptnumber') {
            $mpesaReceipt = $item['Value'];
            break;
        }
    }
}

$rawPayload = json_encode($data);
$logQuery = "INSERT INTO mpesa_callback_logs (claim_id, checkout_request_id, result_code, result_desc, payload) VALUES (?, ?, ?, ?, ?)";
$logStmt = mysqli_prepare($conn, $logQuery);
if ($logStmt) {
    $resultDesc = $callback['ResultDesc'] ?? '';
    mysqli_stmt_bind_param($logStmt, "isiss", $claimId, $checkoutRequestId, $resultCode, $resultDesc, $rawPayload);
    mysqli_stmt_execute($logStmt);
    mysqli_stmt_close($logStmt);
}

if ($resultCode === 0 && $mpesaReceipt) {
    $handoffPin = strval(rand(1000, 9999));
    $query = "UPDATE item_claims SET payment_status = 'paid', mpesa_receipt = ?, checkout_request_id = ?, handoff_pin = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssi", $mpesaReceipt, $checkoutRequestId, $handoffPin, $claimId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    // Do not unset a previously-confirmed payment on callback failures.
    // Leave the claim record untouched if it is already marked paid.
    // If the claim remains unpaid, the existing status is already correct.
}

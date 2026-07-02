<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: claims.php");
    exit();
}

$claimTrackId = (int)($_POST['claim_track_id'] ?? 0);
$userPhone = trim($_POST['phone'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';

if ($claimTrackId <= 0 || empty($userPhone) || !validate_csrf_token($csrfToken)) {
    die("Invalid request parameters or missing CSRF token.");
}

// 1. Standardize Kenyan Phone Number Format (e.g., 0712345678 -> 254712345678)
$userPhone = preg_replace('/^0/', '254', $userPhone);
$userPhone = preg_replace('/^\+/', '', $userPhone);

// Validate basic phone pattern structure
if (!preg_match('/^254[17]\d{8}$/', $userPhone)) {
    die("Invalid M-Pesa phone number format. Please use 07XXXXXXXX or 254XXXXXXXX.");
}

// 2. Fetch the corresponding claim transaction details to confirm amount
$query = "SELECT * FROM item_claims WHERE id = ? AND loser_id = ? AND payment_status = 'unpaid'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $claimTrackId, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$claimData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$claimData) {
    die("Claim transaction record not found or already paid.");
}

$amount = $claimData['amount_paid'];

// 3. M-Pesa Daraja API Credentials
$consumerKey       = MPESA_CONSUMER_KEY;
$consumerSecret    = MPESA_CONSUMER_SECRET;
$businessShortCode = MPESA_BUSINESS_SHORTCODE;
$passkey           = MPESA_PASSKEY;

$authUrl = MPESA_ENVIRONMENT === 'production'
    ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

// 4. Generate Password & Timestamp
$timestamp = date('YmdHis');
$password = base64_encode($businessShortCode . $passkey . $timestamp);

// 5. Request Daraja OAuth Token via cURL
$authUrl = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$authResult = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("M-Pesa auth request failed: " . htmlspecialchars($curlError));
}

$authResponse = json_decode($authResult, true);
if (!is_array($authResponse) || !isset($authResponse['access_token'])) {
    die("Failed to generate M-Pesa access token. Response: " . htmlspecialchars($authResult));
}
$accessToken = $authResponse['access_token'];

// 6. Set your dynamic callback URL from config/constants.php
if (!defined('MPESA_CALLBACK_URL') || MPESA_CALLBACK_URL === 'https://your-public-domain.ngrok-free.app/mpesa-callback.php') {
    die("Please set MPESA_CALLBACK_URL inside config/constants.php to your public M-Pesa callback endpoint.");
}
$callbackUrl = MPESA_CALLBACK_URL;

$stkUrl = MPESA_ENVIRONMENT === 'production'
    ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
    : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$stkPayload = [
    'BusinessShortCode' => $businessShortCode,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerPayBillOnline',
    'Amount'            => $amount,
    'PartyA'            => $userPhone,
    'PartyB'            => $businessShortCode,
    'PhoneNumber'       => $userPhone,
    'CallBackURL'       => $callbackUrl . "?claim_id=" . $claimTrackId,
    'AccountReference'  => 'Claim_' . $claimData['item_id'],
    'TransactionDesc'   => 'Unlock Chat Access'
];

// 8. Fire the STK Push Request
$ch = curl_init($stkUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkPayload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$stkResult = curl_exec($ch);
$stkCurlError = curl_error($ch);
curl_close($ch);

if ($stkCurlError) {
    die("M-Pesa STK request failed: " . htmlspecialchars($stkCurlError));
}

$stkResponse = json_decode($stkResult, true);
if (!is_array($stkResponse)) {
    die("Invalid response from M-Pesa STK push: " . htmlspecialchars($stkResult));
}

// 9. Evaluate Daraja API Response
$conversationId = null;
$conversationQuery = "SELECT id FROM conversations WHERE item_id = ? AND poster_id = ? AND claimant_id = ? LIMIT 1";
$conversationStmt = mysqli_prepare($conn, $conversationQuery);
if ($conversationStmt) {
    mysqli_stmt_bind_param($conversationStmt, "iii", $claimData['item_id'], $claimData['finder_id'], $claimData['loser_id']);
    mysqli_stmt_execute($conversationStmt);
    $conversationResult = mysqli_stmt_get_result($conversationStmt);
    $conversationRow = mysqli_fetch_assoc($conversationResult);
    if ($conversationRow) {
        $conversationId = (int) $conversationRow['id'];
    }
    mysqli_stmt_close($conversationStmt);
}

$checkoutRequestId = $stkResponse['CheckoutRequestID'] ?? null;

if (isset($stkResponse['ResponseCode']) && $stkResponse['ResponseCode'] == '0') {
    if ($checkoutRequestId) {
        $updateQuery = "UPDATE item_claims SET checkout_request_id = ? WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, "si", $checkoutRequestId, $claimTrackId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
    }

    echo "<div style='max-width:500px; margin:50px auto; padding:20px; font-family:sans-serif; text-align:center; border:1px solid #ddd; border-radius:8px;'>";
    echo "  <h3 style='color:#4CAF50;'>STK Push Initiated Successfully!</h3>";
    echo "  <p>Please check your phone (<strong>" . h($userPhone) . "</strong>). An M-Pesa popup prompt has been sent requesting your PIN.</p>";
    if ($conversationId) {
        echo "  <p>Once you authorize the transaction, refresh your <a href='chat.php?id=" . $conversationId . "'>chat</a> to start talking.</p>";
        echo "  <p><small>You will be redirected back in 10 seconds.</small></p>";
        echo "  <script>setTimeout(function(){ window.location.href = 'chat.php?id=" . $conversationId . "'; }, 10000);</script>";
    } else {
        echo "  <p>Once you authorize the transaction, refresh your chat page or go back to your inbox to continue.</p>";
    }
    echo "</div>";
} else {
    echo "M-Pesa API Error: " . ($stkResponse['ResponseDescription'] ?? 'Unknown dispatch error occurred.');
}
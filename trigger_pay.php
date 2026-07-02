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

if ($claimTrackId <= 0 || empty($userPhone)) {
    die("Invalid request parameters.");
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

// 3. M-Pesa Daraja API Sandbox Credentials
$consumerKey    = '83R8ewAaFkcoUQzmu6Rb1bMxmAujK1h5Jk4Nhu5McMawnCZh'; // Replace with yours
$consumerSecret = 'FSOyjgLPMiAksUPpPTaRscOPr20Vtt4NyzUx5wjQnwONznv5psTIsWcgevl4R28A'; // Replace with yours
$businessShortCode = '174379'; // Default Daraja Sandbox Paybill
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; // Sandbox Passkey

// 4. Generate Password & Timestamp
$timestamp = date('YmdHis');
$password = base64_encode($businessShortCode . $passkey . $timestamp);

// 5. Request Daraja OAuth Token via cURL
$authUrl = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$authResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($authResponse['access_token'])) {
    die("Failed to generate M-Pesa access token. Check your Consumer Key/Secret config.");
}
$accessToken = $authResponse['access_token'];

// 6. Set your dynamic callback URL (Change this to your public domain/ngrok address!)
// Example: 'https://a1b2-105-160-x-x.ngrok-free.app/mpesa-callback.php'
$callbackUrl = 'https://unfunded-audition-acorn.ngrok-free.dev/mpesa-callback.php';
// 7. Prepare the M-Pesa Express (STK Push) Payload
$stkUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
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
$stkResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

// 9. Evaluate Daraja API Response
if (isset($stkResponse['ResponseCode']) && $stkResponse['ResponseCode'] == '0') {
    echo "<div style='max-width:500px; margin:50px auto; padding:20px; font-family:sans-serif; text-align:center; border:1px solid #ddd; border-radius:8px;'>";
    echo "  <h3 style='color:#4CAF50;'>STK Push Initiated Successfully!</h3>";
    echo "  <p>Please check your phone (<strong>".$userPhone."</strong>). An M-Pesa popup prompt has been sent requesting your PIN.</p>";
    echo "  <p>Once you authorize the transaction, refresh your <a href='chat.php?item_id=".$claimData['item_id']."'>Chat Link here</a> to start talking.</p>";
    echo "</div>";
} else {
    echo "M-Pesa API Error: " . ($stkResponse['ResponseDescription'] ?? 'Unknown dispatch error occurred.');
}
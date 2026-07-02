<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

$conversation_id = (int) ($_GET['id'] ?? 0);
if ($conversation_id <= 0) {
    header("Location: inbox.php");
    exit();
}

// 1. FETCH THE CONVERSATION, ITS ITEM, AND BOTH PARTICIPANTS
$conv_sql = "SELECT conv.*, i.title AS item_title, i.id AS item_id, i.status AS item_status,
                    poster.full_name AS poster_name, poster.account_type AS poster_account_type,
                    claimant.full_name AS claimant_name, claimant.account_type AS claimant_account_type
             FROM conversations conv
             INNER JOIN items i ON conv.item_id = i.id
             INNER JOIN users poster ON conv.poster_id = poster.id
             INNER JOIN users claimant ON conv.claimant_id = claimant.id
             WHERE conv.id = ?";
$conv_stmt = mysqli_prepare($conn, $conv_sql);
mysqli_stmt_bind_param($conv_stmt, "i", $conversation_id);
mysqli_stmt_execute($conv_stmt);
$conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($conv_stmt));
mysqli_stmt_close($conv_stmt);

if (!$conversation) {
    header("Location: inbox.php");
    exit();
}

$is_poster = ((int) $conversation['poster_id'] === (int) $_SESSION['user_id']);
$is_claimant = ((int) $conversation['claimant_id'] === (int) $_SESSION['user_id']);

// 2. ONLY THE TWO PEOPLE IN THIS CONVERSATION MAY VIEW OR POST TO IT
if (!$is_poster && !$is_claimant) {
    header("Location: inbox.php");
    exit();
}

// =====================================================================
// MONETIZATION CHECK: VALIDATE PAYWALL GATEWAY STATUS
// =====================================================================
$pay_check_sql = "SELECT * FROM item_claims WHERE item_id = ? AND loser_id = ? AND finder_id = ?";
$pay_stmt = mysqli_prepare($conn, $pay_check_sql);
mysqli_stmt_bind_param($pay_stmt, "iii", $conversation['item_id'], $conversation['claimant_id'], $conversation['poster_id']);
mysqli_stmt_execute($pay_stmt);
$payment_record = mysqli_fetch_assoc(mysqli_stmt_get_result($pay_stmt));
mysqli_stmt_close($pay_stmt);

$is_chat_unlocked = true;

// If a record exists and it is unpaid, enforce the paywall ONLY for the claimant (loser)
if ($payment_record) {
    if ($payment_record['payment_status'] === 'unpaid') {
        $is_chat_unlocked = false;
        
        if ($is_claimant) {
            // Render beautiful M-Pesa paywall skin and halt downstream script processing
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Unlock Chat Gateway</title>
                <link rel="stylesheet" href="assets/css/style.css">
                <style>
                    .pay-box { max-width: 450px; margin: 100px auto; padding: 30px; border: 1px solid #ddd; border-radius: 8px; text-align: center; font-family: sans-serif; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                    .mpesa-btn { background-color: #33b5e5; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 16px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 15px; }
                    .mpesa-btn:hover { background-color: #008cc1; }
                    .input-phone { width: 94%; padding: 11px; margin: 12px 0; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; }
                    .badge-price { background: #e1f5fe; color: #0288d1; padding: 4px 10px; border-radius: 20px; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="pay-box">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/512px-M-PESA_LOGO-01.svg.png" width="130" alt="M-Pesa"><br><br>
                    <h2>Unlock Conversation Access</h2>
                    <p>Your claim validation request was approved! To unlock direct messaging with the finder, pay a small platform maintenance fee of <span class="badge-price">KES 20.00</span>.</p>
                    <p style="font-size:12px; color:gray; line-height:1.4;">If you confirm this item is a mismatch after chatting, KES 18.00 will be instantly reversed into your system wallet account (KES 2 retained for service charges).</p>
                    
                    <form action="trigger_pay.php" method="POST">
                        <input type="hidden" name="claim_track_id" value="<?php echo $payment_record['id']; ?>">
                        <input type="text" name="phone" class="input-phone" placeholder="e.g. 0712345678" required>
                        <button type="submit" class="mpesa-btn">Request M-Pesa STK Push</button>
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit();
        }
    }
}

$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

// 3. HANDLE SENDING A NEW MESSAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_text = trim($_POST['message'] ?? '');
    $send_error = '';
    $sent_message = null;

    if ($message_text === '') {
        $send_error = "Message cannot be empty.";
    } else {
        // MEASURE 2: Apply regular expression filter if the user hasn't cleared payment or is bypassing
        $phonePattern = '/(\+?254|0)[17]\d{8}/'; 
        if (!$is_chat_unlocked && preg_match($phonePattern, $message_text)) {
            $message_text = preg_replace($phonePattern, "[Contact details hidden until payment settled]", $message_text);
        }

        $insert_sql = "INSERT INTO messages (conversation_id, sender_id, message) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "iis", $conversation_id, $_SESSION['user_id'], $message_text);

        if (mysqli_stmt_execute($insert_stmt)) {
            $sent_message = [
                'id'         => mysqli_insert_id($conn),
                'message'    => $message_text,
                'is_mine'    => true,
                'time_label' => date('g:i A'),
            ];
        } else {
            $send_error = "Could not send your message. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo $sent_message
            ? json_encode(['success' => true, 'message' => $sent_message])
            : json_encode(['success' => false, 'error' => $send_error]);
        exit();
    }

    header("Location: chat.php?id=" . $conversation_id);
    exit();
}

// 4. MARK THE OTHER PERSON'S MESSAGES AS READ, SINCE WE'RE VIEWING THEM NOW
$mark_read_sql = "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
$mark_read_stmt = mysqli_prepare($conn, $mark_read_sql);
mysqli_stmt_bind_param($mark_read_stmt, "ii", $conversation_id, $_SESSION['user_id']);
mysqli_stmt_execute($mark_read_stmt);
mysqli_stmt_close($mark_read_stmt);

// 5. FETCH THE FULL MESSAGE THREAD
$messages_sql = "SELECT id, sender_id, message, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC";
$messages_stmt = mysqli_prepare($conn, $messages_sql);
mysqli_stmt_bind_param($messages_stmt, "i", $conversation_id);
mysqli_stmt_execute($messages_stmt);
$messages_result = mysqli_stmt_get_result($messages_stmt);

$other_name = $is_poster ? $conversation['claimant_name'] : $conversation['poster_name'];
$other_account_type = $is_poster ? $conversation['claimant_account_type'] : $conversation['poster_account_type'];

$extra_scripts = '<script src="assets/js/chat.js"></script>';
$page_title = $other_name . " — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 760px;">
        
        <?php if ($payment_record && $payment_record['payment_status'] === 'paid'): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2" style="border-left: 5px solid #2e7d32;">
                <div>
                    <i class="bi bi-shield-check-fill me-2 text-success"></i>
                    <strong>Payment Verified Secure Handoff:</strong> 
                    <?php if ($is_claimant): ?>
                        Provide this Verification PIN to the finder when you safely meet up and receive your item.
                    <?php else: ?>
                        Do not release this item until the claimant gives you their generated 4-digit confirmation PIN.
                    <?php endif; ?>
                </div>
                <div>
                    <span style="font-size: 18px; font-weight: bold; background: white; padding: 6px 14px; border-radius: 4px; letter-spacing: 2px; border: 1px dashed #2e7d32;">
                        <?php echo htmlspecialchars($payment_record['handoff_pin']); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-0">
                    <?php echo h($other_name); ?>
                    <?php if ($other_account_type === 'institution'): ?>
                        <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i> Institution</span>
                    <?php endif; ?>
                </h4>
                <a href="item-details.php?id=<?php echo (int) $conversation['item_id']; ?>" class="text-soft small text-decoration-none">
                    <i class="bi bi-tag"></i> <?php echo h($conversation['item_title']); ?>
                    <span class="status-pill status-<?php echo $conversation['item_status']; ?> ms-1"><?php echo h($conversation['item_status']); ?></span>
                </a>
            </div>
            <div class="d-flex gap-2">
                <?php if ($payment_record && $payment_record['payment_status'] === 'paid' && $is_claimant): ?>
                    <a href="process_mismatch.php?claim_id=<?php echo $payment_record['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure this found item is not yours? This will close the chat and refund KES 18.00 back to your profile wallet.');">
                        <i class="bi bi-x-circle"></i> Not My Item (Refund)
                    </a>
                <?php endif; ?>
                <a href="inbox.php" class="btn btn-outline-brand btn-sm"><i class="bi bi-arrow-left"></i> Inbox</a>
            </div>
        </div>

        <div class="chat-window">
            <div class="chat-thread" id="chatThread" data-conversation-id="<?php echo $conversation_id; ?>">
                <?php if (mysqli_num_rows($messages_result) === 0): ?>
                    <div class="empty-state">
                        <p class="mb-0">Say hello and get the conversation started.</p>
                    </div>
                <?php else: ?>
                    <?php while ($msg = mysqli_fetch_assoc($messages_result)): ?>
                        <?php $is_mine = ((int) $msg['sender_id'] === (int) $_SESSION['user_id']); ?>
                        <div class="chat-bubble <?php echo $is_mine ? 'sent' : 'received'; ?>" data-message-id="<?php echo (int) $msg['id']; ?>">
                            <?php echo nl2br(h($msg['message'])); ?>
                            <span class="chat-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
            <div class="chat-input-row">
                <form id="chatForm" method="POST" action="chat.php?id=<?php echo $conversation_id; ?>" class="d-flex gap-2">
                    <textarea name="message" id="chatMessageInput" class="form-control" rows="1" placeholder="Type a message..." required></textarea>
                    <button type="submit" id="chatSendBtn" class="btn btn-brand"><i class="bi bi-send-fill"></i></button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

$item_id = (int) ($_GET['item_id'] ?? 0);
if ($item_id <= 0) {
    header("Location: browse.php");
    exit();
}

// 1. FETCH THE ITEM BEING CLAIMED
$sql = "SELECT i.*, u.full_name AS poster_name, u.account_type AS poster_account_type
        FROM items i
        INNER JOIN users u ON i.user_id = u.id
        WHERE i.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $item_id);
mysqli_stmt_execute($stmt);
$item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$item) {
    header("Location: browse.php");
    exit();
}

// Can't claim your own listing, and can't claim something no longer open.
if ((int) $item['user_id'] === (int) $_SESSION['user_id'] || $item['status'] !== 'open') {
    header("Location: item-details.php?id=" . $item_id);
    exit();
}

// 2. IF THIS USER ALREADY HAS A CLAIM ON THIS ITEM, SEND THEM STRAIGHT TO THE CHAT
$existing_sql = "SELECT id FROM claims WHERE item_id = ? AND claimant_id = ?";
$existing_stmt = mysqli_prepare($conn, $existing_sql);
mysqli_stmt_bind_param($existing_stmt, "ii", $item_id, $_SESSION['user_id']);
mysqli_stmt_execute($existing_stmt);
$already_claimed = mysqli_fetch_assoc(mysqli_stmt_get_result($existing_stmt)) !== null;
mysqli_stmt_close($existing_stmt);

if ($already_claimed) {
    $conv_sql = "SELECT id FROM conversations WHERE item_id = ? AND claimant_id = ?";
    $conv_stmt = mysqli_prepare($conn, $conv_sql);
    mysqli_stmt_bind_param($conv_stmt, "ii", $item_id, $_SESSION['user_id']);
    mysqli_stmt_execute($conv_stmt);
    $conv_row = mysqli_fetch_assoc(mysqli_stmt_get_result($conv_stmt));
    mysqli_stmt_close($conv_stmt);

    if ($conv_row) {
        header("Location: chat.php?id=" . (int) $conv_row['id']);
        exit();
    }
}

$payment_record = null;
if (!$is_owner && !$already_claimed) {
    $payment_sql = "SELECT * FROM item_claims WHERE item_id = ? AND loser_id = ? AND finder_id = ?";
    $payment_stmt = mysqli_prepare($conn, $payment_sql);
    mysqli_stmt_bind_param($payment_stmt, "iii", $item_id, $_SESSION['user_id'], $item['user_id']);
    mysqli_stmt_execute($payment_stmt);
    $payment_record = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_stmt));
    mysqli_stmt_close($payment_stmt);

    if (!$payment_record && $item['status'] === 'open') {
        $init_payment_sql = "INSERT INTO item_claims (item_id, loser_id, finder_id, amount_paid, payment_status) VALUES (?, ?, ?, 20.00, 'unpaid')";
        $payment_stmt = mysqli_prepare($conn, $init_payment_sql);
        mysqli_stmt_bind_param($payment_stmt, "iii", $item_id, $_SESSION['user_id'], $item['user_id']);
        mysqli_stmt_execute($payment_stmt);
        mysqli_stmt_close($payment_stmt);

        $payment_stmt = mysqli_prepare($conn, $payment_sql);
        mysqli_stmt_bind_param($payment_stmt, "iii", $item_id, $_SESSION['user_id'], $item['user_id']);
        mysqli_stmt_execute($payment_stmt);
        $payment_record = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_stmt));
        mysqli_stmt_close($payment_stmt);
    }
}

$errors = [];
$proof_message = '';

// 3. PROCESS THE CLAIM SUBMISSION
if (isset($_POST['submit_claim'])) {
    $proof_message = trim($_POST['proof_message'] ?? '');
    if ($proof_message === '') {
        $errors[] = "Please describe why you believe this item is yours.";
    }

    if (!$payment_record || $payment_record['payment_status'] !== 'paid') {
        $errors[] = "Please complete the KES 20 payment before submitting your claim.";
    }

    if (empty($errors)) {
        // Record the claim itself, sitting at "pending" until the poster reviews it.
        $insert_claim_sql = "INSERT INTO claims (item_id, claimant_id, proof_message, status) VALUES (?, ?, ?, 'pending')";
        $insert_claim_stmt = mysqli_prepare($conn, $insert_claim_sql);
        mysqli_stmt_bind_param($insert_claim_stmt, "iis", $item_id, $_SESSION['user_id'], $proof_message);
        mysqli_stmt_execute($insert_claim_stmt);
        mysqli_stmt_close($insert_claim_stmt);

        // Find or create the conversation between the poster and this claimant.
        $find_conv_sql = "SELECT id FROM conversations WHERE item_id = ? AND claimant_id = ?";
        $find_conv_stmt = mysqli_prepare($conn, $find_conv_sql);
        mysqli_stmt_bind_param($find_conv_stmt, "ii", $item_id, $_SESSION['user_id']);
        mysqli_stmt_execute($find_conv_stmt);
        $conv_row = mysqli_fetch_assoc(mysqli_stmt_get_result($find_conv_stmt));
        mysqli_stmt_close($find_conv_stmt);

        if ($conv_row) {
            $conversation_id = (int) $conv_row['id'];
        } else {
            $create_conv_sql = "INSERT INTO conversations (item_id, poster_id, claimant_id) VALUES (?, ?, ?)";
            $create_conv_stmt = mysqli_prepare($conn, $create_conv_sql);
            mysqli_stmt_bind_param($create_conv_stmt, "iii", $item_id, $item['user_id'], $_SESSION['user_id']);
            mysqli_stmt_execute($create_conv_stmt);
            $conversation_id = mysqli_insert_id($conn);
            mysqli_stmt_close($create_conv_stmt);
        }

        // Drop the proof message into the chat as the opening message, so the
        // poster sees it the moment they open their inbox — no separate step needed.
        $seed_text = "Claim submitted for \"" . $item['title'] . "\": " . $proof_message;
        $seed_sql = "INSERT INTO messages (conversation_id, sender_id, message) VALUES (?, ?, ?)";
        $seed_stmt = mysqli_prepare($conn, $seed_sql);
        mysqli_stmt_bind_param($seed_stmt, "iis", $conversation_id, $_SESSION['user_id'], $seed_text);
        mysqli_stmt_execute($seed_stmt);
        mysqli_stmt_close($seed_stmt);

        header("Location: chat.php?id=" . $conversation_id);
        exit();
    }
}

$page_title = "Claim Item — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 600px;">
        <div class="form-card">
            <h2 class="mb-1">Claim This Item</h2>
            <p class="text-soft mb-4">
                Tell <?php echo h($item['poster_name']); ?> why this is yours. Be specific —
                a serial number, a unique mark, or what was inside a bag all help confirm a real match.
            </p>

            <div class="d-flex align-items-center gap-2 mb-4 p-3" style="background: var(--color-bg); border-radius: 10px;">
                <span class="status-pill status-<?php echo $item['status']; ?>"><?php echo h($item['status']); ?></span>
                <span class="fw-semibold"><?php echo h($item['title']); ?></span>
                <span class="ref-code ms-auto"><?php echo item_ref_code($item); ?></span>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($payment_record && $payment_record['payment_status'] === 'unpaid'): ?>
                <div class="alert alert-warning">
                    A small KES 20 fee is required before you can submit your claim and access the chat.
                    Please complete the payment below, then return to this page to continue.
                </div>
                <form action="trigger_pay.php" method="POST">
                    <input type="hidden" name="claim_track_id" value="<?php echo $payment_record['id']; ?>">
                    <?php echo csrf_input_field(); ?>
                    <div class="mb-4">
                        <label for="phone" class="form-label">M-Pesa Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control" placeholder="e.g. 0712345678" required>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2">Pay KES 20 to Claim</button>
                </form>
            <?php else: ?>
                <form method="POST" action="claim.php?item_id=<?php echo (int) $item_id; ?>">
                    <div class="mb-4">
                        <label for="proof_message" class="form-label">Why is this item yours?</label>
                        <textarea name="proof_message" id="proof_message" class="form-control" rows="5" placeholder="e.g. It's a black Tecno phone with a cracked top-left corner, lock screen has a photo of..." required><?php echo h($proof_message); ?></textarea>
                    </div>
                    <button type="submit" name="submit_claim" class="btn btn-brand w-100 py-2">Submit Claim &amp; Start Chat</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

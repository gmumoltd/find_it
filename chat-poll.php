<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$conversation_id = (int) ($_GET['id'] ?? 0);
$after_id = (int) ($_GET['after_id'] ?? 0);

if ($conversation_id <= 0) {
    echo json_encode(['messages' => []]);
    exit();
}

// Only a participant in this conversation may poll its messages.
$conv_sql = "SELECT poster_id, claimant_id FROM conversations WHERE id = ?";
$conv_stmt = mysqli_prepare($conn, $conv_sql);
mysqli_stmt_bind_param($conv_stmt, "i", $conversation_id);
mysqli_stmt_execute($conv_stmt);
$conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($conv_stmt));
mysqli_stmt_close($conv_stmt);

$is_participant = $conversation
    && ((int) $conversation['poster_id'] === (int) $_SESSION['user_id'] || (int) $conversation['claimant_id'] === (int) $_SESSION['user_id']);

if (!$is_participant) {
    http_response_code(403);
    echo json_encode(['error' => 'Not allowed']);
    exit();
}

// Any messages newer than the last one the browser already has.
$messages_sql = "SELECT id, sender_id, message, created_at FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at ASC";
$messages_stmt = mysqli_prepare($conn, $messages_sql);
mysqli_stmt_bind_param($messages_stmt, "ii", $conversation_id, $after_id);
mysqli_stmt_execute($messages_stmt);
$result = mysqli_stmt_get_result($messages_stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Sent as raw text on purpose — the browser-side script escapes it
    // before inserting into the page, so we don't double-encode here.
    $messages[] = [
        'id'         => (int) $row['id'],
        'message'    => $row['message'],
        'is_mine'    => ((int) $row['sender_id'] === (int) $_SESSION['user_id']),
        'time_label' => date('g:i A', strtotime($row['created_at'])),
    ];
}
mysqli_stmt_close($messages_stmt);

// Whatever we just delivered from the other person counts as read now.
$mark_read_sql = "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
$mark_read_stmt = mysqli_prepare($conn, $mark_read_sql);
mysqli_stmt_bind_param($mark_read_stmt, "ii", $conversation_id, $_SESSION['user_id']);
mysqli_stmt_execute($mark_read_stmt);
mysqli_stmt_close($mark_read_stmt);

echo json_encode(['messages' => $messages]);

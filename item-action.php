<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['do'] ?? '';

if ($id <= 0 || !in_array($action, ['resolve', 'delete'], true)) {
    header("Location: my-items.php");
    exit();
}

// Only the item's own poster may resolve or delete it.
$check_sql = "SELECT id, photo FROM items WHERE id = ? AND user_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $id, $_SESSION['user_id']);
mysqli_stmt_execute($check_stmt);
$item = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
mysqli_stmt_close($check_stmt);

if (!$item) {
    header("Location: my-items.php");
    exit();
}

if ($action === 'resolve') {
    $update_sql = "UPDATE items SET status = 'resolved', updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
} elseif ($action === 'delete') {
    // Claims, conversations, and messages tied to this item are removed
    // automatically via ON DELETE CASCADE in the database schema.
    $delete_sql = "DELETE FROM items WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "i", $id);
    mysqli_stmt_execute($delete_stmt);
    mysqli_stmt_close($delete_stmt);

    // Clean up the uploaded photo file from disk too, if there was one.
    if (!empty($item['photo'])) {
        $photo_path = ITEM_UPLOAD_DIR . $item['photo'];
        if (is_file($photo_path)) {
            unlink($photo_path);
        }
    }
}

header("Location: my-items.php");
exit();

<?php
// =====================================================================
// Helper functions used across the site.
// Keeping these in one place means every page checks logins, uploads
// photos, and formats dates the exact same way.
// =====================================================================

// -----------------------------------------------------------------
// 1. LOGIN HELPERS
// -----------------------------------------------------------------

// True if someone is currently logged in.
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// Send a guest to the login page. Call this at the top of any page
// that should only be seen by logged-in users.
function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// -----------------------------------------------------------------
// 2. OUTPUT / FORMATTING HELPERS
// -----------------------------------------------------------------

// Shortcut so we don't keep typing htmlspecialchars(...) everywhere.
function h($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Turns a timestamp into "2 hours ago", "Just now", "3 days ago", etc.
// Used in chat threads and the inbox list.
function time_ago($datetime)
{
    $seconds = time() - strtotime($datetime);

    if ($seconds < 60) {
        return "Just now";
    }
    $minutes = floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ($minutes == 1 ? " minute ago" : " minutes ago");
    }
    $hours = floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ($hours == 1 ? " hour ago" : " hours ago");
    }
    $days = floor($hours / 24);
    if ($days < 7) {
        return $days . ($days == 1 ? " day ago" : " days ago");
    }
    return date("d M Y", strtotime($datetime));
}

// Builds a short reference code for an item, e.g. "LF-2606-0014".
// Purely cosmetic — makes each listing feel like a real claim ticket.
function item_ref_code($item)
{
    $prefix = ($item['item_type'] === 'lost') ? 'LT' : 'FD';
    return $prefix . '-' . date('ym', strtotime($item['created_at'])) . '-' . str_pad($item['id'], 4, '0', STR_PAD_LEFT);
}

// -----------------------------------------------------------------
// 3. PHOTO UPLOAD HELPER
//    Validates the uploaded file and moves it into the given folder.
//    Returns the new filename on success, or false on failure (with
//    $error filled in so the calling page can show it).
// -----------------------------------------------------------------
function handle_photo_upload($file_field, $destination_folder, &$error)
{
    // No file chosen is not an error — photo is optional on most forms.
    if (!isset($_FILES[$file_field]) || $_FILES[$file_field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$file_field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "There was a problem uploading the photo. Please try again.";
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $error = "That photo is too large. Please use an image under 5MB.";
        return false;
    }

    // Check the REAL file type (not just the filename) to stop someone
    // renaming a .php file to .jpg.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
        $error = "Only JPG, PNG, or WEBP photos are allowed.";
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        $extension = 'jpg';
    }

    // Random, unique filename so two people uploading "photo.jpg" never collide.
    $new_filename = uniqid('img_', true) . '_' . time() . '.' . $extension;

    if (!is_dir($destination_folder)) {
        mkdir($destination_folder, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination_folder . $new_filename)) {
        $error = "Could not save the uploaded photo. Please try again.";
        return false;
    }

    return $new_filename;
}

// -----------------------------------------------------------------
// 4. SMALL DATA LOOKUPS USED ON SEVERAL PAGES
// -----------------------------------------------------------------

// Counts unread messages waiting for the given user, across every
// conversation they are part of. Used for the navbar inbox badge.
function count_unread_messages($conn, $user_id)
{
    $sql = "SELECT COUNT(*) AS total
            FROM messages m
            INNER JOIN conversations c ON m.conversation_id = c.id
            WHERE (c.poster_id = ? OR c.claimant_id = ?)
              AND m.sender_id != ?
              AND m.is_read = 0";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) $row['total'];
}

// Counts pending claims sitting on items the given user posted.
// Used for the navbar / dashboard "needs your attention" badge.
function count_pending_claims_received($conn, $user_id)
{
    $sql = "SELECT COUNT(*) AS total
            FROM claims cl
            INNER JOIN items i ON cl.item_id = i.id
            WHERE i.user_id = ? AND cl.status = 'pending'";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) $row['total'];
}

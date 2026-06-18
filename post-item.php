<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login(); // must be logged in to post or edit a listing

$edit_id = (int) ($_GET['id'] ?? 0);
$is_edit = $edit_id > 0;

$old = [
    'item_type'   => (($_GET['type'] ?? '') === 'found') ? 'found' : 'lost',
    'category_id' => '',
    'title'       => '',
    'description' => '',
    'location'    => '',
    'item_date'   => date('Y-m-d'),
];
$existing_photo = null;
$errors = [];

// 1. IF EDITING, LOAD THE ITEM AND CONFIRM THIS USER OWNS IT
if ($is_edit) {
    $fetch_sql = "SELECT * FROM items WHERE id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $edit_id);
    mysqli_stmt_execute($fetch_stmt);
    $existing_item = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));
    mysqli_stmt_close($fetch_stmt);

    if (!$existing_item || (int) $existing_item['user_id'] !== (int) $_SESSION['user_id']) {
        header("Location: my-items.php");
        exit();
    }

    $old['item_type']   = $existing_item['item_type'];
    $old['category_id'] = $existing_item['category_id'];
    $old['title']       = $existing_item['title'];
    $old['description'] = $existing_item['description'];
    $old['location']    = $existing_item['location'];
    $old['item_date']   = $existing_item['item_date'];
    $existing_photo     = $existing_item['photo'];
}

// 2. PROCESS THE FORM (covers both creating and saving edits)
if (isset($_POST['save_item'])) {
    $old['item_type']   = (($_POST['item_type'] ?? 'lost') === 'found') ? 'found' : 'lost';
    $old['category_id'] = $_POST['category_id'] ?? '';
    $old['title']       = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['location']    = trim($_POST['location'] ?? '');
    $old['item_date']   = $_POST['item_date'] ?? '';

    // 3. VALIDATE
    if ($old['title'] === '') {
        $errors[] = "Please give the item a short title.";
    }
    if ($old['description'] === '') {
        $errors[] = "Please add a description.";
    }
    if ($old['location'] === '') {
        $errors[] = "Please add a location.";
    }
    if ($old['item_date'] === '') {
        $errors[] = "Please pick a date.";
    }

    $category_id_value = (ctype_digit((string) $old['category_id'])) ? (int) $old['category_id'] : null;

    // 4. HANDLE AN OPTIONAL NEW PHOTO (keeps the existing one if none uploaded)
    $photo_filename = $existing_photo;
    $upload_error = '';
    $uploaded = handle_photo_upload('photo', ITEM_UPLOAD_DIR, $upload_error);
    if ($uploaded === false) {
        $errors[] = $upload_error;
    } elseif ($uploaded !== null) {
        $photo_filename = $uploaded;
    }

    // 5. SAVE
    if (empty($errors)) {
        if ($is_edit) {
            $save_sql = "UPDATE items
                         SET category_id=?, item_type=?, title=?, description=?, location=?, item_date=?, photo=?, updated_at=NOW()
                         WHERE id=?";
            $save_stmt = mysqli_prepare($conn, $save_sql);
            mysqli_stmt_bind_param(
                $save_stmt,
                "issssssi",
                $category_id_value,
                $old['item_type'],
                $old['title'],
                $old['description'],
                $old['location'],
                $old['item_date'],
                $photo_filename,
                $edit_id
            );
            $success = mysqli_stmt_execute($save_stmt);
            mysqli_stmt_close($save_stmt);
            $redirect_id = $edit_id;
        } else {
            $save_sql = "INSERT INTO items (user_id, category_id, item_type, title, description, location, item_date, photo)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $save_stmt = mysqli_prepare($conn, $save_sql);
            mysqli_stmt_bind_param(
                $save_stmt,
                "iissssss",
                $_SESSION['user_id'],
                $category_id_value,
                $old['item_type'],
                $old['title'],
                $old['description'],
                $old['location'],
                $old['item_date'],
                $photo_filename
            );
            $success = mysqli_stmt_execute($save_stmt);
            $redirect_id = $success ? mysqli_insert_id($conn) : null;
            mysqli_stmt_close($save_stmt);
        }

        if ($success) {
            header("Location: item-details.php?id=" . (int) $redirect_id);
            exit();
        } else {
            $errors[] = "Something went wrong saving your listing. Please try again.";
        }
    }
}

$categories_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");

$page_title = ($is_edit ? "Edit Listing" : "Report an Item") . " — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 700px;">
        <div class="form-card">
            <h2 class="mb-1"><?php echo $is_edit ? "Edit Your Listing" : "Report a Lost or Found Item"; ?></h2>
            <p class="text-soft mb-4">A clear photo and description make it much easier for a match to find you.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="post-item.php<?php echo $is_edit ? '?id=' . (int) $edit_id : ''; ?>" enctype="multipart/form-data">

                <div class="account-type-toggle btn-group w-100 mb-4" role="group">
                    <input type="radio" class="btn-check" name="item_type" id="itemTypeLost" value="lost" autocomplete="off" <?php echo ($old['item_type'] === 'lost') ? 'checked' : ''; ?>>
                    <label class="btn" for="itemTypeLost"><i class="bi bi-exclamation-circle me-1"></i> I Lost Something</label>

                    <input type="radio" class="btn-check" name="item_type" id="itemTypeFound" value="found" autocomplete="off" <?php echo ($old['item_type'] === 'found') ? 'checked' : ''; ?>>
                    <label class="btn" for="itemTypeFound"><i class="bi bi-check-circle me-1"></i> I Found Something</label>
                </div>

                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Black Samsung phone, cracked screen" value="<?php echo h($old['title']); ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">Select a category...</option>
                            <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo (int) $cat['id']; ?>" <?php echo ((string) $old['category_id'] === (string) $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="item_date" class="form-label">Date</label>
                        <input type="date" name="item_date" id="item_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo h($old['item_date']); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Wote town, Makueni" value="<?php echo h($old['location']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="4" placeholder="Colour, brand, identifying marks, contents..." required><?php echo h($old['description']); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="photo" class="form-label">Photo <span class="text-soft">(optional, but recommended)</span></label>
                    <?php if (!empty($existing_photo)): ?>
                        <div class="mb-2">
                            <img src="uploads/items/<?php echo h($existing_photo); ?>" alt="Current photo" style="max-width: 140px; border-radius: 10px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="photo" id="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>

                <button type="submit" name="save_item" class="btn btn-brand w-100 py-2">
                    <?php echo $is_edit ? "Save Changes" : "Post Listing"; ?>
                </button>
            </form>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

<?php
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';
require_login();

// 1. LOAD THE CURRENT USER
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);

if (!$user) {
    header("Location: logout.php");
    exit();
}

$errors = [];
$success_message = '';

$old = [
    'full_name'        => $user['full_name'],
    'phone'            => $user['phone'],
    'location'         => $user['location'],
    'institution_type' => $user['institution_type'],
];

// 2. PROCESS THE UPDATE
if (isset($_POST['update_profile'])) {
    $old['full_name']        = trim($_POST['full_name'] ?? '');
    $old['phone']            = trim($_POST['phone'] ?? '');
    $old['location']         = trim($_POST['location'] ?? '');
    $old['institution_type'] = trim($_POST['institution_type'] ?? '');
    $new_password         = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // 3. VALIDATE
    if ($old['full_name'] === '') {
        $errors[] = ($user['account_type'] === 'institution') ? "Institution name is required." : "Full name is required.";
    }
    if ($user['account_type'] === 'institution' && $old['institution_type'] === '') {
        $errors[] = "Please select an institution type.";
    }

    $password_changing = ($new_password !== '' || $confirm_new_password !== '');
    if ($password_changing) {
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        }
        if ($new_password !== $confirm_new_password) {
            $errors[] = "New passwords do not match.";
        }
    }

    // 4. HANDLE AN OPTIONAL NEW PROFILE PHOTO
    $photo_filename = $user['photo'];
    $upload_error = '';
    $uploaded = handle_photo_upload('photo', PROFILE_UPLOAD_DIR, $upload_error);
    if ($uploaded === false) {
        $errors[] = $upload_error;
    } elseif ($uploaded !== null) {
        $photo_filename = $uploaded;
    }

    // 5. SAVE
    if (empty($errors)) {
        $institution_type_value = ($user['account_type'] === 'institution') ? $old['institution_type'] : null;

        if ($password_changing) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET full_name=?, institution_type=?, phone=?, location=?, photo=?, password=? WHERE id=?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssssi",
                $old['full_name'],
                $institution_type_value,
                $old['phone'],
                $old['location'],
                $photo_filename,
                $hashed_password,
                $_SESSION['user_id']
            );
        } else {
            $update_sql = "UPDATE users SET full_name=?, institution_type=?, phone=?, location=?, photo=? WHERE id=?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $update_stmt,
                "sssssi",
                $old['full_name'],
                $institution_type_value,
                $old['phone'],
                $old['location'],
                $photo_filename,
                $_SESSION['user_id']
            );
        }

        $success = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        if ($success) {
            $_SESSION['full_name'] = $old['full_name']; // keep the navbar in sync immediately
            $user['photo'] = $photo_filename;
            $success_message = "Profile updated successfully.";
        } else {
            $errors[] = "Something went wrong saving your profile. Please try again.";
        }
    }
}

$page_title = "My Profile — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 600px;">
        <div class="form-card">
            <h2 class="mb-1">My Profile</h2>
            <p class="text-soft mb-4">
                <?php echo h($user['email']); ?>
                <?php if ($user['account_type'] === 'institution'): ?>
                    <span class="institution-badge ms-1"><i class="bi bi-patch-check-fill"></i> Institution</span>
                <?php endif; ?>
            </p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo h($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php" enctype="multipart/form-data">

                <div class="mb-4 d-flex align-items-center gap-3">
                    <div style="width: 72px; height: 72px; border-radius: 50%; overflow: hidden; background: var(--color-bg); flex-shrink: 0;">
                        <?php if (!empty($user['photo'])): ?>
                            <img src="uploads/profiles/<?php echo h($user['photo']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-soft"><i class="bi bi-person fs-3"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <label for="photo" class="form-label mb-1">Profile Photo</label>
                        <input type="file" name="photo" id="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="full_name" class="form-label"><?php echo ($user['account_type'] === 'institution') ? 'Institution / Organisation Name' : 'Full Name'; ?></label>
                    <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo h($old['full_name']); ?>" required>
                </div>

                <?php if ($user['account_type'] === 'institution'): ?>
                    <div class="mb-3">
                        <label for="institution_type" class="form-label">Institution Type</label>
                        <select name="institution_type" id="institution_type" class="form-select">
                            <?php
                            $institution_types = ['School', 'Church', 'NGO', 'Police Post', 'Hospital', 'Company / Office', 'Other'];
                            foreach ($institution_types as $type):
                            ?>
                                <option value="<?php echo h($type); ?>" <?php echo ($old['institution_type'] === $type) ? 'selected' : ''; ?>><?php echo h($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" value="<?php echo h($old['phone']); ?>" required>
                </div>

                <div class="mb-4">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" name="location" id="location" class="form-control" value="<?php echo h($old['location']); ?>">
                </div>

                <hr class="mb-4">
                <h6 class="mb-3">Change Password <span class="text-soft">(optional)</span></h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control">
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-brand w-100 py-2 mt-2">Save Changes</button>
            </form>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

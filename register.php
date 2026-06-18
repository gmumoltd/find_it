<?php
// 1. Start the session and bounce logged-in users straight to their dashboard
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];

// Keep whatever the user typed so the form doesn't clear on a validation error
$old = [
    'account_type'     => 'individual',
    'full_name'        => '',
    'institution_type' => '',
    'email'            => '',
    'phone'            => '',
    'location'         => '',
];

// 2. PROCESS REGISTRATION (triggers when the form is submitted)
if (isset($_POST['register'])) {
    $old['account_type']     = (($_POST['account_type'] ?? 'individual') === 'institution') ? 'institution' : 'individual';
    $old['full_name']        = trim($_POST['full_name'] ?? '');
    $old['institution_type'] = trim($_POST['institution_type'] ?? '');
    $old['email']            = trim($_POST['email'] ?? '');
    $old['phone']            = trim($_POST['phone'] ?? '');
    $old['location']         = trim($_POST['location'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 3. VALIDATE INPUT
    if ($old['full_name'] === '') {
        $errors[] = ($old['account_type'] === 'institution') ? "Institution name is required." : "Full name is required.";
    }
    if ($old['account_type'] === 'institution' && $old['institution_type'] === '') {
        $errors[] = "Please select an institution type.";
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if ($old['phone'] === '') {
        $errors[] = "Phone number is required.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // 4. CHECK THE EMAIL ISN'T ALREADY REGISTERED
    if (empty($errors)) {
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $old['email']);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $errors[] = "That email is already registered. Try logging in instead.";
        }
        mysqli_stmt_close($check_stmt);
    }

    // 5. INSERT THE NEW USER (only the real institution_type when relevant)
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $institution_type_value = ($old['account_type'] === 'institution') ? $old['institution_type'] : null;

        $insert_sql = "INSERT INTO users (account_type, full_name, institution_type, email, phone, password, location)
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param(
            $insert_stmt,
            "sssssss",
            $old['account_type'],
            $old['full_name'],
            $institution_type_value,
            $old['email'],
            $old['phone'],
            $hashed_password,
            $old['location']
        );
        $success = mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);

        if ($success) {
            header("Location: login.php?registered=1");
            exit();
        } else {
            $errors[] = "Something went wrong creating your account. Please try again.";
        }
    }
}

$page_title = "Create an Account — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 560px;">
        <div class="form-card">
            <h2 class="mb-1">Create your account</h2>
            <p class="text-soft mb-4">It's free to post, browse, and chat.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" novalidate>

                <div class="account-type-toggle btn-group w-100 mb-4" role="group">
                    <input type="radio" class="btn-check" name="account_type" id="typeIndividual" value="individual" autocomplete="off" <?php echo ($old['account_type'] !== 'institution') ? 'checked' : ''; ?>>
                    <label class="btn" for="typeIndividual"><i class="bi bi-person me-1"></i> Individual</label>

                    <input type="radio" class="btn-check" name="account_type" id="typeInstitution" value="institution" autocomplete="off" <?php echo ($old['account_type'] === 'institution') ? 'checked' : ''; ?>>
                    <label class="btn" for="typeInstitution"><i class="bi bi-building me-1"></i> Institution</label>
                </div>

                <div class="mb-3">
                    <label for="full_name" class="form-label" id="fullNameLabel">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" placeholder="e.g. Wanjiku Kamau" value="<?php echo h($old['full_name']); ?>" required>
                </div>

                <div class="mb-3 <?php echo ($old['account_type'] === 'institution') ? '' : 'd-none'; ?>" id="institutionTypeWrap">
                    <label for="institution_type" class="form-label">Institution Type</label>
                    <select name="institution_type" id="institution_type" class="form-select">
                        <option value="">Select type...</option>
                        <?php
                        $institution_types = ['School', 'Church', 'NGO', 'Police Post', 'Hospital', 'Company / Office', 'Other'];
                        foreach ($institution_types as $type):
                        ?>
                            <option value="<?php echo h($type); ?>" <?php echo ($old['institution_type'] === $type) ? 'selected' : ''; ?>><?php echo h($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="you@example.com" value="<?php echo h($old['email']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" placeholder="07XX XXX XXX" value="<?php echo h($old['phone']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="location" class="form-label">Location <span class="text-soft">(optional)</span></label>
                    <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Wote, Makueni" value="<?php echo h($old['location']); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="At least 6 characters" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-brand w-100 py-2 mt-2">Create Account</button>
            </form>

            <p class="text-center text-soft mt-4 mb-0">
                Already have an account? <a href="login.php">Login</a>
            </p>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

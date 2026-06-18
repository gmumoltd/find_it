<?php
// 1. Start the session and bounce already-logged-in users to their dashboard
session_start();
require 'config/constants.php';
require 'config/db.php';
require 'includes/functions.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error_message = "";
$success_message = isset($_GET['registered']) ? "Account created successfully! You can now log in." : "";

// 2. PROCESS LOGIN (triggers when the form is submitted)
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Look the account up by email using a secure placeholder (?)
    $sql = "SELECT id, full_name, account_type, password FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // 3. VERIFY PASSWORD: match the typed password against the hashed one in the DB
        if (password_verify($password, $row['password'])) {
            // Correct! Set the session variables the rest of the site relies on.
            $_SESSION['user_id']      = $row['id'];
            $_SESSION['full_name']    = $row['full_name'];
            $_SESSION['account_type'] = $row['account_type'];

            mysqli_stmt_close($stmt);
            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Incorrect email or password.";
        }
    } else {
        $error_message = "Incorrect email or password.";
    }

    mysqli_stmt_close($stmt);
}

$page_title = "Login — " . SITE_NAME;
require 'includes/header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 460px;">
        <div class="form-card">
            <h2 class="mb-1">Welcome back</h2>
            <p class="text-soft mb-4">Log in to manage your reports and messages.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo h($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo h($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="you@example.com" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Your password" required>
                </div>

                <button type="submit" name="login" class="btn btn-brand w-100 py-2 mt-2">Login</button>
            </form>

            <p class="text-center text-soft mt-4 mb-0">
                Don't have an account? <a href="register.php">Sign up free</a>
            </p>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

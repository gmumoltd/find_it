</main>

<footer class="site-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-5">
                <div class="brand-wordmark mb-2">
                    <i class="bi bi-binoculars-fill"></i> Find<span class="brand-dot">Point</span>
                </div>
                <p class="text-white-50 small mb-0" style="max-width: 320px;">
                    <?php echo h(SITE_TAGLINE); ?> Built for individuals, schools, churches, and organisations across Kenya.
                </p>
            </div>
            <div class="col-md-3">
                <h6 class="text-white mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="browse.php">Browse Items</a></li>
                    <li class="mb-2"><a href="post-item.php?type=lost">Report Lost Item</a></li>
                    <li class="mb-2"><a href="post-item.php?type=found">Report Found Item</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="text-white mb-3">Account</h6>
                <ul class="list-unstyled small">
                    <?php if (is_logged_in()): ?>
                        <li class="mb-2"><a href="dashboard.php">My Dashboard</a></li>
                        <li class="mb-2"><a href="inbox.php">Messages</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="register.php">Create an Account</a></li>
                        <li class="mb-2"><a href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-4 mb-3">
        <p class="small text-white-50 mb-0">&copy; <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?>. All rights reserved.</p>
    </div>
</footer>

<!-- Bootstrap 5.3.3 JS bundle (includes Popper, needed for dropdowns) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>

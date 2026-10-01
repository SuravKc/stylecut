<?php
// ==============================================
// footer.php - Footer Template
// ==============================================
?>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Stylecut Nepal. All rights reserved.</p>
        </footer>
    </div>

    <!-- StyleCut Logout Confirmation Modal -->
    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['user_role'])): ?>
    <div id="logoutModal" class="logout-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" style="display: none;">
        <div class="logout-modal-card">
            <button type="button" class="logout-modal-close" id="logoutModalCloseBtn" aria-label="Close modal">&times;</button>
            <div class="logout-modal-icon-wrap">
                <span class="logout-modal-icon">🚪</span>
            </div>
            <h3 id="logoutModalTitle" class="logout-modal-title">Confirm Logout</h3>
            <p class="logout-modal-text">
                Are you sure you want to log out of <strong>StyleCut Nepal</strong><?php echo !empty($_SESSION['user_name']) ? ', <strong>' . htmlspecialchars($_SESSION['user_name']) . '</strong>' : ''; ?>?
            </p>
            <p class="logout-modal-subtext">
                You will need to sign in again to manage your bookings or access your dashboard.
            </p>
            <div class="logout-modal-actions">
                <button type="button" class="btn logout-cancel-btn" id="logoutCancelBtn">Cancel</button>
                <a href="/stylecut/logout.php?confirm=1" class="btn logout-confirm-btn" id="logoutConfirmBtn">Log Out</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="/stylecut/assets/js/main.js"></script>
</body>
</html> 
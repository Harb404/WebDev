<?php
/**
 * Shared login/profile/cart-toggle actions block for the top nav.
 * Included by index.php and Contact.php.
 * Expects $isLoggedIn and $cartCount to already be set by the caller.
 */
?>
    <div class="store-actions">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <div class="notif-wrap">
          <button class="nav-action notif-toggle" id="notif-toggle-btn" type="button" onclick="toggleNotifications()" aria-haspopup="true" aria-expanded="false" aria-label="Order notifications">
            <svg class="notif-bell" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3a6 6 0 0 0-6 6v3.2c0 .5-.16.99-.46 1.4L4 15.5c-.6.8-.02 2 .98 2h14.04c1 0 1.58-1.2.98-2l-1.54-1.9c-.3-.41-.46-.9-.46-1.4V9a6 6 0 0 0-6-6Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M9.5 19a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <span class="notif-count" id="notif-count" style="display:none;">0</span>
          </button>
          <div class="notif-panel" id="notif-panel" aria-hidden="true">
            <div class="notif-panel-head">Order Updates</div>
            <div class="notif-list" id="notif-list"><p class="notif-empty">No order updates yet.</p></div>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($isLoggedIn): ?>
        <a class="nav-action" href="account.php">Profile</a>
        <?php if (!empty($_SESSION['is_admin'])): ?><a class="nav-action" href="admin.php">Admin Panel</a><?php endif; ?>
        <a class="nav-action" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="nav-action" href="login.php">Login</a>
      <?php endif; ?>
      <button class="nav-action cart-toggle" id="cart-toggle-btn" type="button" onclick="toggleCart()">Cart <span class="cart-count" id="cart-count"<?php echo $cartCount > 0 ? '' : ' style="display:none;"'; ?>><?php echo $cartCount; ?></span></button>
    </div>

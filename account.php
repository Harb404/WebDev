<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validation.php';

require_once __DIR__ . '/includes/account-setup.php';
require_once __DIR__ . '/actions/account-actions.php';

require_once __DIR__ . '/includes/account-view-data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account — Masalihit Luxe</title>
<link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
<script src="script.js?v=<?php echo file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : time(); ?>" defer></script>
</head>
<body>
<main class="account-page<?php echo $isCheckout ? ' checkout-page' : ''; ?>">
  <div class="account-header">
    <div><div class="eyebrow">Masalihit Luxe</div><h1 class="display"><?php echo $isCheckout ? 'Checkout' : 'Your Account'; ?></h1></div>
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
      <a class="nav-action" href="index.php">Back to store</a>
    </div>
  </div>
  <?php if ($message): ?><div class="store-message order-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="form-error account-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php foreach ($orderNotifications as $notification): ?>
    <div class="order-notify order-notify-<?php echo htmlspecialchars($notification['status']); ?>">
      <span class="order-notify-icon" aria-hidden="true"><?php echo $notification['status'] === 'delivered' ? '✓' : '!'; ?></span>
      <span>
        <?php if ($notification['status'] === 'delivered'): ?>
          Good news — your order<?php echo $notification['ordered_at'] ? ' from ' . htmlspecialchars($notification['ordered_at']) : ''; ?> was delivered successfully.
        <?php else: ?>
          Your order<?php echo $notification['ordered_at'] ? ' from ' . htmlspecialchars($notification['ordered_at']) : ''; ?> could not be delivered. Any stock has been returned and no charge stands — contact us if you need help.
        <?php endif; ?>
      </span>
      <button type="button" class="order-notify-close" aria-label="Dismiss" onclick="this.closest('.order-notify').remove()">×</button>
    </div>
  <?php endforeach; ?>

  <div class="auth-modal-backdrop" id="buy-confirm-backdrop"></div>
  <div class="auth-modal buy-confirm-modal" id="buy-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="buy-confirm-title">
    <button class="cart-close auth-modal-close" type="button" onclick="closeBuyConfirm()" aria-label="Close">×</button>
    <h2 id="buy-confirm-title">Confirm Your Order</h2>
    <p class="buy-confirm-sub">You're about to place an order for <strong id="buy-confirm-total"></strong>. Please make sure your delivery details are correct  this can't be undone once placed.</p>
    <div class="auth-modal-actions">
      <button type="button" class="cart-button auth-modal-secondary" onclick="closeBuyConfirm()">Cancel</button>
      <button type="button" class="cart-button" id="buy-confirm-submit">Confirm &amp; Place Order</button>
    </div>
  </div>

  <?php if ($receiptNumber): ?>
    <div class="auth-modal-backdrop" id="receipt-modal-backdrop"></div>
    <div class="auth-modal receipt-modal" id="receipt-modal" data-auto-open="1" role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title">
      <button class="cart-close auth-modal-close" type="button" onclick="closeReceiptModal()" aria-label="Close">×</button>
      <div class="receipt-modal-icon" aria-hidden="true">✓</div>
      <h2 id="receipt-modal-title">Order Confirmed</h2>
      <p class="receipt-modal-sub">Thank you! Your order has been placed and is now pending.</p>
      <div class="receipt-number-box">
        <span class="receipt-number-label">Receipt Number</span>
        <span class="receipt-number-value" id="receipt-number-value"><?php echo htmlspecialchars($receiptNumber); ?></span>
        <button type="button" class="receipt-copy-btn" onclick="copyReceiptNumber()">Copy</button>
      </div>
      <p class="receipt-modal-note">Keep this number for your records. You can track your order's progress anytime from your Purchase History below.</p>
      <button class="cart-button receipt-modal-done" type="button" onclick="closeReceiptModal()">Got it</button>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['user_id'])): ?>
<?php include __DIR__ . '/account/profile-card.php'; ?>
<?php include __DIR__ . '/account/checkout.php'; ?>
<?php if (!$isCheckout): ?><div class="account-bottom-grid">
<?php endif; ?>
<?php include __DIR__ . '/account/order-history.php'; ?>
<?php include __DIR__ . '/account/chat-widget.php'; ?>
<?php if (!$isCheckout): ?></div>
<?php endif; ?>
<a class="back-link" href="logout.php">Log out</a>
  <?php else: ?>
<?php include __DIR__ . '/account/login-register.php'; ?>
  <?php endif; ?>
</main>
</body>
</html>
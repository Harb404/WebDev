<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validation.php';

$database = getDatabase();
$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}
$cart = $_SESSION['cart'] ?? [];
$error = '';
$message = '';
$showRegister = isset($_GET['register']);
$isCheckout = isset($_GET['checkout']);

if (!empty($_SESSION['is_admin']) && empty($_SESSION['user_id'])) {
  $statement = $database->prepare('SELECT id, name FROM users WHERE email = ?');
  $statement->execute(['admin@masalihit.local']);
  $adminUser = $statement->fetch(PDO::FETCH_ASSOC);
  if (!$adminUser) {
    $statement = $database->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $statement->execute(['Administrator', 'admin@masalihit.local', password_hash('admin123', PASSWORD_DEFAULT)]);
    $adminUser = ['id' => $database->lastInsertId(), 'name' => 'Administrator'];
  }
  $_SESSION['user_id'] = (int) $adminUser['id'];
  $_SESSION['user_name'] = $adminUser['name'];
}

$profile = [];
if (!empty($_SESSION['user_id'])) {
  $profileStatement = $database->prepare('SELECT name, email, address, location, zip, phone, comments, status FROM users WHERE id = ?');
  $profileStatement->execute([$_SESSION['user_id']]);
  $profile = $profileStatement->fetch(PDO::FETCH_ASSOC) ?: [];
  if (($profile['status'] ?? 'active') === 'banned') {
    unset($_SESSION['user_id'], $_SESSION['user_name']);
    header('Location: login.php');
    exit;
  }
}
$name = $profile['name'] ?? ($_SESSION['user_name'] ?? '');
$address = $profile['address'] ?? '';
$location = $profile['location'] ?? '';
$zip = $profile['zip'] ?? '';
$phone = $profile['phone'] ?? '';
$comments = $profile['comments'] ?? '';
$paymentMethod = 'cod';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';
  $address = trim($_POST['address'] ?? $address);
  $location = trim($_POST['location'] ?? $location);
  $zip = trim($_POST['zip'] ?? $zip);
  $phone = trim($_POST['phone'] ?? $phone);
  $comments = trim($_POST['comments'] ?? $comments);
  $paymentMethod = $_POST['payment_method'] ?? 'cod';
  if (!in_array($paymentMethod, ['cod', 'ewallet'], true)) {
    $paymentMethod = 'cod';
  }

  if ($action === 'register') {
    $error = validateRegistrationFields($name, $email, $password, $address, $location, $zip, $phone);
    if ($error === '') {
      try {
        $statement = $database->prepare('INSERT INTO users (name, email, password, address, location, zip, phone) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $address, $location, $zip, $phone]);
        // A brand-new customer account should never inherit an admin flag left
        // over from an earlier admin session in the same browser/tab.
        unset($_SESSION['is_admin']);
        $_SESSION['user_id'] = (int) $database->lastInsertId();
        $_SESSION['user_name'] = $name;
        header('Location: account.php?checkout=1');
        exit;
      } catch (PDOException $exception) {
        $error = 'That email is already registered.';
      }
    }
  }

  if ($action === 'update_profile') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in to update your account.';
    } else {
      $error = $name === '' ? 'Please enter your name.' : validateDeliveryFields($address, $location, $zip, $phone);
      if ($error === '') {
        $statement = $database->prepare('UPDATE users SET name = ?, address = ?, location = ?, zip = ?, phone = ? WHERE id = ?');
        $statement->execute([$name, $address, $location, $zip, $phone, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        $profile['name'] = $name;
        $profile['address'] = $address;
        $profile['location'] = $location;
        $profile['zip'] = $zip;
        $profile['phone'] = $phone;
        $message = 'Account details updated successfully.';
      }
    }
  }

  if ($action === 'login') {
    $statement = $database->prepare('SELECT id, name, password, status FROM users WHERE email = ?');
    $statement->execute([$email]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
      if (($user['status'] ?? 'active') === 'banned') {
        $error = 'This account has been suspended. Contact support if you think this is a mistake.';
      } else {
        unset($_SESSION['is_admin']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header('Location: account.php' . ($isCheckout ? '?checkout=1' : ''));
        exit;
      }
    } else {
      $error = 'Incorrect email or password.';
    }
  }

  if ($action === 'buy') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in or create an account before buying.';
    } elseif (!$cart) {
      $error = 'Your cart is empty.';
    } else {
      try {
        $database->beginTransaction();
        $total = 0;
        foreach ($cart as $productId => $quantity) {
          if (!isset($products[$productId]) || $quantity < 1 || $quantity > $products[$productId]['stock']) {
            throw new RuntimeException('One item does not have enough stock.');
          }
          $total += $products[$productId]['price'] * $quantity;
        }
        $deliveryError = validateDeliveryFields($address, $location, $zip, $phone);
        if ($deliveryError !== '') {
          throw new RuntimeException($deliveryError);
        }
        if ($name === '') {
          $name = $profile['name'] ?? $_SESSION['user_name'];
        }
        $profileStatement = $database->prepare('UPDATE users SET name = ?, address = ?, location = ?, zip = ?, phone = ?, comments = ? WHERE id = ?');
        $profileStatement->execute([$name, $address, $location, $zip, $phone, $comments, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        $order = $database->prepare('INSERT INTO orders (user_id, total, address, location, zip, phone, payment_method, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $order->execute([$_SESSION['user_id'], $total, $address, $location, $zip, $phone, $paymentMethod, $comments]);
        $orderId = $database->lastInsertId();
        $item = $database->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)');
        $stockStatement = $database->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        foreach ($cart as $productId => $quantity) {
          $product = $products[$productId];
          $item->execute([$orderId, $productId, $product['name'], $quantity, $product['price']]);
          $stockStatement->execute([$quantity, $productId, $quantity]);
          if ($stockStatement->rowCount() !== 1) {
            throw new RuntimeException('One item does not have enough stock.');
          }
        }
        $database->commit();
        $_SESSION['cart'] = [];
        $cart = [];
        $message = 'Order placed successfully! You can track its status from your Purchase History below.';
      } catch (Throwable $exception) {
        if ($database->inTransaction()) {
          $database->rollBack();
        }
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The order could not be placed.';
      }
    }
  }

  if ($action === 'submit_review') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in to leave a review.';
    } else {
      $reviewProductId = $_POST['product_id'] ?? '';
      $reviewOrderId = (int) ($_POST['order_id'] ?? 0);
      $rating = (int) ($_POST['rating'] ?? 0);
      $comment = trim($_POST['comment'] ?? '');

      if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating from 1 to 5 stars.';
      } else {
        $ownsItemStatement = $database->prepare('SELECT COUNT(*) FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE orders.user_id = ? AND orders.id = ? AND order_items.product_id = ?');
        $ownsItemStatement->execute([$_SESSION['user_id'], $reviewOrderId, $reviewProductId]);
        if (!$ownsItemStatement->fetchColumn()) {
          $error = 'You can only review items you\'ve purchased.';
        } else {
          $reviewStatement = $database->prepare('INSERT INTO reviews (user_id, product_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), order_id = VALUES(order_id), created_at = CURRENT_TIMESTAMP');
          $reviewStatement->execute([$_SESSION['user_id'], $reviewProductId, $reviewOrderId, $rating, substr($comment, 0, 1000)]);
          $message = 'Thanks for your feedback!';
        }
      }
    }
  }
}

$cartTotal = 0;
foreach ($cart as $productId => $quantity) {
  if (isset($products[$productId])) {
    $cartTotal += $products[$productId]['price'] * $quantity;
  }
}
$purchaseHistory = [];
$existingReviews = [];
if (!empty($_SESSION['user_id'])) {
  $historyStatement = $database->prepare('SELECT orders.id, orders.total, orders.address, orders.location, orders.zip, orders.phone, orders.payment_method, orders.comments, orders.delivery_status, orders.estimated_delivery, orders.created_at, order_items.product_id, order_items.product_name, order_items.quantity FROM orders JOIN order_items ON order_items.order_id = orders.id WHERE orders.user_id = ? ORDER BY orders.id DESC');
  $historyStatement->execute([$_SESSION['user_id']]);
  while ($historyItem = $historyStatement->fetch(PDO::FETCH_ASSOC)) {
    $purchaseHistory[$historyItem['id']]['total'] = $historyItem['total'];
    $purchaseHistory[$historyItem['id']]['address'] = $historyItem['address'];
    $purchaseHistory[$historyItem['id']]['location'] = $historyItem['location'];
    $purchaseHistory[$historyItem['id']]['zip'] = $historyItem['zip'];
    $purchaseHistory[$historyItem['id']]['phone'] = $historyItem['phone'];
    $purchaseHistory[$historyItem['id']]['payment_method'] = $historyItem['payment_method'];
    $purchaseHistory[$historyItem['id']]['comments'] = $historyItem['comments'];
    $purchaseHistory[$historyItem['id']]['delivery_status'] = $historyItem['delivery_status'];
    $purchaseHistory[$historyItem['id']]['estimated_delivery'] = $historyItem['estimated_delivery'];
    $purchaseHistory[$historyItem['id']]['created_at'] = $historyItem['created_at'];
    $purchaseHistory[$historyItem['id']]['items'][] = [
      'product_id' => $historyItem['product_id'],
      'product_name' => $historyItem['product_name'],
      'quantity' => $historyItem['quantity'],
    ];
  }

  $reviewStatement = $database->prepare('SELECT product_id, rating, comment FROM reviews WHERE user_id = ?');
  $reviewStatement->execute([$_SESSION['user_id']]);
  foreach ($reviewStatement->fetchAll(PDO::FETCH_ASSOC) as $review) {
    $existingReviews[$review['product_id']] = $review;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account — Masalihit Luxe</title>
<link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
</head>
<body>
<main class="account-page<?php echo $isCheckout ? ' checkout-page' : ''; ?>">
  <div class="account-header">
    <div><div class="eyebrow">Masalihit Luxe</div><h1 class="display"><?php echo $isCheckout ? 'Checkout' : 'Your Account'; ?></h1></div>
    <a class="nav-action" href="index.php">Back to store</a>
  </div>
  <?php if ($message): ?><div class="store-message order-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="form-error account-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if (!empty($_SESSION['user_id'])): ?>
    <?php if (!$isCheckout): ?><section class="profile-card">
      <div class="eyebrow">Profile Details</div>
      <h2><?php echo htmlspecialchars($profile['name'] ?? $_SESSION['user_name']); ?></h2>
      <div class="profile-details">
        <span><?php echo htmlspecialchars($profile['email'] ?? ''); ?></span>
        <span><?php echo htmlspecialchars($address); ?>, <?php echo htmlspecialchars($location); ?> <?php echo htmlspecialchars($zip); ?></span>
        <span>Phone: <?php echo $phone !== '' ? htmlspecialchars($phone) : '—'; ?></span>
      </div>
      <details class="edit-profile-toggle" <?php echo (($action ?? '') === 'update_profile' && $error !== '') ? 'open' : ''; ?>>
        <summary>Edit Details</summary>
        <form class="profile-edit-form" method="post">
        <input type="hidden" name="action" value="update_profile">
        <label>Name<input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required autocomplete="name"></label>
        <label>Address<input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" required autocomplete="street-address"></label>
        <label>City / Location<input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" required autocomplete="address-level2"></label>
        <label>ZIP Code<input type="text" name="zip" value="<?php echo htmlspecialchars($zip); ?>" required autocomplete="postal-code"></label>
        <label>Phone Number<input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" title="11 digits starting with 09, e.g. 09171234567" required autocomplete="tel"></label>
        <button class="cart-button" type="submit">Save Changes</button>
        </form>
      </details>
    </section><?php endif; ?>
    <?php if ($isCheckout): ?><section class="checkout-card">
      <div class="checkout-delivery-panel">
        <div class="eyebrow">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
        <h2>Delivery Details</h2>
        <div class="delivery-details">
          <label>Recipient Name<input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? $_SESSION['user_name']); ?>" form="buy-form" required autocomplete="name"></label>
          <label>Address<input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" form="buy-form" required autocomplete="street-address"></label>
          <label>Location<input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" form="buy-form" required autocomplete="address-level2"></label>
          <label>ZIP Code<input type="text" name="zip" value="<?php echo htmlspecialchars($zip); ?>" form="buy-form" required autocomplete="postal-code"></label>
          <label>Phone Number<input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" form="buy-form" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" title="11 digits starting with 09, e.g. 09171234567" required autocomplete="tel"></label>
          <label>Delivery Comments<textarea name="comments" form="buy-form" rows="3" placeholder="Optional instructions"><?php echo htmlspecialchars($comments); ?></textarea></label>
        </div>
        <div class="payment-method-group">
          <span class="payment-method-label">Payment Method</span>
          <div class="payment-options">
            <label class="payment-option">
              <input type="radio" name="payment_method" value="cod" form="buy-form" <?php echo $paymentMethod !== 'ewallet' ? 'checked' : ''; ?>>
              <span class="payment-option-title">Cash on Delivery</span>
              <span class="payment-option-sub">Pay in cash when your order arrives</span>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment_method" value="ewallet" form="buy-form" <?php echo $paymentMethod === 'ewallet' ? 'checked' : ''; ?>>
              <span class="payment-option-title">E-Wallet</span>
              <span class="payment-option-sub">GCash, Maya, or another e-wallet</span>
            </label>
          </div>
        </div>
      </div>
      <div class="checkout-summary-panel">
        <div class="summary-heading">Order Summary</div>
        <?php if (!$cart): ?>
          <p class="empty-cart">Your cart is empty. Add something from the collection to get started.</p>
          <a class="buy-now-btn checkout-store-link" href="index.php#features"><span class="buy-now-icon" aria-hidden="true">⚡</span>Browse collection</a>
        <?php else: ?>
          <div class="checkout-items">
            <?php foreach ($cart as $productId => $quantity): if (!isset($products[$productId])) continue; ?><div><span><?php echo htmlspecialchars($products[$productId]['name']); ?> × <?php echo (int) $quantity; ?></span><strong>₱<?php echo number_format($products[$productId]['price'] * $quantity); ?></strong></div><?php endforeach; ?>
          </div>
          <div class="checkout-total">Total: ₱<?php echo number_format($cartTotal); ?></div>
          <form method="post" id="buy-form"><input type="hidden" name="action" value="buy"><button class="buy-now-btn checkout-submit" type="submit"><span class="buy-now-icon" aria-hidden="true">⚡</span>Place Order</button></form>
          <p class="checkout-secure-note">🔒 Your order details stay private and are only used for delivery.</p>
        <?php endif; ?>
      </div>
    </section><?php endif; ?>
    <?php if (!$isCheckout): ?><section class="history-card">
      <div class="eyebrow">Profile</div>
      <h2>Purchase History</h2>
      <?php if (!$purchaseHistory): ?>
        <p class="empty-cart">No purchases yet.</p>
      <?php else: ?>
        <div class="history-list">
          <?php foreach ($purchaseHistory as $orderId => $order): ?>
            <?php
              $historyStatus = $order['delivery_status'] ?: 'processing';
              $historyStatusLabels = ['processing' => 'Processing', 'delivered' => 'Delivered', 'failed' => 'Failed'];
              $historyOrderedAt = $order['created_at'] ? date('M j, Y g:i A', strtotime($order['created_at'])) : '—';
              $historyEstimatedDelivery = $order['estimated_delivery'] ? date('M j, Y g:i A', strtotime($order['estimated_delivery'])) : null;
              $paymentMethodLabels = ['cod' => 'Cash on Delivery', 'ewallet' => 'E-Wallet'];
              $historyPaymentMethod = $paymentMethodLabels[$order['payment_method'] ?? 'cod'] ?? 'Cash on Delivery';
            ?>
            <div class="history-row">
              <div>
                <span class="status-badge status-<?php echo htmlspecialchars($historyStatus); ?>"><?php echo htmlspecialchars($historyStatusLabels[$historyStatus] ?? ucfirst($historyStatus)); ?></span>
                <div class="history-items">
                  <?php foreach ($order['items'] as $item): ?>
                    <?php
                      $reviewExisting = $existingReviews[$item['product_id']] ?? null;
                      $starGroup = 'rating-' . $orderId . '-' . preg_replace('/[^A-Za-z0-9]/', '', $item['product_id']);
                    ?>
                    <div class="review-widget">
                      <div class="review-widget-head">
                        <span><?php echo htmlspecialchars($item['product_name']); ?> × <?php echo (int) $item['quantity']; ?></span>
                      </div>
                      <form method="post" class="review-form">
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                        <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                        <div class="star-rating">
                          <?php for ($starValue = 5; $starValue >= 1; $starValue--): ?>
                            <input type="radio" id="<?php echo $starGroup . '-' . $starValue; ?>" name="rating" value="<?php echo $starValue; ?>" <?php echo ($reviewExisting && (int) $reviewExisting['rating'] === $starValue) ? 'checked' : ''; ?> required>
                            <label for="<?php echo $starGroup . '-' . $starValue; ?>" title="<?php echo $starValue; ?> star<?php echo $starValue > 1 ? 's' : ''; ?>">★</label>
                          <?php endfor; ?>
                        </div>
                        <input type="text" name="comment" maxlength="500" placeholder="Optional comment" value="<?php echo htmlspecialchars($reviewExisting['comment'] ?? ''); ?>">
                        <button class="dash-btn-secondary review-submit-btn" type="submit"><?php echo $reviewExisting ? 'Update Review' : 'Submit Review'; ?></button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                </div>
                <span>Deliver to: <?php echo htmlspecialchars($order['address'] . ', ' . $order['location'] . ' ' . $order['zip']); ?></span>
                <?php if (!empty($order['phone'])): ?><span>Phone: <?php echo htmlspecialchars($order['phone']); ?></span><?php endif; ?>
                <span>Payment: <?php echo htmlspecialchars($historyPaymentMethod); ?></span>
                <?php if (!empty($order['comments'])): ?><span>Note: <?php echo htmlspecialchars($order['comments']); ?></span><?php endif; ?>
                <span class="order-time">Ordered: <?php echo htmlspecialchars($historyOrderedAt); ?></span>
                <?php if ($historyEstimatedDelivery): ?>
                  <span class="order-time order-time-eta"><?php echo $historyStatus === 'delivered' ? 'Delivered' : 'Expected'; ?>: <?php echo htmlspecialchars($historyEstimatedDelivery); ?></span>
                <?php endif; ?>
              </div>
              <strong>₱<?php echo number_format($order['total']); ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section><?php endif; ?>
    <a class="back-link" href="logout.php">Log out</a>
  <?php else: ?>
    <div class="account-forms">
      <form class="auth-card" method="post">
        <h2>Log In to continue</h2>
        <input type="hidden" name="action" value="login">
        <label>Email<input type="email" name="email" required autocomplete="email"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="cart-button" type="submit">Log In</button>
      </form>
      <details class="register-option" <?php echo $showRegister ? 'open' : ''; ?>>
        <summary>No account? Create one</summary>
        <form class="auth-card" method="post">
          <h2>Create Account</h2>
          <input type="hidden" name="action" value="register">
          <label>Name<input type="text" name="name" required autocomplete="name"></label>
          <label>Email<input type="email" name="email" required autocomplete="email"></label>
          <label>Password<input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
          <label>Delivery Address<input type="text" name="address" required autocomplete="street-address"></label>
          <label>City / Location<input type="text" name="location" required autocomplete="address-level2"></label>
          <label>ZIP Code<input type="text" name="zip" required autocomplete="postal-code"></label>
          <label>Phone Number<input type="tel" name="phone" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" title="11 digits starting with 09, e.g. 09171234567" required autocomplete="tel"></label>
          <p class="form-note">You can add delivery comments later, at checkout.</p>
          <button class="cart-button" type="submit">Create Account</button>
        </form>
      </details>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
<?php
session_start();
require_once __DIR__ . '/database.php';
$year = date("Y");

$database = getDatabase();
$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}
$cart = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $productId = $_POST['product_id'] ?? '';

  if ($action === 'change_cart_quantity' && isset($cart[$productId], $products[$productId])) {
    $change = (int) ($_POST['change'] ?? 0);
    $newQuantity = $cart[$productId] + $change;
    if ($newQuantity < 1) {
      unset($cart[$productId]);
    } else {
      $cart[$productId] = min($newQuantity, (int) $products[$productId]['stock']);
    }
    $_SESSION['cart'] = $cart;
    header('Location: contact.php');
    exit;
  }

  if ($action === 'remove_from_cart' && isset($cart[$productId])) {
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
    header('Location: contact.php');
    exit;
  }
}

$cartCount = array_sum($cart);
$isLoggedIn = !empty($_SESSION['is_admin']) || !empty($_SESSION['user_id']);
$buyUrl = $isLoggedIn ? 'account.php?checkout=1' : 'login.php?checkout=1';
$cartTotal = 0;
foreach ($cart as $productId => $quantity) {
  if (isset($products[$productId])) {
    $cartTotal += $products[$productId]['price'] * $quantity;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us — Masalihit Luxe</title>
<link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
<script src="script.js?v=<?php echo file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : time(); ?>" defer></script>
</head>
<body>

<header>
  <nav class="wrap">
    <div class="logo">
      <span class="logo-mark"><img src="Pictures/logoh.png" alt="Masalihit Luxe logo"></span>
      Masalihit Luxe
    </div>
    <ul class="nav-links">
      <li><span onclick="window.location.href='index.php'">Home</span></li>
      <li><span onclick="window.location.href='index.php#about'">About Me</span></li>
      <li><span onclick="window.location.href='index.php#features'">Features</span></li>
      <li><span class="active">Contact Us</span></li>
    </ul>
    <div class="store-actions">
      <?php if ($isLoggedIn): ?>
        <a class="nav-action" href="account.php">Profile</a>
        <?php if (!empty($_SESSION['is_admin'])): ?><a class="nav-action" href="admin.php">Admin Panel</a><?php endif; ?>
        <a class="nav-action" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="nav-action" href="login.php">Login</a>
      <?php endif; ?>
      <button class="nav-action cart-toggle" id="cart-toggle-btn" type="button" onclick="toggleCart()">Cart <span class="cart-count" id="cart-count"<?php echo $cartCount > 0 ? '' : ' style="display:none;"'; ?>><?php echo $cartCount; ?></span></button>
    </div>
  </nav>
</header>

<aside class="cart-drawer" id="cart-panel" aria-hidden="true">
  <div class="cart-drawer-header">
    <h2>Shopping Cart</h2>
    <button class="cart-close" type="button" onclick="toggleCart()" aria-label="Close cart">×</button>
  </div>
  <div id="cart-content">
  <?php if (!$cart): ?>
    <p class="empty-cart">Your cart is empty.</p>
  <?php else: ?>
    <div class="cart-list">
      <?php foreach ($cart as $productId => $quantity): if (!isset($products[$productId])) continue; $product = $products[$productId]; $lineTotal = $product['price'] * $quantity; ?>
        <div class="cart-row">
          <span><?php echo htmlspecialchars($product['name']); ?></span>
          <strong>₱<?php echo number_format($lineTotal); ?></strong>
          <form class="quantity-controls" method="post" data-cart-form><input type="hidden" name="action" value="change_cart_quantity"><input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>"><button type="submit" name="change" value="-1" aria-label="Decrease quantity">−</button><span><?php echo (int) $quantity; ?></span><button type="submit" name="change" value="1" aria-label="Increase quantity" <?php echo $quantity >= $product['stock'] ? 'disabled' : ''; ?>>+</button></form>
          <form method="post" data-cart-form><input type="hidden" name="action" value="remove_from_cart"><input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>"><button class="remove-button" type="submit">Remove</button></form>
        </div>
      <?php endforeach; ?>
      <div class="cart-total">Total: ₱<?php echo number_format($cartTotal); ?></div>
      <a class="buy-now-btn" href="<?php echo htmlspecialchars($buyUrl); ?>"><span class="buy-now-icon" aria-hidden="true">⚡</span>Buy Now</a>
    </div>
  <?php endif; ?>
  </div>
</aside>

<main id="top">
  <section class="contact-page reveal">
    <div class="wrap contact-page-grid">
      <div class="contact-page-info">
        <div class="eyebrow">Get In Touch</div>
        <h1 class="display">Contact Us</h1>
        <p class="contact-intro">Have a question about an order, sizing, or the collection? Reach out — we usually reply within a day.</p>
        <div class="contact-list">
          <div class="contact-row">
            <span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M4 6l8 7 8-7" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
            <div><strong>Email</strong><span>MasalihitLuxe@masalihitluxe.com</span></div>
          </div>
          <div class="contact-row">
            <span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.9 21 3 13.1 3 3.5c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
            <div><strong>Phone</strong><span>+63 935 469 4362</span></div>
          </div>
          <div class="contact-row">
            <span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4" stroke="currentColor" stroke-width="1.4"/></svg></span>
            <div><strong>Location</strong><span>Dumaguete, Philippines</span></div>
          </div>
        </div>
      </div>

      <div class="contact-page-chat">
        <div class="chat-widget">
          <div class="chat-widget-head">Live Chat<span class="chat-widget-status">We usually reply fast</span></div>
          <div class="chat-messages" id="chat-messages"><p class="chat-empty">Say hello — we're happy to help.</p></div>
          <form class="chat-form" id="chat-form">
            <input type="text" id="chat-name-input" placeholder="Your name" autocomplete="name" <?php echo $isLoggedIn ? 'hidden' : ''; ?>>
            <div class="chat-form-row">
              <input type="text" id="chat-message-input" placeholder="Type a message…" autocomplete="off" required>
              <button type="submit" aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 12l16-7-6.5 16-2.8-6.7L4 12z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="logo">
          <span class="logo-mark"><img src="Pictures/logoh.png" alt="Masalihit Luxe logo"></span>
          Masalihit Luxe
        </div>
        <p>Next-generation design meets everyday utility. High-performance shoes, shirts, and rugged drinkware engineered for your active lifestyle.</p>
        <div class="footer-social">
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 8h-2c-.55 0-1 .45-1 1v2h3l-.4 3H12v7h-3v-7H7v-3h2V8.5C9 6.57 10.57 5 12.5 5H15v3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 5.5c-.7.35-1.46.58-2.25.69a3.9 3.9 0 0 0 1.71-2.16c-.76.46-1.6.8-2.5.98A3.86 3.86 0 0 0 15.1 4c-2.15 0-3.9 1.78-3.9 3.98 0 .31.03.62.1.9-3.24-.17-6.11-1.75-8.03-4.17-.34.6-.53 1.29-.53 2.03 0 1.38.69 2.6 1.73 3.32-.64-.02-1.24-.2-1.77-.5v.05c0 1.93 1.34 3.54 3.13 3.9-.33.09-.67.14-1.03.14-.25 0-.5-.02-.73-.07.5 1.58 1.94 2.73 3.65 2.76A7.72 7.72 0 0 1 2 18.4a10.85 10.85 0 0 0 5.94 1.77c7.13 0 11.03-6.03 11.03-11.26l-.01-.51c.76-.56 1.42-1.26 1.94-2.06-.7.32-1.44.53-2.2.63Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor"/></svg></span>
        </div>
      </div>
      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><span onclick="window.location.href='index.php'">Home</span></li>
          <li><span onclick="window.location.href='index.php#about'">About</span></li>
          <li><span onclick="window.location.href='index.php#features'">Features</span></li>
          <li><span onclick="window.location.href='contact.php'">Contact</span></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Collection</h4>
        <ul>
          <li><span onclick="window.location.href='index.php#features'">Shoes</span></li>
          <li><span onclick="window.location.href='index.php#features'">Shirt</span></li>
          <li><span onclick="window.location.href='index.php#features'">Tumbler</span></li>
          <li><span onclick="window.location.href='index.php#features'">Cap</span></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <ul>
          <li><span onclick="window.location.href='contact.php'">FAQ</span></li>
          <li><span onclick="window.location.href='contact.php'">Shipping</span></li>
          <li><span onclick="window.location.href='contact.php'">Returns</span></li>
          <li><span onclick="window.location.href='contact.php'">Size Guide</span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?php echo $year; ?> Masalihit Luxe. All rights reserved.</span>
      <span>Gear Shift Mode</span>
    </div>
  </div>
</footer>

</body>
</html>
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
// Drop any cart entries for products that no longer exist (e.g. deleted
// from the Admin Panel) so the cart count/total always match what the
// drawer actually displays instead of counting ghost items.
$cart = array_intersect_key($cart, $products);
$_SESSION['cart'] = $cart;

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
    <?php include __DIR__ . '/includes/store-actions.php'; ?>
  </nav>
</header>

<?php include __DIR__ . '/includes/cart-drawer.php'; ?>

<main id="top">
  <section class="contact-page reveal">
    <div class="wrap contact-page-single">
      <div class="contact-page-info">
        <div class="eyebrow">Get In Touch</div>
        <h1 class="display">Contact Us</h1>
        <p class="contact-intro">Have a question about an order, sizing, or the collection? Reach out we usually reply within a day.</p>
        <?php if ($isLoggedIn): ?>
          <a class="contact-profile-btn" href="account.php">Message us from your profile →</a>
        <?php else: ?>
          <a class="contact-profile-btn" href="login.php?next=<?php echo urlencode('account.php'); ?>">Log in to message us →</a>
        <?php endif; ?>
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
            <div><strong>Location</strong><span>Robinsons Place, Dumaguete, Philippines</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php $onHomePage = false; include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
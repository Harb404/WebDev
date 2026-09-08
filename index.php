<?php
session_start();
require_once __DIR__ . '/database.php';
// Stock and cart state change often (every purchase, every add-to-cart), so
// never let the browser serve a stale cached copy of this page — e.g. when
// someone hits the back button right after buying the last unit of an item.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$year = date("Y");

$images = [
  "hero_shoe" => "Pictures/Sapatos.png",
  "about"     => "Pictures/About.png",
  "tumbler"   => "Pictures/Bottle.png",
  "air1"      => "Pictures/Sapatos.png",
  "cap"       => "Pictures/CAP.png",
  "shirt"     => "Pictures/Shirt.png",
  "fuel"      => "Pictures/person drinking.png",
  
];

$database = getDatabase();
$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}
$productRatings = [];
foreach ($database->query('SELECT product_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id')->fetchAll(PDO::FETCH_ASSOC) as $ratingRow) {
  $productRatings[$ratingRow['product_id']] = $ratingRow;
}
$cart = $_SESSION['cart'] ?? [];
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $productId = $_POST['product_id'] ?? '';

  if ($action === 'add_to_cart' && isset($products[$productId]) && $products[$productId]['stock'] > 0) {
    if (empty($_SESSION['user_id']) && empty($_SESSION['is_admin'])) {
      header('Location: login.php?next=' . urlencode('index.php#features'));
      exit;
    }
    $cart[$productId] = ($cart[$productId] ?? 0) + 1;
    $cart[$productId] = min($cart[$productId], $products[$productId]['stock']);
    $_SESSION['cart'] = $cart;
    $_SESSION['flash_message'] = $products[$productId]['name'] . ' added to cart.';
    header('Location: index.php#features');
    exit;
  }

  if ($action === 'remove_from_cart' && isset($cart[$productId])) {
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
    $_SESSION['flash_message'] = 'Item removed from cart.';
    header('Location: index.php');
    exit;
  }

  if ($action === 'change_cart_quantity' && isset($cart[$productId], $products[$productId])) {
    $change = (int) ($_POST['change'] ?? 0);
    $newQuantity = $cart[$productId] + $change;
    if ($newQuantity < 1) {
      unset($cart[$productId]);
    } else {
      $cart[$productId] = min($newQuantity, (int) $products[$productId]['stock']);
    }
    $_SESSION['cart'] = $cart;
    header('Location: index.php?cart=1');
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
<title>Masalihit Luxe — Build For Those Who Move First</title>
<link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
<script>window.IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;</script>
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
      <li><span data-target="top" onclick="goTo('top')">Home</span></li>
      <li><span data-target="about" onclick="goTo('about')">About Me</span></li>
      <li><span data-target="features" onclick="goTo('features')">Featured</span></li>
      <li><span data-target="contact" onclick="window.location.href='contact.php'">Contact Us</span></li>
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
      <a class="buy-now-btn" href="<?php echo $buyUrl; ?>"><span class="buy-now-icon" aria-hidden="true">⚡</span>Buy Now</a>
    </div>
  <?php endif; ?>
  </div>
</aside>

<?php if (!$isLoggedIn): ?>
<div class="auth-modal-backdrop" id="auth-modal-backdrop" onclick="closeAuthModal()"></div>
<div class="auth-modal" id="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">
  <button class="cart-close auth-modal-close" type="button" onclick="closeAuthModal()" aria-label="Close">×</button>
  <h2 id="auth-modal-title">Log in to keep shopping</h2>
  <p class="contact-intro">Create a free account or log in to add items to your cart and check out.</p>
  <div class="auth-modal-actions">
    <a class="cart-button" id="auth-modal-login" href="login.php?next=<?php echo urlencode('index.php#features'); ?>">Log In</a>
    <a class="cart-button auth-modal-secondary" id="auth-modal-register" href="register.php?next=<?php echo urlencode('index.php#features'); ?>">Sign Up</a>
  </div>
</div>
<?php endif; ?>

<main id="top">

  <!-- HERO -->
  <section class="hero reveal">
    <div class="wrap hero-grid">
      <div>
        <div class="eyebrow">Est. 2026 · Dumaguete, Philippines</div>
        <h1 class="display" style="margin-top:14px;">Build for<br>those who<br><span class="accent">move first.</span></h1>
        <p class="lede">Footwear, shirts, tumblers and caps engineered for the pace of everyday movement — not just the podium. No filler, no fluff. Just gear that keeps up.</p>
        <div class="hero-cta">
          <span class="link-solid" onclick="goTo('features')">Shop the Drop</span>
          <span class="link-outline" onclick="goTo('about')">Our Story</span>
        </div>
      </div>

      <div class="hero-visual">
        <img class="shoe-img" src="<?php echo $images['hero_shoe']; ?>" alt="M-Luxe Shoe Air1">
        <div class="price-chip">
          M-Luxe Shoe Air1
          <strong>₱2,450</strong>
        </div>
      </div>
    </div>
  </section>

  <!-- ABOUT -->
  <section class="about reveal" id="about">
    <div class="wrap about-grid">
      <div class="about-visual">
        <img src="<?php echo $images['about']; ?>" alt="Masalihit Luxe gear">
      </div>
      <div>
        <div class="eyebrow">Our Story</div>
        <h2 class="display" style="font-size:30px; margin-top:12px; line-height:1.15;">Style and function, never mutually exclusive</h2>
        <p style="margin-top:18px;">At Masalihit Luxe, we believe that style and function should never be mutually exclusive. Founded with a relentless drive for innovation, we create high-performance gear for those who move differently — people who demand excellence whether they are conquering the urban landscape, training hard, or navigating their daily routine.</p>
      </div>
    </div>
  </section>

  <!-- FEATURED COLLECTION -->
  <section id="features" class="reveal">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow">// M-Luxe Gear - LIMITED EDITION</div>
        <h2 class="display" style="font-size:30px;">Featured Collection</h2>
      </div>

      <div class="products-carousel">
        <button class="products-arrow products-arrow-left" type="button" onclick="scrollProducts(-1)" aria-label="Scroll products left">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="products" id="products-row">
          <?php foreach ($products as $productId => $product):
            $inCartQuantity = (int) ($cart[$productId] ?? 0);
            $atMaxInCart = $product['stock'] > 0 && $inCartQuantity >= $product['stock'];
          ?>
          <div class="product-card" data-product-id="<?php echo htmlspecialchars($productId); ?>" data-stock="<?php echo (int) $product['stock']; ?>">
            <div class="product-thumb">
              <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <?php if (isset($productRatings[$productId])): ?>
              <div class="product-rating">
                <span class="product-rating-stars" style="--rating: <?php echo htmlspecialchars($productRatings[$productId]['avg_rating']); ?>;" aria-hidden="true">★★★★★</span>
                <span class="product-rating-count"><?php echo htmlspecialchars($productRatings[$productId]['avg_rating']); ?> (<?php echo (int) $productRatings[$productId]['review_count']; ?>)</span>
              </div>
            <?php endif; ?>
            <div class="price">₱<?php echo number_format($product['price']); ?></div>
            <div class="stock <?php echo $product['stock'] < 1 ? 'out-of-stock' : ''; ?>" data-stock-label><?php echo $product['stock'] < 1 ? 'No stock' : 'Stock: ' . (int) $product['stock']; ?></div>
            <form method="post" data-cart-form>
              <input type="hidden" name="action" value="add_to_cart">
              <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>">
              <button class="cart-button <?php echo ($product['stock'] < 1 || $atMaxInCart) ? 'out-of-stock-button' : ''; ?>" type="submit" <?php echo ($product['stock'] < 1 || $atMaxInCart) ? 'disabled' : ''; ?>><?php echo $product['stock'] < 1 ? 'No stock' : ($atMaxInCart ? 'Max in Cart' : 'Add to Cart'); ?></button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="products-arrow products-arrow-right" type="button" onclick="scrollProducts(1)" aria-label="Scroll products right">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>
      <?php if ($message): ?><div class="store-message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    </div>
  </section>

  <!-- TESTIMONIALS -->
  <section class="testimonials reveal">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow" style="text-align:center; display:block;">Feedback</div>
        <h2 class="display" style="font-size:28px;">Style your ideal</h2>
      </div>

      <div class="t-grid">
        <div class="t-card">
          <div class="t-top"><img class="t-avatar" src="Pictures/corn.jpg" alt="Richard Cornelius Timosa II"><div><div class="t-name">Richard Cornelius Timosa II</div><div class="t-loc">Dipolog City</div></div></div>
          <div class="stars">★★★★★</div>
          <div class="t-quote">"The quality is amazing."</div>
        </div>
        <div class="t-card">
          <div class="t-top"><img class="t-avatar" src="Pictures/ciara.jpg" alt="Ciara Amber Saycon"><div><div class="t-name">Ciara Amber Saycon</div><div class="t-loc">Pamplona</div></div></div>
          <div class="stars">★★★★★</div>
          <div class="t-quote">"Wow, I love the style, it really fits the groove."</div>
        </div>
        <div class="t-card">
          <div class="t-top"><img class="t-avatar" src="Pictures/fhet.jpg" alt="Hecate18"><div><div class="t-name">Hecate18</div><div class="t-loc">Tanjay City</div></div></div>
          <div class="stars">★★★★★</div>
          <div class="t-quote">"Their customer service quality is excellent!"</div>
        </div>
        <div class="t-card">
          <div class="t-top"><img class="t-avatar" src="Pictures/harveyf.jpg" alt="Harvey S. Masalihit"><div><div class="t-name">Harvey S. Masalihit</div><div class="t-loc">Bais City</div></div></div>
          <div class="stars">★★★★★</div>
          <div class="t-quote">"The design was wonderful."</div>
        </div>
      </div>
    </div>
  </section>

  <!-- FUEL / SPEC PANEL -->
  <section class="fuel reveal">
    <div class="wrap">
      <div>
        <div class="eyebrow">Discipline</div>
        <h2 class="display" style="margin-top:12px;">Fuel your potential.<br>Wear your strength.</h2>
        <p>Every stitch and seam is built around one idea: gear should disappear when you're moving well. Feel the lightness with every step, and the quiet confidence of gear designed for peak endurance — built to keep pace, not slow you down.</p>
      </div>
      <div class="fuel-visual">
        <img src="<?php echo $images['fuel']; ?>" alt="Person drinking from an M-Luxe tumbler">
        
      </div>
    </div>
  </section>

  <!-- BANNER -->
  <section class="banner reveal" id="contact">
    <div class="wrap">
      <span class="eyebrow">Move First</span>
      <h2 class="display">Peak performance.<br>Zero excuses.</h2>
      <span class="link-solid" onclick="goTo('features')">View Collection</span>
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
          <li><span onclick="goTo('top')">Home</span></li>
          <li><span onclick="goTo('about')">About</span></li>
          <li><span onclick="goTo('features')">Featured</span></li>
          <li><span onclick="window.location.href='contact.php'">Contact</span></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Collection</h4>
        <ul>
          <li><span onclick="goTo('features')">Shoes</span></li>
          <li><span onclick="goTo('features')">Shirt</span></li>
          <li><span onclick="goTo('features')">Tumbler</span></li>
          <li><span onclick="goTo('features')">Cap</span></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <ul>
          <li><span onclick="goTo('contact')">FAQ</span></li>
          <li><span onclick="goTo('contact')">Shipping</span></li>
          <li><span onclick="goTo('contact')">Returns</span></li>
          <li><span onclick="goTo('contact')">Size Guide</span></li>
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
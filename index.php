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
require_once __DIR__ . '/includes/store-data.php';   // loads $products, $productRatings, $cart
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

require_once __DIR__ . '/actions/cart-actions.php';  // handles add/remove/change-quantity posts

$featuredReviews = $database->query("
  SELECT reviews.rating, reviews.comment, users.name AS user_name, users.location AS user_location
  FROM reviews
  JOIN users ON users.id = reviews.user_id
  WHERE reviews.is_featured = 1
  ORDER BY reviews.created_at DESC
  LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

// The 4 sample testimonials below are permanent showcase cards — they always
// display alongside whatever real reviews get featured from the admin panel,
// rather than only appearing when there are zero real featured reviews.
$sampleTestimonials = [
  ['name' => 'Richard Cornelius Timosa II', 'location' => 'Dipolog City', 'rating' => 5, 'comment' => 'The quality is amazing.', 'avatar' => 'Pictures/corn.jpg'],
  ['name' => 'Ciara Amber Saycon', 'location' => 'Pamplona', 'rating' => 5, 'comment' => 'Wow, I love the style, it really fits the groove.', 'avatar' => 'Pictures/ciara.jpg'],
  ['name' => 'Hecate18', 'location' => 'Tanjay City', 'rating' => 5, 'comment' => 'Their customer service quality is excellent!', 'avatar' => 'Pictures/fhet.jpg'],
  ['name' => 'Harvey S. Masalihit', 'location' => 'Bais City', 'rating' => 5, 'comment' => 'The design was wonderful.', 'avatar' => 'Pictures/harveyf.jpg'],
];

$displayTestimonials = [];
foreach ($featuredReviews as $review) {
  $displayTestimonials[] = [
    'name'     => $review['user_name'],
    'location' => $review['user_location'],
    'rating'   => (int) $review['rating'],
    'comment'  => $review['comment'],
    'avatar'   => null, // real reviewers get an initial avatar, not a photo
  ];
}
foreach ($sampleTestimonials as $sample) {
  $displayTestimonials[] = $sample;
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
    <?php include __DIR__ . '/includes/store-actions.php'; ?>
  </nav>
</header>

<?php include __DIR__ . '/includes/cart-drawer.php'; ?>

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

      <div class="products-carousel testimonials-carousel">
        <button class="products-arrow products-arrow-left" type="button" onclick="scrollTestimonials(-1)" aria-label="Scroll testimonials left">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="t-grid" id="t-row">
        <?php foreach ($displayTestimonials as $review):
          $initial = strtoupper(substr(trim($review['name']), 0, 1)) ?: '?';
        ?>
        <div class="t-card">
          <div class="t-top">
            <?php if (!empty($review['avatar'])): ?>
              <img class="t-avatar" src="<?php echo htmlspecialchars($review['avatar']); ?>" alt="<?php echo htmlspecialchars($review['name']); ?>">
            <?php else: ?>
              <span class="t-avatar t-avatar-initial" aria-hidden="true"><?php echo htmlspecialchars($initial); ?></span>
            <?php endif; ?>
            <div>
              <div class="t-name"><?php echo htmlspecialchars($review['name']); ?></div>
              <?php if (!empty($review['location'])): ?><div class="t-loc"><?php echo htmlspecialchars($review['location']); ?></div><?php endif; ?>
            </div>
          </div>
          <div class="stars"><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></div>
          <?php if (trim((string) $review['comment']) !== ''): ?><div class="t-quote">"<?php echo htmlspecialchars($review['comment']); ?>"</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <button class="products-arrow products-arrow-right" type="button" onclick="scrollTestimonials(1)" aria-label="Scroll testimonials right">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
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

<?php $onHomePage = true; include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
<?php
session_start();
require_once __DIR__ . '/database.php';
header('Content-Type: application/json');

$database = getDatabase();
$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}

$cart = $_SESSION['cart'] ?? [];
$action = $_POST['action'] ?? '';
$productId = $_POST['product_id'] ?? '';
$message = '';
$addedName = '';
$isLoggedIn = !empty($_SESSION['is_admin']) || !empty($_SESSION['user_id']);

if ($action === 'add_to_cart' && !$isLoggedIn) {
  http_response_code(401);
  echo json_encode(['success' => false, 'requiresLogin' => true, 'message' => 'Please log in to add items to your cart.']);
  exit;
}

if ($action === 'add_to_cart' && isset($products[$productId]) && $products[$productId]['stock'] > 0) {
  $currentQuantity = $cart[$productId] ?? 0;
  $stock = (int) $products[$productId]['stock'];
  if ($currentQuantity >= $stock) {
    echo json_encode([
      'success' => false,
      'atMaxStock' => true,
      'productId' => $productId,
      'productStock' => $stock,
      'cartQuantity' => $currentQuantity,
      'message' => 'You already have all ' . $stock . ' in stock in your cart.',
    ]);
    exit;
  }
  $cart[$productId] = $currentQuantity + 1;
  $_SESSION['cart'] = $cart;
  $addedName = $products[$productId]['name'];
  $message = $addedName . ' added to cart.';
} elseif ($action === 'remove_from_cart' && isset($cart[$productId])) {
  unset($cart[$productId]);
  $_SESSION['cart'] = $cart;
  $message = 'Item removed from cart.';
} elseif ($action === 'change_cart_quantity' && isset($cart[$productId], $products[$productId])) {
  $change = (int) ($_POST['change'] ?? 0);
  $newQuantity = $cart[$productId] + $change;
  if ($newQuantity < 1) {
    unset($cart[$productId]);
  } else {
    $cart[$productId] = min($newQuantity, (int) $products[$productId]['stock']);
  }
  $_SESSION['cart'] = $cart;
} else {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid request.']);
  exit;
}

$cartCount = array_sum($cart);
$cartTotal = 0;
foreach ($cart as $pid => $quantity) {
  if (isset($products[$pid])) {
    $cartTotal += $products[$pid]['price'] * $quantity;
  }
}

$buyUrl = $isLoggedIn ? 'account.php?checkout=1' : 'login.php?checkout=1';

ob_start();
if (!$cart): ?>
  <p class="empty-cart">Your cart is empty.</p>
<?php else: ?>
  <div class="cart-list">
    <?php foreach ($cart as $pid => $quantity): if (!isset($products[$pid])) continue; $product = $products[$pid]; $lineTotal = $product['price'] * $quantity; ?>
      <div class="cart-row">
        <span><?php echo htmlspecialchars($product['name']); ?></span>
        <strong>₱<?php echo number_format($lineTotal); ?></strong>
        <form class="quantity-controls" method="post" data-cart-form><input type="hidden" name="action" value="change_cart_quantity"><input type="hidden" name="product_id" value="<?php echo htmlspecialchars($pid); ?>"><button type="submit" name="change" value="-1" aria-label="Decrease quantity">−</button><span><?php echo (int) $quantity; ?></span><button type="submit" name="change" value="1" aria-label="Increase quantity" <?php echo $quantity >= $product['stock'] ? 'disabled' : ''; ?>>+</button></form>
        <form method="post" data-cart-form><input type="hidden" name="action" value="remove_from_cart"><input type="hidden" name="product_id" value="<?php echo htmlspecialchars($pid); ?>"><button class="remove-button" type="submit">Remove</button></form>
      </div>
    <?php endforeach; ?>
    <div class="cart-total">Total: ₱<?php echo number_format($cartTotal); ?></div>
    <a class="buy-now-btn" href="<?php echo htmlspecialchars($buyUrl); ?>"><span class="buy-now-icon" aria-hidden="true">⚡</span>Buy Now</a>
  </div>
<?php endif;
$cartHtml = ob_get_clean();

echo json_encode([
  'success' => true,
  'action' => $action,
  'productName' => $addedName,
  'productId' => $productId,
  'productStock' => isset($products[$productId]) ? (int) $products[$productId]['stock'] : null,
  'cartQuantity' => $cart[$productId] ?? 0,
  'cartCount' => $cartCount,
  'cartTotal' => $cartTotal,
  'cartHtml' => $cartHtml,
  'message' => $message,
]);
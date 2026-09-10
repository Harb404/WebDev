<?php
/**
 * Handles the cart-related form posts made from index.php itself
 * (add_to_cart / remove_from_cart / change_cart_quantity).
 * These are the plain-form fallbacks; the AJAX cart widget talks to
 * Cart.php instead. Expects $database, $products, and $cart to already
 * be set by the caller (see store-data.php), and redirects back to
 * index.php when done, same as before.
 */

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

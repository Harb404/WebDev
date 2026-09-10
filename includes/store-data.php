<?php
/**
 * Loads the data index.php needs to render the storefront: the product
 * catalog, average ratings, and the current session's cart.
 * Expects $database to already be set by the caller (see database.php).
 */

$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}

$productRatings = [];
foreach ($database->query('SELECT product_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count FROM reviews GROUP BY product_id')->fetchAll(PDO::FETCH_ASSOC) as $ratingRow) {
  $productRatings[$ratingRow['product_id']] = $ratingRow;
}

$cart = $_SESSION['cart'] ?? [];
// Drop any cart entries for products that no longer exist (e.g. deleted
// from the Admin Panel) so the cart count/total always match what the
// drawer actually displays instead of counting ghost items.
$cart = array_intersect_key($cart, $products);
$_SESSION['cart'] = $cart;
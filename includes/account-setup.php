<?php
/**
 * Initial page setup for account.php: loads the product catalog and
 * cart, silently provisions a users-table row for an admin session
 * that predates it, loads the logged-in customer's profile (kicking
 * banned accounts back to login), and sets the form-default variables
 * ($name, $address, ...) the view and the update_profile/buy actions
 * both read from.
 * Expects database.php to already be required by the caller (this file
 * calls getDatabase() itself).
 */

$database = getDatabase();
$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}
$cart = $_SESSION['cart'] ?? [];
// Drop any cart entries for products that no longer exist (e.g. deleted
// from the Admin Panel) so the checkout total always matches what's
// actually listed in the order summary.
$cart = array_intersect_key($cart, $products);
$_SESSION['cart'] = $cart;
$error = '';
$message = '';
$discountError = '';
$discountMessage = '';
$receiptNumber = null;
$showRegister = isset($_GET['register']);
$isCheckout = isset($_GET['checkout']);

// Legacy safety net only: login.php now sets $_SESSION['user_id'] to the
// actual admin who logged in, so this branch should no longer fire for
// fresh logins. It only exists for admin sessions created before that fix
// shipped. It intentionally does NOT guess an identity by hardcoded email
// anymore, since doing so previously caused every admin (including newly
// promoted ones) to see the original admin@masalihit.local account's
// profile/avatar instead of their own. Instead, send stale sessions back
// through login so they pick up their correct identity.
if (!empty($_SESSION['is_admin']) && empty($_SESSION['user_id'])) {
  unset($_SESSION['is_admin']);
  header('Location: login.php');
  exit;
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
$ewalletProvider = 'gcash';
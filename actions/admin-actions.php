<?php
/**
 * Handles every POST action submitted from the admin panels. Included by
 * admin.php after $products/$users/$orders are loaded. Every handler ends
 * with a redirect back to admin.php?panel=... (Post/Redirect/Get) so a page
 * refresh never resubmits the form.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return;
}

$action = $_POST['action'] ?? '';
$panel = $_POST['panel'] ?? 'overview';

/**
 * Sets the flash message shown at the top of admin.php after redirect, then
 * sends the admin back to the tab they were working on.
 */
function adminRedirect(string $panel, string $message): void
{
  $_SESSION['admin_flash_message'] = $message;
  header('Location: admin.php?panel=' . urlencode($panel));
  exit;
}

$allowedImageTypes = [
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/gif' => 'gif',
  'image/webp' => 'webp',
];

/**
 * Moves an uploaded product image into Pictures/ and returns its relative
 * path (e.g. "Pictures/m-luxe-cap-64f2a1.jpg"), or null if the upload was
 * invalid or failed to move.
 */
function saveProductImage(array $file, string $namehint, array $allowedImageTypes): ?string
{
  if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    return null;
  }
  $mimeType = mime_content_type($file['tmp_name']);
  if (!isset($allowedImageTypes[$mimeType])) {
    return null;
  }
  $extension = $allowedImageTypes[$mimeType];
  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $namehint));
  $slug = trim($slug, '-') ?: 'product';
  $filename = $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extension;

  $targetDir = __DIR__ . '/../Pictures';
  if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
  }
  $targetPath = $targetDir . '/' . $filename;

  if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    return null;
  }
  return 'Pictures/' . $filename;
}

/**
 * Records a stock change in stock_history so it shows up in the History tab.
 */
function logStockChange(PDO $database, string $productId, string $productName, int $previousStock, int $newStock, string $reason): void
{
  if ($previousStock === $newStock) {
    return;
  }
  $statement = $database->prepare('INSERT INTO stock_history (product_id, product_name, previous_stock, new_stock, change_amount, reason) VALUES (?, ?, ?, ?, ?, ?)');
  $statement->execute([$productId, $productName, $previousStock, $newStock, $newStock - $previousStock, $reason]);
}

/* ---------------- Products ---------------- */

if ($action === 'add_product') {
  $name = trim($_POST['name'] ?? '');
  $price = (int) ($_POST['price'] ?? 0);
  $stock = (int) ($_POST['stock'] ?? 0);

  if ($name === '' || $price < 0 || $stock < 0) {
    adminRedirect($panel, 'Please fill in a valid name, price, and stock.');
  }

  $imagePath = saveProductImage($_FILES['image'] ?? [], $name, $allowedImageTypes);
  if ($imagePath === null) {
    adminRedirect($panel, 'The image could not be uploaded.');
  }

  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
  $slug = trim($slug, '-') ?: 'product';
  $productId = $slug;
  $suffix = 2;
  $checkStatement = $database->prepare('SELECT COUNT(*) FROM products WHERE id = ?');
  while (true) {
    $checkStatement->execute([$productId]);
    if (!$checkStatement->fetchColumn()) {
      break;
    }
    $productId = $slug . '-' . $suffix;
    $suffix++;
  }

  $insertStatement = $database->prepare('INSERT INTO products (id, name, price, stock, image) VALUES (?, ?, ?, ?, ?)');
  $insertStatement->execute([$productId, $name, $price, $stock, $imagePath]);
  logStockChange($database, $productId, $name, 0, $stock, 'new_product');

  adminRedirect($panel, $name . ' was added to your products.');
}

if ($action === 'update_product') {
  $productId = $_POST['product_id'] ?? '';
  $name = trim($_POST['name'] ?? '');
  $price = (int) ($_POST['price'] ?? 0);
  $stock = (int) ($_POST['stock'] ?? 0);

  if ($productId === '' || $name === '' || $price < 0 || $stock < 0 || !isset($products[$productId])) {
    adminRedirect($panel, 'Please fill in a valid name, price, and stock.');
  }

  $previousStock = (int) $products[$productId]['stock'];
  $statement = $database->prepare('UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?');
  $statement->execute([$name, $price, $stock, $productId]);
  logStockChange($database, $productId, $name, $previousStock, $stock, 'manual_update');

  adminRedirect($panel, $name . ' was updated.');
}

if ($action === 'delete_product') {
  $productId = $_POST['product_id'] ?? '';
  if ($productId !== '' && isset($products[$productId])) {
    $database->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
    adminRedirect($panel, 'Product deleted.');
  }
  adminRedirect($panel, 'Product not found.');
}

/* ---------------- Orders ---------------- */

if ($action === 'update_delivery_status') {
  $orderId = (int) ($_POST['order_id'] ?? 0);
  $newStatus = $_POST['delivery_status'] ?? '';
  $estimatedDelivery = trim($_POST['estimated_delivery'] ?? '');

  if (!$orderId || !isset($orders[$orderId]) || !array_key_exists($newStatus, $deliveryStatuses)) {
    adminRedirect($panel, 'Order not found.');
  }

  $order = $orders[$orderId];
  $previousStatus = $order['delivery_status'] ?: 'pending';

  // Delivered is a final state — once set, it can no longer be changed.
  if ($previousStatus === 'delivered') {
    adminRedirect($panel, 'Order #' . $orderId . ' has already been delivered — its status is locked.');
  }

  $estimatedDeliveryValue = $estimatedDelivery !== '' ? date('Y-m-d H:i:s', strtotime($estimatedDelivery)) : null;

  $statement = $database->prepare('UPDATE orders SET delivery_status = ?, estimated_delivery = ?, status_seen = 0 WHERE id = ?');
  $statement->execute([$newStatus, $estimatedDeliveryValue, $orderId]);

  // A failed order returns its items' stock. Un-failing it (moving it back
  // to another status after being marked failed) takes that stock back out.
  if ($newStatus === 'failed' && $previousStatus !== 'failed') {
    $itemsStatement = $database->prepare('SELECT product_id, product_name, quantity FROM order_items WHERE order_id = ?');
    $itemsStatement->execute([$orderId]);
    foreach ($itemsStatement->fetchAll(PDO::FETCH_ASSOC) as $item) {
      if (!isset($products[$item['product_id']])) {
        continue;
      }
      $previousStock = (int) $products[$item['product_id']]['stock'];
      $newStock = $previousStock + (int) $item['quantity'];
      $database->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $item['product_id']]);
      logStockChange($database, $item['product_id'], $item['product_name'], $previousStock, $newStock, 'order_failed');
    }
  } elseif ($previousStatus === 'failed' && $newStatus !== 'failed') {
    $itemsStatement = $database->prepare('SELECT product_id, product_name, quantity FROM order_items WHERE order_id = ?');
    $itemsStatement->execute([$orderId]);
    foreach ($itemsStatement->fetchAll(PDO::FETCH_ASSOC) as $item) {
      if (!isset($products[$item['product_id']])) {
        continue;
      }
      $previousStock = (int) $products[$item['product_id']]['stock'];
      $newStock = max(0, $previousStock - (int) $item['quantity']);
      $database->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $item['product_id']]);
      logStockChange($database, $item['product_id'], $item['product_name'], $previousStock, $newStock, 'order_reactivated');
    }
  }

  adminRedirect($panel, 'Order #' . $orderId . ' updated.');
}

/* ---------------- Refunds ---------------- */

if ($action === 'approve_refund') {
  $orderId = (int) ($_POST['order_id'] ?? 0);
  if (!$orderId || !isset($orders[$orderId]) || ($orders[$orderId]['refund_status'] ?? 'none') !== 'requested') {
    adminRedirect($panel, 'Refund request not found.');
  }

  $database->prepare("UPDATE orders SET refund_status = 'approved', refunded_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$orderId]);

  // Refunding an order returns its items' stock, same as a failed order —
  // the customer keeps the money back, not the goods.
  $itemsStatement = $database->prepare('SELECT product_id, product_name, quantity FROM order_items WHERE order_id = ?');
  $itemsStatement->execute([$orderId]);
  foreach ($itemsStatement->fetchAll(PDO::FETCH_ASSOC) as $item) {
    if (!isset($products[$item['product_id']])) {
      continue;
    }
    $previousStock = (int) $products[$item['product_id']]['stock'];
    $newStock = $previousStock + (int) $item['quantity'];
    $database->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $item['product_id']]);
    logStockChange($database, $item['product_id'], $item['product_name'], $previousStock, $newStock, 'order_refunded');
  }

  adminRedirect($panel, 'Order #' . $orderId . ' was refunded and its stock restored.');
}

if ($action === 'deny_refund') {
  $orderId = (int) ($_POST['order_id'] ?? 0);
  if (!$orderId || !isset($orders[$orderId]) || ($orders[$orderId]['refund_status'] ?? 'none') !== 'requested') {
    adminRedirect($panel, 'Refund request not found.');
  }
  $database->prepare("UPDATE orders SET refund_status = 'denied' WHERE id = ?")->execute([$orderId]);
  adminRedirect($panel, 'Refund request for order #' . $orderId . ' was denied.');
}

/* ---------------- Customers ---------------- */

if (in_array($action, ['ban_user', 'unban_user', 'delete_user'], true)) {
  $userId = (int) ($_POST['user_id'] ?? 0);
  $targetUser = null;
  foreach ($users as $user) {
    if ((int) $user['id'] === $userId) {
      $targetUser = $user;
      break;
    }
  }
  if (!$userId || !$targetUser || $targetUser['email'] === 'admin@masalihit.local') {
    adminRedirect($panel, 'Customer not found.');
  }

  if ($action === 'ban_user') {
    $database->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$userId]);
    adminRedirect($panel, $targetUser['name'] . ' has been banned.');
  }
  if ($action === 'unban_user') {
    $database->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$userId]);
    adminRedirect($panel, $targetUser['name'] . ' has been unbanned.');
  }
  if ($action === 'delete_user') {
    $database->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    adminRedirect($panel, $targetUser['name'] . ' was deleted.');
  }
}

/* ---------------- Discount Codes ---------------- */

if ($action === 'create_discount_code') {
  $code = strtoupper(trim($_POST['code'] ?? ''));
  $percent = (int) ($_POST['percent'] ?? 0);
  $maxUses = (int) ($_POST['max_uses'] ?? 1);

  if ($code === '' || !preg_match('/^[A-Z0-9\-]{2,50}$/', $code)) {
    adminRedirect($panel, 'Please enter a valid code (letters, numbers, dashes only).');
  }
  if ($percent < 1 || $percent > 100) {
    adminRedirect($panel, 'Discount percent must be between 1 and 100.');
  }
  if ($maxUses < 1 || $maxUses > 1000) {
    adminRedirect($panel, 'Max uses must be between 1 and 1000.');
  }

  try {
    $statement = $database->prepare('INSERT INTO discount_codes (code, percent, max_uses) VALUES (?, ?, ?)');
    $statement->execute([$code, $percent, $maxUses]);
    adminRedirect($panel, 'Discount code "' . $code . '" (' . $percent . '% off, ' . $maxUses . ' use' . ($maxUses === 1 ? '' : 's') . ') created.');
  } catch (PDOException $exception) {
    adminRedirect($panel, 'That code already exists — choose a different one.');
  }
}

if ($action === 'reactivate_discount_code') {
  $codeId = (int) ($_POST['code_id'] ?? 0);
  if ($codeId) {
    $codeRowStatement = $database->prepare('SELECT code FROM discount_codes WHERE id = ?');
    $codeRowStatement->execute([$codeId]);
    $codeName = $codeRowStatement->fetchColumn();
    if ($codeName !== false) {
      // Resets the counter so the code can be redeemed up to max_uses again.
      // The discount_code_uses log is left untouched, so "used by ..." history
      // for prior redemptions still shows up on this code.
      $database->prepare('UPDATE discount_codes SET is_used = 0, used_count = 0, used_by_order_id = NULL, used_at = NULL WHERE id = ?')->execute([$codeId]);
      adminRedirect($panel, 'Discount code "' . $codeName . '" is available again.');
    }
  }
  adminRedirect($panel, 'Discount code not found.');
}

if ($action === 'delete_discount_code') {
  $codeId = (int) ($_POST['code_id'] ?? 0);
  if ($codeId) {
    // Used codes can still be deleted from here — the order that redeemed it
    // keeps its own price and total regardless, so nothing else breaks.
    $database->prepare('DELETE FROM discount_codes WHERE id = ?')->execute([$codeId]);
  }
  adminRedirect($panel, 'Discount code deleted.');
}

/* ---------------- Feedback ---------------- */

if ($action === 'toggle_featured_review') {
  $reviewId = (int) ($_POST['review_id'] ?? 0);
  if ($reviewId) {
    $currentStatement = $database->prepare('SELECT is_featured FROM reviews WHERE id = ?');
    $currentStatement->execute([$reviewId]);
    $currentValue = $currentStatement->fetchColumn();
    if ($currentValue !== false) {
      $newValue = $currentValue ? 0 : 1;
      $database->prepare('UPDATE reviews SET is_featured = ? WHERE id = ?')->execute([$newValue, $reviewId]);
      adminRedirect($panel, $newValue ? 'Review added to the homepage testimonials.' : 'Review removed from the homepage testimonials.');
    }
  }
  adminRedirect($panel, 'Review not found.');
}

if ($action === 'delete_review') {
  $reviewId = (int) ($_POST['review_id'] ?? 0);
  if ($reviewId) {
    $database->prepare('DELETE FROM reviews WHERE id = ?')->execute([$reviewId]);
  }
  adminRedirect($panel, 'Review deleted.');
}
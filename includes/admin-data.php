<?php
/**
 * Loads the data admin.php needs: $products, $users, $orders, plus the
 * shared label maps used across the admin panels. Included by admin.php
 * right after requireAdmin(), before any POST actions are processed.
 */

$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}

$users = $database->query('SELECT id, name, email, role, status, address, location, zip, phone FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$orderRows = $database->query('SELECT id, user_id, total, address, location, zip, phone, comments, delivery_status, estimated_delivery, receipt_number, payment_method, refund_status, refund_reason, refund_requested_at, refunded_at, created_at FROM orders ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

$orders = [];
foreach ($orderRows as $row) {
  $row['items'] = [];
  $orders[$row['id']] = $row;
}

if ($orders) {
  $buyerStatement = $database->prepare('SELECT name, email FROM users WHERE id = ?');
  foreach ($orders as $orderId => &$order) {
    $buyerStatement->execute([$order['user_id']]);
    $buyer = $buyerStatement->fetch(PDO::FETCH_ASSOC);
    $order['buyer_name'] = $buyer['name'] ?? 'Unknown';
    $order['buyer_email'] = $buyer['email'] ?? '';
  }
  unset($order);

  $placeholders = implode(',', array_fill(0, count($orders), '?'));
  $itemsStatement = $database->prepare("SELECT order_id, product_name, quantity, price FROM order_items WHERE order_id IN ($placeholders)");
  $itemsStatement->execute(array_keys($orders));
  foreach ($itemsStatement->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $orders[$item['order_id']]['items'][] = $item['quantity'] . 'x ' . $item['product_name'];
  }
}

// Any order that's ever had a refund touch it (requested, approved, or
// denied), shown with pending requests first so they don't get buried.
$refundRequests = array_filter($orders, fn($order) => ($order['refund_status'] ?? 'none') !== 'none');
uasort($refundRequests, function ($a, $b) {
  $refundRank = ['requested' => 0, 'approved' => 1, 'denied' => 2];
  $rankDiff = ($refundRank[$a['refund_status']] ?? 3) <=> ($refundRank[$b['refund_status']] ?? 3);
  return $rankDiff !== 0 ? $rankDiff : $b['id'] <=> $a['id'];
});

$reviews = $database->query("
  SELECT reviews.id, reviews.rating, reviews.comment, reviews.is_featured, reviews.created_at,
         products.name AS product_name,
         users.name AS user_name, users.location AS user_location
  FROM reviews
  JOIN products ON products.id = reviews.product_id
  JOIN users ON users.id = reviews.user_id
  ORDER BY reviews.is_featured DESC, reviews.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$deliveryStatuses = [
  'pending' => 'Pending',
  'processing' => 'Processing',
  'shipped' => 'Shipped',
  'delivered' => 'Delivered',
  'failed' => 'Failed',
];

$paymentMethodLabels = [
  'cod' => 'Cash on Delivery',
  'gcash' => 'GCash',
];

$discountCodes = $database->query('
  SELECT dc.id, dc.code, dc.percent, dc.max_uses, dc.used_count, dc.is_used, dc.created_at
  FROM discount_codes dc
  ORDER BY dc.id DESC
')->fetchAll(PDO::FETCH_ASSOC);

if ($discountCodes) {
  $discountUsesStatement = $database->query('
    SELECT dcu.code_id, dcu.order_id, dcu.used_at, u.name AS used_by_name
    FROM discount_code_uses dcu
    LEFT JOIN orders o ON o.id = dcu.order_id
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY dcu.id ASC
  ')->fetchAll(PDO::FETCH_ASSOC);
  $discountUsesByCode = [];
  foreach ($discountUsesStatement as $use) {
    $discountUsesByCode[$use['code_id']][] = $use;
  }
  foreach ($discountCodes as &$discountCodeRow) {
    $discountCodeRow['uses'] = $discountUsesByCode[$discountCodeRow['id']] ?? [];
  }
  unset($discountCodeRow);
}
<?php
/**
 * Data the account.php view needs, computed after any POST action
 * above has run: the cart total, the applied discount code (if any)
 * and the resulting checkout total, the purchase history (grouped by
 * order with its line items), one-time delivered/failed
 * notifications, and the reviews the customer has already left.
 */

$cartTotal = 0;
foreach ($cart as $productId => $quantity) {
  if (isset($products[$productId])) {
    $cartTotal += $products[$productId]['price'] * $quantity;
  }
}

$discountAmount = 0;
$discountPercent = 0;
$appliedDiscountCode = null;
if (!empty($_SESSION['applied_discount_code'])) {
  $appliedCodeStatement = $database->prepare('SELECT code, percent, max_uses, used_count FROM discount_codes WHERE code = ?');
  $appliedCodeStatement->execute([$_SESSION['applied_discount_code']]);
  $appliedCodeRowView = $appliedCodeStatement->fetch(PDO::FETCH_ASSOC);
  if ($appliedCodeRowView && (int) $appliedCodeRowView['used_count'] < (int) $appliedCodeRowView['max_uses']) {
    $appliedDiscountCode = $appliedCodeRowView;
    $discountPercent = (int) $appliedCodeRowView['percent'];
    $discountAmount = (int) round($cartTotal * $discountPercent / 100);
  } else {
    // Code was used/removed elsewhere (e.g. another tab) since it was applied.
    unset($_SESSION['applied_discount_code']);
  }
}
$shippingFee = SHIPPING_FEE;
$checkoutTotal = max(0, $cartTotal - $discountAmount) + $shippingFee;

$purchaseHistory = [];
$existingReviews = [];
$orderNotifications = [];
if (!empty($_SESSION['user_id'])) {
  $historyStatement = $database->prepare('SELECT orders.id, orders.total, orders.shipping_fee, orders.address, orders.location, orders.zip, orders.phone, orders.payment_method, orders.ewallet_provider, orders.comments, orders.delivery_status, orders.status_seen, orders.estimated_delivery, orders.receipt_number, orders.refund_status, orders.refund_reason, orders.refund_requested_at, orders.refunded_at, orders.created_at, discount_codes.code AS discount_code, discount_codes.percent AS discount_percent, order_items.product_id, order_items.product_name, order_items.quantity, order_items.price FROM orders JOIN order_items ON order_items.order_id = orders.id LEFT JOIN discount_code_uses ON discount_code_uses.order_id = orders.id LEFT JOIN discount_codes ON discount_codes.id = discount_code_uses.code_id WHERE orders.user_id = ? ORDER BY orders.id DESC');
  $historyStatement->execute([$_SESSION['user_id']]);
  while ($historyItem = $historyStatement->fetch(PDO::FETCH_ASSOC)) {
    $purchaseHistory[$historyItem['id']]['total'] = $historyItem['total'];
    $purchaseHistory[$historyItem['id']]['shipping_fee'] = (int) $historyItem['shipping_fee'];
    $purchaseHistory[$historyItem['id']]['address'] = $historyItem['address'];
    $purchaseHistory[$historyItem['id']]['location'] = $historyItem['location'];
    $purchaseHistory[$historyItem['id']]['zip'] = $historyItem['zip'];
    $purchaseHistory[$historyItem['id']]['phone'] = $historyItem['phone'];
    $purchaseHistory[$historyItem['id']]['payment_method'] = $historyItem['payment_method'];
    $purchaseHistory[$historyItem['id']]['ewallet_provider'] = $historyItem['ewallet_provider'];
    $purchaseHistory[$historyItem['id']]['comments'] = $historyItem['comments'];
    $purchaseHistory[$historyItem['id']]['delivery_status'] = $historyItem['delivery_status'];
    $purchaseHistory[$historyItem['id']]['status_seen'] = (int) $historyItem['status_seen'];
    $purchaseHistory[$historyItem['id']]['estimated_delivery'] = $historyItem['estimated_delivery'];
    $purchaseHistory[$historyItem['id']]['receipt_number'] = $historyItem['receipt_number'];
    $purchaseHistory[$historyItem['id']]['refund_status'] = $historyItem['refund_status'];
    $purchaseHistory[$historyItem['id']]['refund_reason'] = $historyItem['refund_reason'];
    $purchaseHistory[$historyItem['id']]['refund_requested_at'] = $historyItem['refund_requested_at'];
    $purchaseHistory[$historyItem['id']]['refunded_at'] = $historyItem['refunded_at'];
    $purchaseHistory[$historyItem['id']]['created_at'] = $historyItem['created_at'];
    $purchaseHistory[$historyItem['id']]['discount_code'] = $historyItem['discount_code'];
    $purchaseHistory[$historyItem['id']]['discount_percent'] = $historyItem['discount_percent'];
    $purchaseHistory[$historyItem['id']]['items'][] = [
      'product_id' => $historyItem['product_id'],
      'product_name' => $historyItem['product_name'],
      'quantity' => $historyItem['quantity'],
      'price' => $historyItem['price'],
    ];
  }

  // Surface a one-time notification for any order that just became Delivered
  // or Failed since the customer last looked, then clear the flag so it
  // doesn't show again on the next visit.
  $seenIds = [];
  foreach ($purchaseHistory as $notifyOrderId => $notifyOrder) {
    if ($notifyOrder['status_seen'] === 0 && in_array($notifyOrder['delivery_status'], ['delivered', 'failed'], true)) {
      $orderNotifications[] = [
        'status' => $notifyOrder['delivery_status'],
        'ordered_at' => $notifyOrder['created_at'] ? date('M j, Y', strtotime($notifyOrder['created_at'])) : '',
      ];
      $seenIds[] = $notifyOrderId;
    }
  }
  if ($seenIds) {
    $seenPlaceholders = implode(',', array_fill(0, count($seenIds), '?'));
    $database->prepare("UPDATE orders SET status_seen = 1 WHERE user_id = ? AND id IN ($seenPlaceholders)")->execute(array_merge([$_SESSION['user_id']], $seenIds));
  }

  $reviewStatement = $database->prepare('SELECT product_id, rating, comment FROM reviews WHERE user_id = ?');
  $reviewStatement->execute([$_SESSION['user_id']]);
  foreach ($reviewStatement->fetchAll(PDO::FETCH_ASSOC) as $review) {
    $existingReviews[$review['product_id']] = $review;
  }
}
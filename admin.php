<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validation.php';
requireAdmin();

$database = getDatabase();
require_once __DIR__ . '/includes/admin-data.php'; // loads $products, $users, $orders, $deliveryStatuses, $paymentMethodLabels, $discountCodes
$message = $_SESSION['admin_flash_message'] ?? '';
unset($_SESSION['admin_flash_message']);

// Which sidebar tab should be shown when the page loads. Every admin form
// carries a hidden "panel" field naming the tab it lives on, so after a
// redirect-after-post we land back where the admin was working instead of
// always snapping to the Dashboard. A plain visit/refresh of admin.php
// (no ?panel= in the URL) still defaults to the Dashboard, as before.
$validPanels = ['overview', 'products', 'orders', 'refunds', 'history', 'customers', 'discounts', 'messages'];
$activePanel = $_GET['panel'] ?? 'overview';
if (!in_array($activePanel, $validPanels, true)) {
  $activePanel = 'overview';
}

require_once __DIR__ . '/actions/admin-actions.php';

// --- Dashboard stats (computed after any POST updates above) ---
$statOrderCount = count($orders);
$statTotalRevenue = 0;
$statDeliveredRevenue = 0;
$statStatusCounts = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'failed' => 0];
$statRefundRequestCount = 0;
$statRefundedRevenue = 0;
foreach ($orders as $order) {
  $status = $order['delivery_status'] ?: 'pending';
  $statStatusCounts[$status] = ($statStatusCounts[$status] ?? 0) + 1;
  $isRefunded = ($order['refund_status'] ?? 'none') === 'approved';
  if ($isRefunded) {
    $statRefundedRevenue += (int) $order['total'];
  }
  if (($order['refund_status'] ?? 'none') === 'requested') {
    $statRefundRequestCount++;
  }
  // A refunded order stops counting toward revenue, just like a failed one —
  // the money went back to the customer.
  if ($status !== 'failed' && !$isRefunded) {
    $statTotalRevenue += (int) $order['total'];
  }
  if ($status === 'delivered' && !$isRefunded) {
    $statDeliveredRevenue += (int) $order['total'];
  }
}
$statProductCount = count($products);
$statTotalStock = array_sum(array_column($products, 'stock'));
$statLowStockCount = 0;
foreach ($products as $product) {
  if ((int) $product['stock'] <= 5) {
    $statLowStockCount++;
  }
}
$statUserCount = 0;
foreach ($users as $user) {
  if (($user['role'] ?? '') === 'client') {
    $statUserCount++;
  }
}
$stockHistory = $database->query('SELECT product_name, previous_stock, new_stock, change_amount, reason, created_at FROM stock_history ORDER BY id DESC LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);
$stockHistoryReasonLabels = ['manual_update' => 'Manual edit', 'quick_add' => 'Quick add', 'new_product' => 'New product', 'order_failed' => 'Failed order (restocked)', 'order_reactivated' => 'Order un-failed', 'order_refunded' => 'Refund (restocked)'];

// --- Monthly revenue trend (current calendar year) for the dashboard chart ---
$monthlyRevenue = array_fill(1, 12, 0);
$monthlyStatement = $database->prepare("SELECT MONTH(created_at) AS m, SUM(total) AS revenue FROM orders WHERE YEAR(created_at) = ? AND delivery_status != 'failed' AND refund_status != 'approved' GROUP BY MONTH(created_at)");
$monthlyStatement->execute([date('Y')]);
foreach ($monthlyStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $monthlyRevenue[(int) $row['m']] = (int) $row['revenue'];
}
$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$maxMonthlyRevenue = max(1, max($monthlyRevenue));

// --- Top products by revenue, for the ranking list ---
$topProducts = $database->query("SELECT order_items.product_name, SUM(order_items.quantity) AS qty, SUM(order_items.quantity * order_items.price) AS revenue FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE orders.delivery_status != 'failed' AND orders.refund_status != 'approved' GROUP BY order_items.product_name ORDER BY revenue DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — Masalihit Luxe</title>
<link rel="stylesheet" href="admin-style.css?v=<?php echo file_exists(__DIR__ . '/admin-style.css') ? filemtime(__DIR__ . '/admin-style.css') : time(); ?>">
</head>
<body class="dash-body">
<div class="dash-shell">

<?php include __DIR__ . '/admin/sidebar.php'; ?>

  <div class="dash-main">
<?php include __DIR__ . '/admin/topbar.php'; ?>

    <div class="dash-content">
      <?php if ($message): ?><div class="dash-message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php include __DIR__ . '/admin/panel-overview.php'; ?>

<?php include __DIR__ . '/admin/panel-products.php'; ?>

<?php include __DIR__ . '/admin/panel-orders.php'; ?>

<?php include __DIR__ . '/admin/panel-refunds.php'; ?>

<?php include __DIR__ . '/admin/panel-history.php'; ?>

<?php include __DIR__ . '/admin/panel-customers.php'; ?>

<?php include __DIR__ . '/admin/panel-discounts.php'; ?>

<?php include __DIR__ . '/admin/panel-messages.php'; ?>
    </div>
  </div>
</div>

<script src="admin/chat-widget.js?v=<?php echo file_exists(__DIR__ . '/admin/chat-widget.js') ? filemtime(__DIR__ . '/admin/chat-widget.js') : time(); ?>" defer></script>
</body>
</html>
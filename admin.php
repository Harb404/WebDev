<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validation.php';
requireAdmin();

$database = getDatabase();
$products = [];
foreach ($database->query('SELECT id, name, price, stock, image FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $product) {
  $products[$product['id']] = $product;
}
$users = $database->query('SELECT id, name, email, role, status, address, location, zip, phone FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$orders = [];
$orderStatement = $database->query('SELECT orders.id, orders.total, orders.address, orders.location, orders.zip, orders.phone, orders.payment_method, orders.comments, orders.delivery_status, orders.estimated_delivery, orders.created_at, users.name AS buyer_name, users.email AS buyer_email, order_items.product_name, order_items.quantity FROM orders JOIN users ON users.id = orders.user_id JOIN order_items ON order_items.order_id = orders.id ORDER BY orders.id DESC');
while ($orderItem = $orderStatement->fetch(PDO::FETCH_ASSOC)) {
  $orderId = $orderItem['id'];
  $orders[$orderId]['total'] = $orderItem['total'];
  $orders[$orderId]['address'] = $orderItem['address'];
  $orders[$orderId]['location'] = $orderItem['location'];
  $orders[$orderId]['zip'] = $orderItem['zip'];
  $orders[$orderId]['phone'] = $orderItem['phone'];
  $orders[$orderId]['payment_method'] = $orderItem['payment_method'];
  $orders[$orderId]['comments'] = $orderItem['comments'];
  $orders[$orderId]['delivery_status'] = $orderItem['delivery_status'];
  $orders[$orderId]['estimated_delivery'] = $orderItem['estimated_delivery'];
  $orders[$orderId]['created_at'] = $orderItem['created_at'];
  $orders[$orderId]['buyer_name'] = $orderItem['buyer_name'];
  $orders[$orderId]['buyer_email'] = $orderItem['buyer_email'];
  $orders[$orderId]['items'][] = $orderItem['product_name'] . ' × ' . $orderItem['quantity'];
}
$deliveryStatuses = ['processing' => 'Processing', 'delivered' => 'Delivered', 'failed' => 'Failed'];
$paymentMethodLabels = ['cod' => 'Cash on Delivery', 'ewallet' => 'E-Wallet'];
$message = '';

function logStockChange(PDO $database, string $productId, string $productName, int $previousStock, int $newStock, string $reason): void
{
  if ($previousStock === $newStock) {
    return;
  }
  $statement = $database->prepare('INSERT INTO stock_history (product_id, product_name, previous_stock, new_stock, change_amount, reason) VALUES (?, ?, ?, ?, ?, ?)');
  $statement->execute([$productId, $productName, $previousStock, $newStock, $newStock - $previousStock, $reason]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $newStock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $image = $_FILES['image'] ?? null;
    $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $imageInfo = $image && $image['error'] === UPLOAD_ERR_OK ? getimagesize($image['tmp_name']) : false;
    $imageExtension = $imageInfo ? ($allowedMimeTypes[$imageInfo['mime']] ?? null) : null;

    if ($name === '' || $price === false || $price === null || $newStock === false || $newStock === null || !$imageExtension) {
      $message = 'Enter product details and upload a JPG, PNG, GIF, or WEBP image.';
    } else {
      $productId = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) . '-' . time();
      $imageName = $productId . '.' . $imageExtension;
      $imagePath = __DIR__ . '/Pictures/' . $imageName;
      if (move_uploaded_file($image['tmp_name'], $imagePath)) {
        $statement = $database->prepare('INSERT INTO products (id, name, price, stock, image) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$productId, $name, $price, $newStock, 'Pictures/' . $imageName]);
        $products[$productId] = ['id' => $productId, 'name' => $name, 'price' => $price, 'stock' => $newStock, 'image' => 'Pictures/' . $imageName];
        logStockChange($database, $productId, $name, 0, $newStock, 'new_product');
        $message = 'Product added successfully.';
      } else {
        $message = 'The image could not be uploaded.';
      }
    }
  }

  $productId = $_POST['product_id'] ?? '';
  $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
  if ($action !== 'add_product' && isset($products[$productId]) && $stock !== false && $stock !== null) {
    $previousStock = (int) $products[$productId]['stock'];
    $statement = $database->prepare('UPDATE products SET stock = ? WHERE id = ?');
    $statement->execute([$stock, $productId]);
    $products[$productId]['stock'] = $stock;
    logStockChange($database, $productId, $products[$productId]['name'], $previousStock, (int) $stock, 'manual_update');
    $message = 'Stock updated successfully.';
  }

  if ($action === 'quick_add_stock') {
    $quickProductId = $_POST['quick_product_id'] ?? '';
    $addAmount = filter_input(INPUT_POST, 'add_amount', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (isset($products[$quickProductId]) && $addAmount) {
      $previousStock = (int) $products[$quickProductId]['stock'];
      $newStockValue = $previousStock + $addAmount;
      $statement = $database->prepare('UPDATE products SET stock = ? WHERE id = ?');
      $statement->execute([$newStockValue, $quickProductId]);
      $products[$quickProductId]['stock'] = $newStockValue;
      logStockChange($database, $quickProductId, $products[$quickProductId]['name'], $previousStock, $newStockValue, 'quick_add');
      $message = 'Added ' . $addAmount . ' units to ' . $products[$quickProductId]['name'] . '.';
    } else {
      $message = 'Choose a product and a valid quantity to add.';
    }
  }

  if ($action === 'delete_product' && isset($products[$productId])) {
    $statement = $database->prepare('DELETE FROM products WHERE id = ?');
    $statement->execute([$productId]);
    $imagePath = __DIR__ . '/' . $products[$productId]['image'];
    if (is_file($imagePath) && str_starts_with(realpath($imagePath), realpath(__DIR__ . '/Pictures'))) {
      unlink($imagePath);
    }
    unset($products[$productId]);
    $message = 'Product deleted successfully.';
  }
  if ($action === 'update_delivery_status') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = $_POST['delivery_status'] ?? '';
    $estimatedDeliveryInput = trim($_POST['estimated_delivery'] ?? '');
    $estimatedDelivery = null;
    if ($estimatedDeliveryInput !== '') {
      $parsedDate = DateTime::createFromFormat('Y-m-d\TH:i', $estimatedDeliveryInput);
      if ($parsedDate) {
        $estimatedDelivery = $parsedDate->format('Y-m-d H:i:s');
      }
    }
    if ($orderId && isset($deliveryStatuses[$status])) {
      $previousStatus = $orders[$orderId]['delivery_status'] ?? 'processing';
      $statement = $database->prepare('UPDATE orders SET delivery_status = ?, estimated_delivery = ? WHERE id = ?');
      $statement->execute([$status, $estimatedDelivery, $orderId]);
      if (isset($orders[$orderId])) {
        $orders[$orderId]['delivery_status'] = $status;
        $orders[$orderId]['estimated_delivery'] = $estimatedDelivery;
      }
      $message = 'Order #' . $orderId . ' marked as ' . $deliveryStatuses[$status] . ($estimatedDelivery ? ', expected delivery updated.' : '.');

      // A failed delivery means the items never reached the customer, so put
      // that stock back. Only do this the moment the order *becomes* failed,
      // so re-saving the same status twice doesn't restock it twice.
      if ($status === 'failed' && $previousStatus !== 'failed') {
        $failedItemsStatement = $database->prepare('SELECT product_id, product_name, quantity FROM order_items WHERE order_id = ?');
        $failedItemsStatement->execute([$orderId]);
        foreach ($failedItemsStatement->fetchAll(PDO::FETCH_ASSOC) as $failedItem) {
          if (!isset($products[$failedItem['product_id']])) {
            continue; // product may have since been removed from the catalog
          }
          $previousStock = (int) $products[$failedItem['product_id']]['stock'];
          $newStock = $previousStock + (int) $failedItem['quantity'];
          $database->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([(int) $failedItem['quantity'], $failedItem['product_id']]);
          $products[$failedItem['product_id']]['stock'] = $newStock;
          logStockChange($database, $failedItem['product_id'], $failedItem['product_name'], $previousStock, $newStock, 'order_failed');
        }
        $message .= ' Stock was returned to inventory.';
      }

      // If an order is moved back off "Failed" (e.g. corrected by mistake),
      // take that restocked amount back out so inventory stays accurate.
      if ($status !== 'failed' && $previousStatus === 'failed') {
        $revivedItemsStatement = $database->prepare('SELECT product_id, product_name, quantity FROM order_items WHERE order_id = ?');
        $revivedItemsStatement->execute([$orderId]);
        foreach ($revivedItemsStatement->fetchAll(PDO::FETCH_ASSOC) as $revivedItem) {
          if (!isset($products[$revivedItem['product_id']])) {
            continue;
          }
          $previousStock = (int) $products[$revivedItem['product_id']]['stock'];
          $newStock = max(0, $previousStock - (int) $revivedItem['quantity']);
          $database->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $revivedItem['product_id']]);
          $products[$revivedItem['product_id']]['stock'] = $newStock;
          logStockChange($database, $revivedItem['product_id'], $revivedItem['product_name'], $previousStock, $newStock, 'order_reactivated');
        }
      }
    }
  }

  if (in_array($action, ['ban_user', 'unban_user', 'promote_admin', 'demote_admin', 'delete_user'], true)) {
    $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $targetUser = null;
    foreach ($users as $userRow) {
      if ((int) $userRow['id'] === $targetUserId) {
        $targetUser = $userRow;
        break;
      }
    }

    if (!$targetUserId || !$targetUser) {
      $message = 'Customer not found.';
    } elseif ($targetUser['email'] === 'admin@masalihit.local') {
      // Protect the seed admin account from being banned, demoted, or deleted
      // by mistake — that would lock everyone out of the admin panel.
      $message = 'The default administrator account can\'t be modified here.';
    } else {
      if ($action === 'ban_user') {
        $database->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$targetUserId]);
        $message = $targetUser['name'] . ' has been banned and can no longer log in.';
        $targetUser['status'] = 'banned';
      } elseif ($action === 'unban_user') {
        $database->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetUserId]);
        $message = $targetUser['name'] . ' has been unbanned.';
        $targetUser['status'] = 'active';
      } elseif ($action === 'promote_admin') {
        $database->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetUserId]);
        $message = $targetUser['name'] . ' is now an admin.';
        $targetUser['role'] = 'admin';
      } elseif ($action === 'demote_admin') {
        $database->prepare("UPDATE users SET role = 'client' WHERE id = ?")->execute([$targetUserId]);
        $message = $targetUser['name'] . ' is now a regular customer.';
        $targetUser['role'] = 'client';
      } elseif ($action === 'delete_user') {
        try {
          $database->prepare('DELETE FROM users WHERE id = ?')->execute([$targetUserId]);
          $message = $targetUser['name'] . ' was deleted.';
          $targetUser = null;
        } catch (PDOException $exception) {
          $message = 'Can\'t delete this customer — they have existing orders or reviews on record. Ban them instead.';
        }
      }
      // Reflect the change in the in-memory list so the page below shows the
      // update immediately, without a second request.
      foreach ($users as $userIndex => $userRow) {
        if ((int) $userRow['id'] === $targetUserId) {
          if ($targetUser === null) {
            unset($users[$userIndex]);
          } else {
            $users[$userIndex] = $targetUser;
          }
          break;
        }
      }
    }
  }
}

// --- Dashboard stats (computed after any POST updates above) ---
$statOrderCount = count($orders);
$statTotalRevenue = 0;
$statDeliveredRevenue = 0;
$statStatusCounts = ['processing' => 0, 'delivered' => 0, 'failed' => 0];
foreach ($orders as $order) {
  $status = $order['delivery_status'] ?: 'processing';
  $statStatusCounts[$status] = ($statStatusCounts[$status] ?? 0) + 1;
  if ($status !== 'failed') {
    $statTotalRevenue += (int) $order['total'];
  }
  if ($status === 'delivered') {
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
$stockHistoryReasonLabels = ['manual_update' => 'Manual edit', 'quick_add' => 'Quick add', 'new_product' => 'New product', 'order_failed' => 'Failed order (restocked)', 'order_reactivated' => 'Order un-failed'];

// --- Monthly revenue trend (current calendar year) for the dashboard chart ---
$monthlyRevenue = array_fill(1, 12, 0);
$monthlyStatement = $database->prepare("SELECT MONTH(created_at) AS m, SUM(total) AS revenue FROM orders WHERE YEAR(created_at) = ? AND delivery_status != 'failed' GROUP BY MONTH(created_at)");
$monthlyStatement->execute([date('Y')]);
foreach ($monthlyStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $monthlyRevenue[(int) $row['m']] = (int) $row['revenue'];
}
$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$maxMonthlyRevenue = max(1, max($monthlyRevenue));

// --- Top products by revenue, for the ranking list ---
$topProducts = $database->query("SELECT order_items.product_name, SUM(order_items.quantity) AS qty, SUM(order_items.quantity * order_items.price) AS revenue FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE orders.delivery_status != 'failed' GROUP BY order_items.product_name ORDER BY revenue DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
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

  <aside class="dash-sidebar">
    <div class="dash-brand"><span class="dash-brand-mark"><img src="Pictures/logoh.png" alt="Masalihit Luxe logo"></span> Masalihit Luxe</div>
    <nav class="dash-nav">
      <button type="button" class="dash-nav-item active" data-section="overview">
        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg>
        Dashboard
      </button>
      <button type="button" class="dash-nav-item" data-section="products">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 7.5L12 12l8-4.5M12 12v9" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Products
      </button>
      <button type="button" class="dash-nav-item" data-section="stock">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 4v16m-7-8h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Add Stock
      </button>
      <button type="button" class="dash-nav-item" data-section="orders">
        <svg viewBox="0 0 24 24" fill="none"><path d="M6 3h12l1 5H5l1-5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M5 8h14l-1.2 11.2a1 1 0 01-1 .8H7.2a1 1 0 01-1-.8L5 8z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Orders
      </button>
      <button type="button" class="dash-nav-item" data-section="history">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        History
      </button>
      <button type="button" class="dash-nav-item" data-section="customers">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Customers
      </button>
      <button type="button" class="dash-nav-item" data-section="messages">
        <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H9l-4.5 3.5V17H4a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Messages
        <span class="dash-nav-badge" id="chat-nav-badge" hidden>0</span>
      </button>
    </nav>
    <div class="dash-sidebar-footer">
      <a href="index.php">View Store</a>
      <a href="logout.php">Logout</a>
    </div>
  </aside>

  <div class="dash-main">
    <header class="dash-topbar">
      <div class="dash-topbar-title">Admin Panel</div>
      <div class="dash-topbar-actions">
        <div class="dash-notif-wrap">
          <button type="button" class="dash-notif-chat" id="chat-topbar-bell" title="Client messages">
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H9l-4.5 3.5V17H4a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
            <span class="dash-notif-badge" id="chat-topbar-badge" hidden>0</span>
          </button>
          <div class="dash-notif-dropdown" id="chat-notif-dropdown" hidden>
            <div class="dash-notif-dropdown-head">Client Messages</div>
            <div class="dash-notif-dropdown-list" id="chat-notif-list">
              <p class="dash-empty">No unread messages.</p>
            </div>
          </div>
        </div>
        <?php if ($statLowStockCount > 0): ?>
          <div class="dash-notif-wrap">
            <button type="button" class="dash-notif-chat" id="lowstock-topbar-bell" title="<?php echo (int) $statLowStockCount; ?> product(s) low on stock">
              <svg viewBox="0 0 24 24" fill="none"><path d="M12 4a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15.5h14l-1.5-2A2 2 0 0117 12.2V9a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 005 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
              <span class="dash-notif-badge"><?php echo (int) $statLowStockCount; ?></span>
            </button>
            <div class="dash-notif-dropdown" id="lowstock-notif-dropdown" hidden>
              <div class="dash-notif-dropdown-head">Low Stock</div>
              <div class="dash-notif-dropdown-list">
                <?php foreach ($products as $lowStockProductId => $lowStockProduct): if ((int) $lowStockProduct['stock'] > 5) continue; ?>
                  <button type="button" class="dash-notif-item" data-lowstock-item>
                    <span><?php echo htmlspecialchars($lowStockProduct['name']); ?></span>
                    <span class="dash-notif-item-count <?php echo $lowStockProduct['stock'] < 1 ? 'is-zero' : ''; ?>"><?php echo $lowStockProduct['stock'] < 1 ? 'No stock' : (int) $lowStockProduct['stock'] . ' left'; ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <div class="dash-user"><span class="dash-avatar">A</span> Admin</div>
      </div>
    </header>

    <div class="dash-content">
      <?php if ($message): ?><div class="dash-message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

      <section class="dash-section" data-panel="overview">
        <div class="dash-cards">
          <div class="dash-card">
            <div class="dash-card-label">Total Revenue</div>
            <div class="dash-card-value">₱<?php echo number_format($statTotalRevenue); ?></div>
            <div class="dash-card-sub"><?php echo (int) $statOrderCount; ?> total orders</div>
          </div>
          <div class="dash-card">
            <div class="dash-card-label">Delivered Revenue</div>
            <div class="dash-card-value">₱<?php echo number_format($statDeliveredRevenue); ?></div>
            <div class="dash-card-sub"><?php echo (int) $statStatusCounts['delivered']; ?> delivered</div>
          </div>
          <div class="dash-card">
            <div class="dash-card-label">Total Stock</div>
            <div class="dash-card-value"><?php echo (int) $statTotalStock; ?></div>
            <div class="dash-card-sub"><?php echo (int) $statProductCount; ?> products</div>
          </div>
          <div class="dash-card dash-card-accent">
            <div class="dash-card-label">Low Stock Alert</div>
            <div class="dash-card-value"><?php echo (int) $statLowStockCount; ?></div>
            <div class="dash-card-sub"><?php echo (int) $statUserCount; ?> registered customers</div>
          </div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head">
            <div class="dash-panel-title">Store Sales Trend — <?php echo date('Y'); ?></div>
          </div>
          <div class="dash-chart-row">
            <div class="dash-chart">
              <?php foreach ($monthNames as $index => $monthName): $revenueValue = $monthlyRevenue[$index + 1]; $barHeight = $revenueValue > 0 ? max(6, round(($revenueValue / $maxMonthlyRevenue) * 100)) : 2; ?>
                <div class="dash-chart-col">
                  <div class="dash-chart-bar" style="height: <?php echo $barHeight; ?>%;" title="₱<?php echo number_format($revenueValue); ?>"></div>
                  <span class="dash-chart-label"><?php echo $monthName; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="dash-ranking">
              <div class="dash-ranking-title">Top Products</div>
              <?php if (!$topProducts): ?>
                <p class="dash-empty">No sales yet.</p>
              <?php else: ?>
                <?php foreach ($topProducts as $rank => $product): ?>
                  <div class="dash-ranking-row">
                    <span class="dash-rank-badge"><?php echo $rank + 1; ?></span>
                    <span class="dash-rank-name"><?php echo htmlspecialchars($product['product_name']); ?></span>
                    <span class="dash-rank-value">₱<?php echo number_format($product['revenue']); ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head">
            <div class="dash-panel-title">Orders by Status</div>
          </div>
          <div class="dash-status-row">
            <div class="dash-status-chip status-processing">Processing<span><?php echo (int) $statStatusCounts['processing']; ?></span></div>
            <div class="dash-status-chip status-delivered">Delivered<span><?php echo (int) $statStatusCounts['delivered']; ?></span></div>
            <div class="dash-status-chip status-failed">Failed<span><?php echo (int) $statStatusCounts['failed']; ?></span></div>
          </div>
        </div>
      </section>

      <section class="dash-section" data-panel="products" hidden>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Add Product</div></div>
          <form class="dash-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">
            <div class="dash-form-row">
              <label>Product Name<input type="text" name="name" required></label>
              <label>Price<input type="number" name="price" min="0" required></label>
              <label>Stock<input type="number" name="stock" min="0" required></label>
            </div>
            <label>Product Image<input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required></label>
            <button class="dash-btn-primary" type="submit">Add Product</button>
          </form>
        </div>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Inventory</div></div>
          <div class="dash-table">
            <?php foreach ($products as $productId => $product): ?>
              <div class="dash-table-row">
                <div class="dash-table-main"><strong><?php echo htmlspecialchars($product['name']); ?></strong><span>₱<?php echo number_format($product['price']); ?></span></div>
                <form class="dash-inline-form" method="post">
                  <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>">
                  <input type="number" name="stock" min="0" value="<?php echo (int) $product['stock']; ?>" required>
                  
                </form>
                <form class="dash-inline-form" method="post">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>">
                  <button class="dash-btn-danger" type="submit" onclick="return confirm('Delete this product?');">Delete</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="dash-section" data-panel="stock" hidden>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Add Stock to a Product</div></div>
          <form class="dash-form" method="post">
            <input type="hidden" name="action" value="quick_add_stock">
            <div class="dash-form-row">
              <label>Product
                <select name="quick_product_id" required>
                  <option value="" disabled selected>Choose a product…</option>
                  <?php foreach ($products as $productId => $product): ?>
                    <option value="<?php echo htmlspecialchars($productId); ?>"><?php echo htmlspecialchars($product['name']); ?> (current: <?php echo (int) $product['stock']; ?>)</option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Quantity to Add<input type="number" name="add_amount" min="1" required></label>
            </div>
            <button class="dash-btn-primary" type="submit">Add Stock</button>
          </form>
        </div>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Current Stock Levels</div></div>
          <div class="dash-table">
            <?php foreach ($products as $product): ?>
              <div class="dash-table-row dash-table-row-simple">
                <span><?php echo htmlspecialchars($product['name']); ?></span>
                <span class="dash-stock-value<?php echo (int) $product['stock'] <= 5 ? ' dash-stock-low' : ''; ?>"><?php echo (int) $product['stock']; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="dash-section" data-panel="orders" hidden>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Orders</div></div>
          <?php if (!$orders): ?>
            <p class="dash-empty">No orders yet.</p>
          <?php else: ?>
            <div class="dash-table">
              <?php foreach ($orders as $orderId => $order): $status = $order['delivery_status'] ?: 'processing'; $orderedAt = $order['created_at'] ? date('M j, Y g:i A', strtotime($order['created_at'])) : '—'; $estimatedDeliveryValue = $order['estimated_delivery'] ? date('Y-m-d\TH:i', strtotime($order['estimated_delivery'])) : ''; $estimatedDeliveryDisplay = $order['estimated_delivery'] ? date('M j, Y g:i A', strtotime($order['estimated_delivery'])) : 'Not set'; ?>
                <div class="dash-order-row">
                  <div>
                    <strong>Order #<?php echo (int) $orderId; ?></strong>
                    <span><?php echo htmlspecialchars($order['buyer_name']); ?> · <?php echo htmlspecialchars($order['buyer_email']); ?></span>
                    <span><?php echo htmlspecialchars(implode(', ', $order['items'])); ?></span>
                    <span class="dash-order-time">Ordered: <?php echo htmlspecialchars($orderedAt); ?></span>
                  </div>
                  <div>
                    <strong>₱<?php echo number_format($order['total']); ?></strong>
                    <span><?php echo htmlspecialchars($order['address'] . ', ' . $order['location'] . ' ' . $order['zip']); ?></span>
                    <?php if (!empty($order['phone'])): ?><span>Phone: <?php echo htmlspecialchars($order['phone']); ?></span><?php endif; ?>
                    <span>Payment: <?php echo htmlspecialchars($paymentMethodLabels[$order['payment_method'] ?? 'cod'] ?? 'Cash on Delivery'); ?></span>
                    <?php if ($order['comments'] !== ''): ?><span>Note: <?php echo htmlspecialchars($order['comments']); ?></span><?php endif; ?>
                  </div>
                  <div class="dash-order-status-panel">
                    <span class="dash-status-chip status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($deliveryStatuses[$status] ?? ucfirst($status)); ?></span>
                    <span class="dash-order-time">Expected: <?php echo htmlspecialchars($estimatedDeliveryDisplay); ?></span>
                    <form class="dash-inline-form" method="post">
                      <input type="hidden" name="action" value="update_delivery_status">
                      <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                      <select name="delivery_status">
                        <?php foreach ($deliveryStatuses as $value => $label): ?>
                          <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="datetime-local" name="estimated_delivery" value="<?php echo htmlspecialchars($estimatedDeliveryValue); ?>">
                      <button class="dash-btn-secondary" type="submit">Update</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="dash-section" data-panel="history" hidden>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Recent Stock Changes</div></div>
          <?php if (!$stockHistory): ?>
            <p class="dash-empty">No stock changes recorded yet.</p>
          <?php else: ?>
            <div class="dash-table">
              <?php foreach ($stockHistory as $entry): $isIncrease = $entry['change_amount'] >= 0; ?>
                <div class="dash-table-row dash-history-row">
                  <div>
                    <strong><?php echo htmlspecialchars($entry['product_name']); ?></strong>
                    <span class="dash-order-time"><?php echo htmlspecialchars($stockHistoryReasonLabels[$entry['reason']] ?? ucfirst($entry['reason'])); ?> · <?php echo htmlspecialchars(date('M j, g:i A', strtotime($entry['created_at']))); ?></span>
                  </div>
                  <span class="<?php echo $isIncrease ? 'dash-history-up' : 'dash-history-down'; ?>">
                    <?php echo (int) $entry['previous_stock']; ?> → <?php echo (int) $entry['new_stock']; ?>
                    (<?php echo $isIncrease ? '+' : ''; ?><?php echo (int) $entry['change_amount']; ?>)
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="dash-section" data-panel="customers" hidden>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Customers</div></div>
          <div class="dash-table">
            <?php $visibleUsers = array_filter($users, fn($u) => $u['email'] !== 'admin@masalihit.local'); ?>
            <?php if (!$visibleUsers): ?><p class="dash-empty">No customers yet.</p><?php endif; ?>
            <?php foreach ($visibleUsers as $user): ?>
              <?php $userStatus = $user['status'] ?? 'active'; ?>
              <div class="dash-table-row dash-table-row-simple dash-customer-row">
                <div>
                  <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                  <span class="dash-order-time">
                    <?php echo htmlspecialchars($user['email']); ?> ·
                    <span class="dash-mini-badge status-<?php echo $user['role'] === 'admin' ? 'delivered' : 'processing'; ?>"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                    <span class="dash-mini-badge status-<?php echo $userStatus === 'banned' ? 'failed' : 'delivered'; ?>"><?php echo $userStatus === 'banned' ? 'Banned' : 'Active'; ?></span>
                  </span>
                  <span><?php echo htmlspecialchars(trim($user['address'] . ', ' . $user['location'] . ' ' . $user['zip'], ', ')); ?></span>
                </div>
                <div class="dash-customer-actions">
                  <form method="post" onsubmit="return confirm(<?php echo $user['role'] === 'admin' ? "'Remove admin access from " . addslashes(htmlspecialchars($user['name'])) . "?'" : "'Make " . addslashes(htmlspecialchars($user['name'])) . " an admin?'"; ?>);">
                    <input type="hidden" name="action" value="<?php echo $user['role'] === 'admin' ? 'demote_admin' : 'promote_admin'; ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button class="dash-btn-secondary" type="submit"><?php echo $user['role'] === 'admin' ? 'Remove Admin' : 'Make Admin'; ?></button>
                  </form>
                  <form method="post" onsubmit="return confirm(<?php echo $userStatus === 'banned' ? "'Unban " . addslashes(htmlspecialchars($user['name'])) . "?'" : "'Ban " . addslashes(htmlspecialchars($user['name'])) . "? They won\\'t be able to log in.'"; ?>);">
                    <input type="hidden" name="action" value="<?php echo $userStatus === 'banned' ? 'unban_user' : 'ban_user'; ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button class="dash-btn-secondary" type="submit"><?php echo $userStatus === 'banned' ? 'Unban' : 'Ban'; ?></button>
                  </form>
                  <form method="post" onsubmit="return confirm('Permanently delete <?php echo addslashes(htmlspecialchars($user['name'])); ?>? This can\'t be undone.');">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button class="dash-btn-danger" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="dash-section" data-panel="messages" hidden>
        <div class="dash-panel-head"><div class="dash-panel-title">Client Messages</div></div>
        <div class="dash-chat-shell">
          <div class="dash-chat-threads" id="chat-thread-list">
            <p class="dash-empty">Loading conversations…</p>
          </div>
          <div class="dash-chat-panel">
            <div class="dash-chat-panel-head" id="chat-panel-head">Select a conversation</div>
            <div class="dash-chat-log" id="chat-log">
              <div class="dash-chat-empty">Pick a client on the left to view the conversation.</div>
            </div>
            <form class="dash-chat-reply" id="chat-reply-form" hidden>
              <input type="text" id="chat-reply-input" placeholder="Type a reply…" autocomplete="off" required>
              <button class="dash-btn-primary" type="submit">Send</button>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<script>
(function () {
  // Number inputs (stock, price, quantity-to-add) shouldn't change value just
  // because the admin scrolled the page while the cursor happened to be over
  // one. Blur the field on wheel so scrolling passes through to the page.
  document.addEventListener('wheel', function (event) {
    var active = document.activeElement;
    if (active && active.tagName === 'INPUT' && active.type === 'number') {
      active.blur();
    }
  }, { passive: true });

  var navButtons = document.querySelectorAll('.dash-nav-item');
  var sections = document.querySelectorAll('.dash-section');
  navButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      navButtons.forEach(function (b) { b.classList.remove('active'); });
      sections.forEach(function (s) { s.hidden = true; });
      btn.classList.add('active');
      var section = document.querySelector('.dash-section[data-panel="' + btn.dataset.section + '"]');
      if (section) section.hidden = false;
    });
  });

  /* ---------- Live chat inbox ---------- */
  var threadListEl = document.getElementById('chat-thread-list');
  var chatLogEl = document.getElementById('chat-log');
  var chatPanelHead = document.getElementById('chat-panel-head');
  var replyForm = document.getElementById('chat-reply-form');
  var replyInput = document.getElementById('chat-reply-input');
  var navBadge = document.getElementById('chat-nav-badge');
  var topbarBell = document.getElementById('chat-topbar-bell');
  var topbarBadge = document.getElementById('chat-topbar-badge');
  var activeThreadId = null;
  var threadsCache = [];

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function renderThreadList() {
    if (!threadsCache.length) {
      threadListEl.innerHTML = '<p class="dash-empty">No conversations yet.</p>';
      return;
    }
    threadListEl.innerHTML = threadsCache.map(function (thread) {
      var activeClass = thread.id === activeThreadId ? ' active' : '';
      var unreadBadge = thread.unread > 0 ? '<span class="dash-chat-unread-dot">' + thread.unread + '</span>' : '';
      var preview = thread.lastMessage ? escapeHtml(thread.lastMessage) : 'No messages yet';
      return '<button type="button" class="dash-chat-thread' + activeClass + '" data-thread-id="' + thread.id + '">' +
        '<div class="dash-chat-thread-top"><span class="dash-chat-thread-name">' + escapeHtml(thread.name) + '</span>' + unreadBadge + '</div>' +
        '<span class="dash-chat-thread-preview">' + preview + '</span>' +
        '<span class="dash-chat-thread-time">' + thread.time + '</span></button>';
    }).join('');

    threadListEl.querySelectorAll('.dash-chat-thread').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openThread(parseInt(btn.dataset.threadId, 10));
      });
    });
  }

  function refreshThreadList() {
    fetch('chat.php?action=admin_threads')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) return;
        threadsCache = data.threads;
        renderThreadList();
        renderChatNotifDropdown();
      })
      .catch(function () {});
  }

  function refreshUnreadBadge() {
    fetch('chat.php?action=admin_unread_total')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) return;
        var count = data.unreadThreads;
        if (count > 0) {
          navBadge.textContent = count;
          navBadge.hidden = false;
          topbarBadge.textContent = count;
          topbarBadge.hidden = false;
        } else {
          navBadge.hidden = true;
          topbarBadge.hidden = true;
        }
      })
      .catch(function () {});
  }

  function renderChatNotifDropdown() {
    var listEl = document.getElementById('chat-notif-list');
    if (!listEl) return;
    var unread = threadsCache.filter(function (t) { return t.unread > 0; });
    if (!unread.length) {
      listEl.innerHTML = '<p class="dash-empty">No unread messages.</p>';
      return;
    }
    listEl.innerHTML = unread.map(function (thread) {
      var preview = thread.lastMessage ? escapeHtml(thread.lastMessage) : 'No messages yet';
      return '<button type="button" class="dash-notif-item" data-thread-id="' + thread.id + '">' +
        '<span>' + escapeHtml(thread.name) + '</span>' +
        '<span class="dash-notif-item-count">' + preview + '</span></button>';
    }).join('');
    listEl.querySelectorAll('[data-thread-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeAllNotifDropdowns();
        var messagesNav = document.querySelector('.dash-nav-item[data-section="messages"]');
        if (messagesNav) messagesNav.click();
        openThread(parseInt(btn.dataset.threadId, 10));
      });
    });
  }

  function closeAllNotifDropdowns() {
    document.querySelectorAll('.dash-notif-dropdown').forEach(function (dropdown) {
      dropdown.hidden = true;
    });
  }

  function toggleNotifDropdown(dropdown) {
    var wasHidden = dropdown.hidden;
    closeAllNotifDropdowns();
    dropdown.hidden = !wasHidden;
  }

  function renderMessages(messages) {
    if (!messages.length) {
      chatLogEl.innerHTML = '<div class="dash-chat-empty">No messages in this conversation yet.</div>';
      return;
    }
    chatLogEl.innerHTML = messages.map(function (m) {
      var cls = m.sender === 'admin' ? 'from-admin' : 'from-client';
      return '<div class="dash-chat-bubble ' + cls + '">' + escapeHtml(m.message) +
        '<span class="dash-chat-bubble-time">' + m.time + '</span></div>';
    }).join('');
    chatLogEl.scrollTop = chatLogEl.scrollHeight;
  }

  function openThread(threadId) {
    activeThreadId = threadId;
    renderThreadList();
    var thread = threadsCache.find(function (t) { return t.id === threadId; });
    chatPanelHead.textContent = thread ? thread.name : 'Conversation';
    replyForm.hidden = false;
    fetch('chat.php?action=admin_thread_messages&thread_id=' + threadId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) return;
        renderMessages(data.messages);
        refreshUnreadBadge();
        refreshThreadList();
      })
      .catch(function () {});
  }

  replyForm.addEventListener('submit', function (event) {
    event.preventDefault();
    var message = replyInput.value.trim();
    if (!message || !activeThreadId) return;
    var formData = new FormData();
    formData.set('action', 'admin_reply');
    formData.set('thread_id', activeThreadId);
    formData.set('message', message);
    replyInput.value = '';
    fetch('chat.php', { method: 'POST', body: formData })
      .then(function (r) { return r.json(); })
      .then(function () {
        openThread(activeThreadId);
      })
      .catch(function () {});
  });

  topbarBell.addEventListener('click', function (event) {
    event.stopPropagation();
    var dropdown = document.getElementById('chat-notif-dropdown');
    if (dropdown) toggleNotifDropdown(dropdown);
  });

  var lowStockBell = document.getElementById('lowstock-topbar-bell');
  if (lowStockBell) {
    lowStockBell.addEventListener('click', function (event) {
      event.stopPropagation();
      var dropdown = document.getElementById('lowstock-notif-dropdown');
      if (dropdown) toggleNotifDropdown(dropdown);
    });
  }

  document.querySelectorAll('[data-lowstock-item]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeAllNotifDropdowns();
      var productsNav = document.querySelector('.dash-nav-item[data-section="products"]');
      if (productsNav) productsNav.click();
    });
  });

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.dash-notif-wrap')) {
      closeAllNotifDropdowns();
    }
  });

  refreshThreadList();
  refreshUnreadBadge();
  setInterval(function () {
    refreshThreadList();
    refreshUnreadBadge();
    if (activeThreadId && document.querySelector('.dash-section[data-panel="messages"]') && !document.querySelector('.dash-section[data-panel="messages"]').hidden) {
      fetch('chat.php?action=admin_thread_messages&thread_id=' + activeThreadId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.success) renderMessages(data.messages);
        })
        .catch(function () {});
    }
  }, 6000);
})();
</script>
</body>
</html>
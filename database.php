<?php
// Flat shipping fee (in pesos) added to every order at checkout.
define('SHIPPING_FEE', 150);

function getDatabase(): PDO
{
  static $database;
  if ($database instanceof PDO) {
    return $database;
  }

  $host = getenv('DB_HOST') ?: '127.0.0.1';
  $username = getenv('DB_USER') ?: 'root';
  $password = getenv('DB_PASS') ?: '';
  $databaseName = 'masalihitluxe';
  $server = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
  $server->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $server->exec("CREATE DATABASE IF NOT EXISTS `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $database = new PDO("mysql:host=$host;dbname=$databaseName;charset=utf8mb4", $username, $password);
  $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $database->exec("CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'client',
    address VARCHAR(255) NOT NULL DEFAULT '',
    location VARCHAR(255) NOT NULL DEFAULT '',
    zip VARCHAR(20) NOT NULL DEFAULT '',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    comments VARCHAR(1000) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $database->exec("ALTER TABLE users MODIFY comments VARCHAR(1000) NOT NULL DEFAULT ''");
  $usersPhoneColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone'");
  $usersPhoneColumn->execute([$databaseName]);
  if (!$usersPhoneColumn->fetchColumn()) {
    $database->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER zip");
  }
  $usersStatusColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'");
  $usersStatusColumn->execute([$databaseName]);
  if (!$usersStatusColumn->fetchColumn()) {
    $database->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role");
  }
  $usersAvatarColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar'");
  $usersAvatarColumn->execute([$databaseName]);
  if (!$usersAvatarColumn->fetchColumn()) {
    $database->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NOT NULL DEFAULT '' AFTER phone");
  }
  $adminStatement = $database->prepare('SELECT id FROM users WHERE email = ?');
  $adminStatement->execute(['admin@masalihit.local']);
  if (!$adminStatement->fetchColumn()) {
    $adminStatement = $database->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
    $adminStatement->execute(['Administrator', 'admin@masalihit.local', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
  } else {
    $database->prepare('UPDATE users SET role = ? WHERE email = ?')->execute(['admin', 'admin@masalihit.local']);
  }
  $database->exec("CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    total INT NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    location VARCHAR(255) NOT NULL DEFAULT '',
    zip VARCHAR(20) NOT NULL DEFAULT '',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    comments VARCHAR(1000) NOT NULL DEFAULT '',
    delivery_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    estimated_delivery DATETIME NULL DEFAULT NULL,
    receipt_number VARCHAR(64) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
  ) ENGINE=InnoDB");
  $database->exec("ALTER TABLE orders MODIFY comments VARCHAR(1000) NOT NULL DEFAULT ''");
  $ordersPhoneColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'phone'");
  $ordersPhoneColumn->execute([$databaseName]);
  if (!$ordersPhoneColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER zip");
  }
  $ordersPaymentMethodColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_method'");
  $ordersPaymentMethodColumn->execute([$databaseName]);
  if (!$ordersPaymentMethodColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cod' AFTER phone");
  }
  // Stores the flat shipping fee actually charged on this order, so past
  // receipts stay accurate even if SHIPPING_FEE changes later.
  $ordersShippingFeeColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_fee'");
  $ordersShippingFeeColumn->execute([$databaseName]);
  if (!$ordersShippingFeeColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN shipping_fee INT NOT NULL DEFAULT 0 AFTER total");
  }
  $ordersStatusSeenColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status_seen'");
  $ordersStatusSeenColumn->execute([$databaseName]);
  if (!$ordersStatusSeenColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN status_seen TINYINT(1) NOT NULL DEFAULT 1 AFTER delivery_status");
  }
  $ordersDeliveryStatusColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_status'");
  $ordersDeliveryStatusColumn->execute([$databaseName]);
  if (!$ordersDeliveryStatusColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN delivery_status VARCHAR(20) NOT NULL DEFAULT 'processing' AFTER comments");
  }
  $ordersEstimatedDeliveryColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'estimated_delivery'");
  $ordersEstimatedDeliveryColumn->execute([$databaseName]);
  if (!$ordersEstimatedDeliveryColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN estimated_delivery DATETIME NULL DEFAULT NULL AFTER delivery_status");
  }
  $ordersReceiptNumberColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'receipt_number'");
  $ordersReceiptNumberColumn->execute([$databaseName]);
  if (!$ordersReceiptNumberColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN receipt_number VARCHAR(64) NULL DEFAULT NULL AFTER estimated_delivery");
  }
  // Refund request tracking: a delivered order can be 'none' (default),
  // 'requested' (customer asked, awaiting admin review), 'approved' (admin
  // refunded it — stock was restored and it's excluded from revenue), or
  // 'denied' (admin rejected it; the customer may request again).
  $ordersRefundStatusColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'refund_status'");
  $ordersRefundStatusColumn->execute([$databaseName]);
  if (!$ordersRefundStatusColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN refund_status VARCHAR(20) NOT NULL DEFAULT 'none' AFTER receipt_number");
  }
  $ordersRefundReasonColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'refund_reason'");
  $ordersRefundReasonColumn->execute([$databaseName]);
  if (!$ordersRefundReasonColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN refund_reason VARCHAR(500) NOT NULL DEFAULT '' AFTER refund_status");
  }
  $ordersRefundRequestedAtColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'refund_requested_at'");
  $ordersRefundRequestedAtColumn->execute([$databaseName]);
  if (!$ordersRefundRequestedAtColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN refund_requested_at DATETIME NULL DEFAULT NULL AFTER refund_reason");
  }
  $ordersRefundedAtColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'refunded_at'");
  $ordersRefundedAtColumn->execute([$databaseName]);
  if (!$ordersRefundedAtColumn->fetchColumn()) {
    $database->exec("ALTER TABLE orders ADD COLUMN refunded_at DATETIME NULL DEFAULT NULL AFTER refund_requested_at");
  }
  $database->exec("CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
  ) ENGINE=InnoDB");
  $database->exec("CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255) NOT NULL
  ) ENGINE=InnoDB");
  if ((int) $database->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
    $productSeed = json_decode(file_get_contents(__DIR__ . '/products.json'), true) ?: [];
    $productStatement = $database->prepare('INSERT INTO products (id, name, price, stock, image) VALUES (?, ?, ?, ?, ?)');
    foreach ($productSeed as $productId => $product) {
      $productStatement->execute([$productId, $product['name'], $product['price'], $product['stock'], $product['image']]);
    }
  }
  $database->exec("CREATE TABLE IF NOT EXISTS stock_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    change_amount INT NOT NULL,
    reason VARCHAR(30) NOT NULL DEFAULT 'manual',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  // Link to products so the relationship shows up in ER diagrams, but as a
  // nullable FK with ON DELETE SET NULL: deleting a product must never fail
  // or wipe out its stock history — the row should just detach and keep
  // showing the product_name snapshot already stored alongside it.
  $database->exec("ALTER TABLE stock_history MODIFY product_id VARCHAR(100) NULL");
  $database->exec("UPDATE stock_history sh LEFT JOIN products p ON p.id = sh.product_id SET sh.product_id = NULL WHERE sh.product_id IS NOT NULL AND p.id IS NULL");
  $stockHistoryProductFk = $database->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'stock_history' AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products'");
  $stockHistoryProductFk->execute([$databaseName]);
  if (!$stockHistoryProductFk->fetchColumn()) {
    $database->exec("ALTER TABLE stock_history ADD CONSTRAINT fk_stock_history_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL");
  }

  $database->exec("CREATE TABLE IF NOT EXISTS chat_threads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    guest_token VARCHAR(64) NULL,
    guest_name VARCHAR(255) NULL,
    last_message_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    UNIQUE KEY unique_guest_token (guest_token),
    FOREIGN KEY (user_id) REFERENCES users(id)
  ) ENGINE=InnoDB");

  $database->exec("CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    sender VARCHAR(10) NOT NULL,
    message VARCHAR(2000) NOT NULL,
    read_by_admin TINYINT(1) NOT NULL DEFAULT 0,
    read_by_client TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id)
  ) ENGINE=InnoDB");

  $database->exec("CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment VARCHAR(1000) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
  ) ENGINE=InnoDB");

  // The product_id FK above was created with no ON DELETE rule, so MySQL
  // defaults to RESTRICT: deleting a product with any reviews throws a
  // 1451 integrity constraint error. Swap it for ON DELETE CASCADE so
  // removing a product cleans up its reviews instead of blocking.
  $reviewsProductFk = $database->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products'");
  $reviewsProductFk->execute([$databaseName]);
  $reviewsProductFkName = $reviewsProductFk->fetchColumn();
  if ($reviewsProductFkName) {
    $reviewsProductFkOnDelete = $database->prepare("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'reviews' AND CONSTRAINT_NAME = ?");
    $reviewsProductFkOnDelete->execute([$databaseName, $reviewsProductFkName]);
    if ($reviewsProductFkOnDelete->fetchColumn() !== 'CASCADE') {
      $database->exec("ALTER TABLE reviews DROP FOREIGN KEY `$reviewsProductFkName`");
      $database->exec("ALTER TABLE reviews ADD CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE");
    }
  }

  // Lets admins pick specific reviews to show in the homepage testimonials
  // section instead of the old hardcoded four.
  $reviewsFeaturedColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'is_featured'");
  $reviewsFeaturedColumn->execute([$databaseName]);
  if (!$reviewsFeaturedColumn->fetchColumn()) {
    $database->exec("ALTER TABLE reviews ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER comment");
  }

  $database->exec("CREATE TABLE IF NOT EXISTS discount_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    percent TINYINT UNSIGNED NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_by_order_id INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (used_by_order_id) REFERENCES orders(id)
  ) ENGINE=InnoDB");
  $discountMaxUsesColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'discount_codes' AND COLUMN_NAME = 'max_uses'");
  $discountMaxUsesColumn->execute([$databaseName]);
  if (!$discountMaxUsesColumn->fetchColumn()) {
    $database->exec("ALTER TABLE discount_codes ADD COLUMN max_uses SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER percent");
  }
  $discountUsedCountColumn = $database->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'discount_codes' AND COLUMN_NAME = 'used_count'");
  $discountUsedCountColumn->execute([$databaseName]);
  if (!$discountUsedCountColumn->fetchColumn()) {
    $database->exec("ALTER TABLE discount_codes ADD COLUMN used_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_uses");
    // Backfill: any code already marked used under the old one-time-use model
    // counts as 1 of 1 so existing data stays consistent with the new counters.
    $database->exec("UPDATE discount_codes SET used_count = 1 WHERE is_used = 1 AND used_count = 0");
  }

  $database->exec("CREATE TABLE IF NOT EXISTS discount_code_uses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_id) REFERENCES discount_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id)
  ) ENGINE=InnoDB");
  // Backfill: carry any pre-existing single-use record into the new use log
  // so "Used by ..." history isn't lost for codes redeemed before this change.
  $database->exec("INSERT INTO discount_code_uses (code_id, order_id, used_at)
    SELECT dc.id, dc.used_by_order_id, COALESCE(dc.used_at, dc.created_at)
    FROM discount_codes dc
    WHERE dc.used_by_order_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM discount_code_uses dcu WHERE dcu.code_id = dc.id AND dcu.order_id = dc.used_by_order_id)");

  return $database;
}
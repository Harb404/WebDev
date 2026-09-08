<?php
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
    delivery_status VARCHAR(20) NOT NULL DEFAULT 'processing',
    estimated_delivery DATETIME NULL DEFAULT NULL,
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

  return $database;
}
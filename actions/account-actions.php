<?php
/**
 * Handles every form post on account.php: register, update_profile,
 * login, apply_discount, remove_discount, buy (checkout), and
 * submit_review. Included after includes/account-setup.php has set
 * up $products, $cart, $profile, and the form-default variables.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';
  $address = trim($_POST['address'] ?? $address);
  $location = trim($_POST['location'] ?? $location);
  $zip = trim($_POST['zip'] ?? $zip);
  $phone = trim($_POST['phone'] ?? $phone);
  $comments = trim($_POST['comments'] ?? $comments);
  $paymentMethod = $_POST['payment_method'] ?? 'cod';
  if (!in_array($paymentMethod, ['cod', 'ewallet'], true)) {
    $paymentMethod = 'cod';
  }
  $ewalletProvider = $_POST['ewallet_provider'] ?? 'gcash';
  if (!in_array($ewalletProvider, ['gcash', 'paypal', 'gotyme', 'debit'], true)) {
    $ewalletProvider = 'gcash';
  }
  // Only meaningful when paying by e-wallet — keep it out of the order row otherwise.
  $ewalletProviderToSave = $paymentMethod === 'ewallet' ? $ewalletProvider : null;
  $discountCodeInput = strtoupper(trim($_POST['discount_code'] ?? ''));

  if ($action === 'register') {
    $error = validateRegistrationFields($name, $email, $password, $address, $location, $zip, $phone);
    if ($error === '') {
      try {
        $statement = $database->prepare('INSERT INTO users (name, email, password, address, location, zip, phone) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $address, $location, $zip, $phone]);
        // A brand-new customer account should never inherit an admin flag left
        // over from an earlier admin session in the same browser/tab.
        unset($_SESSION['is_admin']);
        $_SESSION['user_id'] = (int) $database->lastInsertId();
        $_SESSION['user_name'] = $name;
        header('Location: account.php?checkout=1');
        exit;
      } catch (PDOException $exception) {
        $error = 'That email is already registered.';
      }
    }
  }

  if ($action === 'update_profile') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in to update your account.';
    } else {
      $error = $name === '' ? 'Please enter your name.' : validateDeliveryFields($address, $location, $zip, $phone);
      if ($error === '') {
        $statement = $database->prepare('UPDATE users SET name = ?, address = ?, location = ?, zip = ?, phone = ? WHERE id = ?');
        $statement->execute([$name, $address, $location, $zip, $phone, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        $profile['name'] = $name;
        $profile['address'] = $address;
        $profile['location'] = $location;
        $profile['zip'] = $zip;
        $profile['phone'] = $phone;
        $message = 'Account details updated successfully.';
      }
    }
  }

  if ($action === 'login') {
    $statement = $database->prepare('SELECT id, name, password, status FROM users WHERE email = ?');
    $statement->execute([$email]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
      if (($user['status'] ?? 'active') === 'banned') {
        $error = 'This account has been suspended. Contact support if you think this is a mistake.';
      } else {
        unset($_SESSION['is_admin']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header('Location: account.php' . ($isCheckout ? '?checkout=1' : ''));
        exit;
      }
    } else {
      $error = 'Incorrect email or password.';
    }
  }

  if ($action === 'apply_discount') {
    if (empty($_SESSION['user_id'])) {
      $discountError = 'Please log in to apply a discount code.';
    } elseif ($discountCodeInput === '') {
      $discountError = 'Please enter a discount code.';
    } else {
      $codeStatement = $database->prepare('SELECT id, code, percent, max_uses, used_count FROM discount_codes WHERE code = ?');
      $codeStatement->execute([$discountCodeInput]);
      $codeRow = $codeStatement->fetch(PDO::FETCH_ASSOC);
      if (!$codeRow) {
        $discountError = 'That discount code was not found.';
      } elseif ((int) $codeRow['used_count'] >= (int) $codeRow['max_uses']) {
        $discountError = 'That discount code has already reached its usage limit.';
      } else {
        $_SESSION['applied_discount_code'] = $codeRow['code'];
        // No success message here — the "Code X applied" chip in checkout.php
        // already confirms this, so setting one too would show the same
        // confirmation twice on the page.
      }
    }
  }

  if ($action === 'remove_discount') {
    unset($_SESSION['applied_discount_code']);
    $discountMessage = 'Discount code removed.';
  }

  if ($action === 'buy') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in or create an account before buying.';
    } elseif (!$cart) {
      $error = 'Your cart is empty.';
    } else {
      try {
        $database->beginTransaction();
        $subtotal = 0;
        foreach ($cart as $productId => $quantity) {
          if (!isset($products[$productId]) || $quantity < 1 || $quantity > $products[$productId]['stock']) {
            throw new RuntimeException('One item does not have enough stock.');
          }
          $subtotal += $products[$productId]['price'] * $quantity;
        }

        // Re-validate the applied discount code inside the transaction (with a
        // row lock) so two near-simultaneous checkouts can't both redeem it.
        $appliedCodeRow = null;
        $sessionDiscountCode = $_SESSION['applied_discount_code'] ?? null;
        $total = $subtotal;
        $discountSavings = 0;
        if ($sessionDiscountCode) {
          $codeLockStatement = $database->prepare('SELECT id, code, percent, max_uses, used_count FROM discount_codes WHERE code = ? FOR UPDATE');
          $codeLockStatement->execute([$sessionDiscountCode]);
          $lockedCodeRow = $codeLockStatement->fetch(PDO::FETCH_ASSOC);
          if ($lockedCodeRow && (int) $lockedCodeRow['used_count'] < (int) $lockedCodeRow['max_uses']) {
            $appliedCodeRow = $lockedCodeRow;
            $total = (int) round($subtotal * (100 - (int) $appliedCodeRow['percent']) / 100);
            $discountSavings = $subtotal - $total;
          } else {
            // Someone else used it (or it was deleted) between applying and placing the order.
            unset($_SESSION['applied_discount_code']);
          }
        }
        // Flat shipping fee is added after any discount, and stored on the
        // order itself so it stays accurate even if SHIPPING_FEE changes later.
        $total += SHIPPING_FEE;

        $deliveryError = validateDeliveryFields($address, $location, $zip, $phone);
        if ($deliveryError !== '') {
          throw new RuntimeException($deliveryError);
        }
        if ($name === '') {
          $name = $profile['name'] ?? $_SESSION['user_name'];
        }
        $profileStatement = $database->prepare('UPDATE users SET name = ?, address = ?, location = ?, zip = ?, phone = ?, comments = ? WHERE id = ?');
        $profileStatement->execute([$name, $address, $location, $zip, $phone, $comments, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        $order = $database->prepare('INSERT INTO orders (user_id, total, shipping_fee, address, location, zip, phone, payment_method, ewallet_provider, comments, delivery_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $order->execute([$_SESSION['user_id'], $total, SHIPPING_FEE, $address, $location, $zip, $phone, $paymentMethod, $ewalletProviderToSave, $comments, 'pending']);
        $orderId = $database->lastInsertId();
        $item = $database->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)');
        $stockStatement = $database->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        foreach ($cart as $productId => $quantity) {
          $product = $products[$productId];
          $item->execute([$orderId, $productId, $product['name'], $quantity, $product['price']]);
          $stockStatement->execute([$quantity, $productId, $quantity]);
          if ($stockStatement->rowCount() !== 1) {
            throw new RuntimeException('One item does not have enough stock.');
          }
        }

        if ($appliedCodeRow) {
          $database->prepare('UPDATE discount_codes SET used_count = used_count + 1, is_used = IF(used_count + 1 >= max_uses, 1, 0), used_at = CURRENT_TIMESTAMP, used_by_order_id = ? WHERE id = ?')
            ->execute([$orderId, $appliedCodeRow['id']]);
          $database->prepare('INSERT INTO discount_code_uses (code_id, order_id) VALUES (?, ?)')
            ->execute([$appliedCodeRow['id'], $orderId]);
        }

        $receiptNumber = generateReceiptNumber((int) $orderId);
        $database->prepare('UPDATE orders SET receipt_number = ? WHERE id = ?')->execute([$receiptNumber, $orderId]);
        $database->commit();
        unset($_SESSION['applied_discount_code']);
        $_SESSION['cart'] = [];
        $cart = [];

        $message = 'Order placed successfully! Your receipt number is ' . $receiptNumber . '.';
        if ($appliedCodeRow) {
          $message .= ' Discount code "' . $appliedCodeRow['code'] . '" (' . (int) $appliedCodeRow['percent'] . '% off) saved you ₱' . number_format($discountSavings) . '.';
        }
        $message .= ' A ₱' . number_format(SHIPPING_FEE) . ' shipping fee was included in the total.';
        $message .= ' You can track its status from your Purchase History below.';
      } catch (Throwable $exception) {
        if ($database->inTransaction()) {
          $database->rollBack();
        }
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The order could not be placed.';
      }
    }
  }

  if ($action === 'request_refund') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in to request a refund.';
    } else {
      $refundOrderId = (int) ($_POST['order_id'] ?? 0);
      $refundReason = trim($_POST['refund_reason'] ?? '');
      $refundOrderStatement = $database->prepare('SELECT delivery_status, refund_status FROM orders WHERE id = ? AND user_id = ?');
      $refundOrderStatement->execute([$refundOrderId, $_SESSION['user_id']]);
      $refundOrderRow = $refundOrderStatement->fetch(PDO::FETCH_ASSOC);
      if (!$refundOrderRow) {
        $error = 'Order not found.';
      } elseif ($refundOrderRow['delivery_status'] !== 'delivered') {
        $error = 'Only delivered orders can be refunded.';
      } elseif (in_array($refundOrderRow['refund_status'], ['requested', 'approved'], true)) {
        $error = 'A refund has already been requested for this order.';
      } elseif ($refundReason === '') {
        $error = 'Please tell us why you\'d like a refund.';
      } else {
        $statement = $database->prepare("UPDATE orders SET refund_status = 'requested', refund_reason = ?, refund_requested_at = CURRENT_TIMESTAMP, refunded_at = NULL WHERE id = ?");
        $statement->execute([substr($refundReason, 0, 500), $refundOrderId]);
        $message = 'Your refund request has been submitted. We\'ll review it shortly.';
      }
    }
  }

  if ($action === 'submit_review') {
    if (empty($_SESSION['user_id'])) {
      $error = 'Please log in to leave a review.';
    } else {
      $reviewProductId = $_POST['product_id'] ?? '';
      $reviewOrderId = (int) ($_POST['order_id'] ?? 0);
      $rating = (int) ($_POST['rating'] ?? 0);
      $comment = trim($_POST['comment'] ?? '');

      if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating from 1 to 5 stars.';
      } else {
        $ownsItemStatement = $database->prepare('SELECT orders.delivery_status FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE orders.user_id = ? AND orders.id = ? AND order_items.product_id = ?');
        $ownsItemStatement->execute([$_SESSION['user_id'], $reviewOrderId, $reviewProductId]);
        $ownedOrderStatus = $ownsItemStatement->fetchColumn();
        if ($ownedOrderStatus === false) {
          $error = 'You can only review items you\'ve purchased.';
        } elseif ($ownedOrderStatus !== 'delivered') {
          $error = 'You can review this item once your order has been delivered.';
        } else {
          $reviewStatement = $database->prepare('INSERT INTO reviews (user_id, product_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), order_id = VALUES(order_id), created_at = CURRENT_TIMESTAMP');
          $reviewStatement->execute([$_SESSION['user_id'], $reviewProductId, $reviewOrderId, $rating, substr($comment, 0, 1000)]);
          $message = 'Thanks for your feedback!';
        }
      }
    }
  }
}
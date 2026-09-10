<?php
session_start();
require_once __DIR__ . '/database.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not logged in.']);
  exit;
}

$database = getDatabase();
$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'poll';

$statusLabels = [
  'pending' => 'Pending',
  'processing' => 'Processing',
  'shipped' => 'Shipped',
  'delivered' => 'Delivered',
  'failed' => 'Failed',
];

/**
 * Returns the customer's most recent orders as notification entries, newest
 * first. `unread` mirrors the orders.status_seen flag, which the admin panel
 * resets to 0 whenever an order moves to Processing, Shipped, Delivered, or
 * Failed (see admin.php).
 */
function buildNotifications(PDO $database, int $userId, array $statusLabels): array
{
  $statement = $database->prepare('SELECT id, total, delivery_status, status_seen, receipt_number, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10');
  $statement->execute([$userId]);
  $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

  return array_map(function ($row) use ($statusLabels) {
    $status = $row['delivery_status'] ?: 'pending';
    return [
      'orderId' => (int) $row['id'],
      'status' => $status,
      'statusLabel' => $statusLabels[$status] ?? ucfirst($status),
      'unread' => (int) $row['status_seen'] === 0,
      'receiptNumber' => $row['receipt_number'],
      'total' => (int) $row['total'],
      'orderedAt' => $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : '',
    ];
  }, $rows);
}

if ($action === 'poll') {
  $notifications = buildNotifications($database, $userId, $statusLabels);
  $unreadCount = count(array_filter($notifications, fn($notification) => $notification['unread']));
  echo json_encode(['success' => true, 'notifications' => $notifications, 'unreadCount' => $unreadCount]);
  exit;
}

if ($action === 'mark_read') {
  $database->prepare('UPDATE orders SET status_seen = 1 WHERE user_id = ?')->execute([$userId]);
  $notifications = buildNotifications($database, $userId, $statusLabels);
  echo json_encode(['success' => true, 'notifications' => $notifications, 'unreadCount' => 0]);
  exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
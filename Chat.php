<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validation.php';
header('Content-Type: application/json');

$database = getDatabase();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Figures out who is chatting: a logged-in user, or a guest identified by a
 * long-lived cookie token (created on first contact if missing).
 */
function getChatIdentity(): array
{
  if (!empty($_SESSION['user_id'])) {
    return ['type' => 'user', 'user_id' => (int) $_SESSION['user_id']];
  }

  $token = $_COOKIE['chat_token'] ?? '';
  if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    $token = bin2hex(random_bytes(16));
    setcookie('chat_token', $token, time() + 60 * 60 * 24 * 180, '/');
    $_COOKIE['chat_token'] = $token;
  }
  return ['type' => 'guest', 'guest_token' => $token];
}

/**
 * Finds the caller's existing chat thread, or creates one on first contact.
 */
function findOrCreateThread(PDO $database, array $identity, string $guestName = ''): int
{
  if ($identity['type'] === 'user') {
    $statement = $database->prepare('SELECT id FROM chat_threads WHERE user_id = ?');
    $statement->execute([$identity['user_id']]);
    $threadId = $statement->fetchColumn();
    if ($threadId) {
      return (int) $threadId;
    }
    $statement = $database->prepare('INSERT INTO chat_threads (user_id) VALUES (?)');
    $statement->execute([$identity['user_id']]);
    return (int) $database->lastInsertId();
  }

  $statement = $database->prepare('SELECT id, guest_name FROM chat_threads WHERE guest_token = ?');
  $statement->execute([$identity['guest_token']]);
  $thread = $statement->fetch(PDO::FETCH_ASSOC);
  if ($thread) {
    if ($guestName !== '' && ($thread['guest_name'] === '' || $thread['guest_name'] === null || $thread['guest_name'] === 'Guest')) {
      $database->prepare('UPDATE chat_threads SET guest_name = ? WHERE id = ?')->execute([$guestName, $thread['id']]);
    }
    return (int) $thread['id'];
  }
  $statement = $database->prepare('INSERT INTO chat_threads (guest_token, guest_name) VALUES (?, ?)');
  $statement->execute([$identity['guest_token'], $guestName !== '' ? $guestName : 'Guest']);
  return (int) $database->lastInsertId();
}

function formatMessages(array $rows): array
{
  return array_map(function ($row) {
    return [
      'sender' => $row['sender'],
      'message' => $row['message'],
      'time' => date('M j, g:i A', strtotime($row['created_at'])),
      'created_at' => $row['created_at'],
    ];
  }, $rows);
}

/* ---------------- Client-facing actions ---------------- */

if ($action === 'send') {
  $message = trim($_POST['message'] ?? '');
  $name = trim($_POST['name'] ?? '');
  if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type a message first.']);
    exit;
  }
  $identity = getChatIdentity();
  $threadId = findOrCreateThread($database, $identity, $name);
  $statement = $database->prepare('INSERT INTO chat_messages (thread_id, sender, message, read_by_client) VALUES (?, ?, ?, 1)');
  $statement->execute([$threadId, 'client', substr($message, 0, 2000)]);
  $database->prepare('UPDATE chat_threads SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$threadId]);
  echo json_encode(['success' => true]);
  exit;
}

if ($action === 'poll') {
  $identity = getChatIdentity();
  $column = $identity['type'] === 'user' ? 'user_id' : 'guest_token';
  $value = $identity['type'] === 'user' ? $identity['user_id'] : $identity['guest_token'];
  $statement = $database->prepare("SELECT id FROM chat_threads WHERE $column = ?");
  $statement->execute([$value]);
  $threadId = $statement->fetchColumn();

  if (!$threadId) {
    echo json_encode(['success' => true, 'hasThread' => false, 'messages' => []]);
    exit;
  }

  $statement = $database->prepare('SELECT sender, message, created_at FROM chat_messages WHERE thread_id = ? ORDER BY id ASC');
  $statement->execute([$threadId]);
  $messages = $statement->fetchAll(PDO::FETCH_ASSOC);

  $database->prepare('UPDATE chat_messages SET read_by_client = 1 WHERE thread_id = ? AND sender = ?')->execute([$threadId, 'admin']);

  echo json_encode(['success' => true, 'hasThread' => true, 'messages' => formatMessages($messages)]);
  exit;
}

/* ---------------- Admin-facing actions ---------------- */

if (in_array($action, ['admin_threads', 'admin_thread_messages', 'admin_reply', 'admin_unread_total'], true)) {
  if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
  }

  if ($action === 'admin_threads') {
    $rows = $database->query("
      SELECT chat_threads.id, chat_threads.guest_name, chat_threads.last_message_at,
             users.name AS user_name, users.email AS user_email,
             (SELECT message FROM chat_messages WHERE thread_id = chat_threads.id ORDER BY id DESC LIMIT 1) AS last_message,
             (SELECT COUNT(*) FROM chat_messages WHERE thread_id = chat_threads.id AND sender = 'client' AND read_by_admin = 0) AS unread_count
      FROM chat_threads
      LEFT JOIN users ON users.id = chat_threads.user_id
      ORDER BY chat_threads.last_message_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $threads = array_map(function ($row) {
      return [
        'id' => (int) $row['id'],
        'name' => $row['user_name'] ?: ($row['guest_name'] ?: 'Guest'),
        'subtitle' => $row['user_email'] ?: 'Guest visitor',
        'lastMessage' => $row['last_message'] ?? '',
        'time' => date('M j, g:i A', strtotime($row['last_message_at'])),
        'unread' => (int) $row['unread_count'],
      ];
    }, $rows);

    echo json_encode(['success' => true, 'threads' => $threads]);
    exit;
  }

  if ($action === 'admin_thread_messages') {
    $threadId = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'thread_id', FILTER_VALIDATE_INT);
    if (!$threadId) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Missing thread.']);
      exit;
    }
    $statement = $database->prepare('SELECT sender, message, created_at FROM chat_messages WHERE thread_id = ? ORDER BY id ASC');
    $statement->execute([$threadId]);
    $messages = $statement->fetchAll(PDO::FETCH_ASSOC);
    $database->prepare("UPDATE chat_messages SET read_by_admin = 1 WHERE thread_id = ? AND sender = 'client'")->execute([$threadId]);
    echo json_encode(['success' => true, 'messages' => formatMessages($messages)]);
    exit;
  }

  if ($action === 'admin_reply') {
    $threadId = filter_input(INPUT_POST, 'thread_id', FILTER_VALIDATE_INT);
    $message = trim($_POST['message'] ?? '');
    if (!$threadId || $message === '') {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Missing thread or message.']);
      exit;
    }
    $statement = $database->prepare('INSERT INTO chat_messages (thread_id, sender, message, read_by_admin) VALUES (?, ?, ?, 1)');
    $statement->execute([$threadId, 'admin', substr($message, 0, 2000)]);
    $database->prepare('UPDATE chat_threads SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$threadId]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'admin_unread_total') {
    $threadCount = (int) $database->query("SELECT COUNT(DISTINCT thread_id) FROM chat_messages WHERE sender = 'client' AND read_by_admin = 0")->fetchColumn();
    echo json_encode(['success' => true, 'unreadThreads' => $threadCount]);
    exit;
  }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
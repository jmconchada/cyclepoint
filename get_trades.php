<?php
/**
 * get_trades.php
 * Checks if logged-in user and another user have a completed trade.
 * GET param: partner_id
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'has_trade' => false]);
    exit;
}

$user_id    = (int) $_SESSION['user_id'];
$partner_id = isset($_GET['partner_id']) ? (int) $_GET['partner_id'] : 0;

if ($partner_id <= 0) {
    echo json_encode(['success' => false, 'has_trade' => false]);
    exit;
}

// Check if a completed trade exists between these two users
$stmt = $conn->prepare("
    SELECT t.id, t.created_at, l.title as item_title
    FROM trades t
    JOIN listings l ON l.id = t.listing_id
    WHERE (t.initiator_id = ? AND t.partner_id = ?)
       OR (t.initiator_id = ? AND t.partner_id = ?)
    ORDER BY t.created_at DESC
    LIMIT 1
");
$stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
$stmt->execute();
$trade = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success'   => true,
    'has_trade' => $trade ? true : false,
    'trade'     => $trade
]);
?>
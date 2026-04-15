<?php
/**
 * mark_traded.php
 * Accepts BOTH listing IDs (one from each user) and marks both as completed.
 * POST JSON: my_listing_id, partner_listing_id, partner_id
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$initiator_id = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$my_listing_id      = isset($input['my_listing_id'])      ? (int)$input['my_listing_id']      : 0;
$partner_listing_id = isset($input['partner_listing_id']) ? (int)$input['partner_listing_id'] : 0;
$partner_id         = isset($input['partner_id'])         ? (int)$input['partner_id']         : 0;

// Basic validations
if ($my_listing_id <= 0 || $partner_listing_id <= 0 || $partner_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid data. Select one item from each side.']);
    exit;
}
if ($initiator_id === $partner_id) {
    echo json_encode(['success' => false, 'error' => 'Cannot trade with yourself.']);
    exit;
}
if ($my_listing_id === $partner_listing_id) {
    echo json_encode(['success' => false, 'error' => 'The two items must be different.']);
    exit;
}

// Verify MY listing
$myStmt = $conn->prepare("SELECT id, title, user_id, status FROM listings WHERE id = ?");
$myStmt->bind_param("i", $my_listing_id);
$myStmt->execute();
$myListing = $myStmt->get_result()->fetch_assoc();
$myStmt->close();

if (!$myListing) { echo json_encode(['success'=>false,'error'=>'Your item not found.']); exit; }
if ((int)$myListing['user_id'] !== $initiator_id) { echo json_encode(['success'=>false,'error'=>'First item must be yours.']); exit; }
if ($myListing['status'] === 'completed') { echo json_encode(['success'=>false,'error'=>'Your item already traded.']); exit; }

// Verify PARTNER listing
$ptStmt = $conn->prepare("SELECT id, title, user_id, status FROM listings WHERE id = ?");
$ptStmt->bind_param("i", $partner_listing_id);
$ptStmt->execute();
$partnerListing = $ptStmt->get_result()->fetch_assoc();
$ptStmt->close();

if (!$partnerListing) { echo json_encode(['success'=>false,'error'=>"Partner's item not found."]); exit; }
if ((int)$partnerListing['user_id'] !== $partner_id) { echo json_encode(['success'=>false,'error'=>"Second item must belong to your partner."]); exit; }
if ($partnerListing['status'] === 'completed') { echo json_encode(['success'=>false,'error'=>"Partner's item already traded."]); exit; }

// Verify they have chatted
$convStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)");
$convStmt->bind_param("iiii", $initiator_id, $partner_id, $partner_id, $initiator_id);
$convStmt->execute();
$convRow = $convStmt->get_result()->fetch_assoc();
$convStmt->close();
if ($convRow['cnt'] == 0) { echo json_encode(['success'=>false,'error'=>'Chat with this user first.']); exit; }

// Check no existing trade
$dupStmt = $conn->prepare("SELECT id FROM trades WHERE (initiator_id=? AND partner_id=?) OR (initiator_id=? AND partner_id=?)");
$dupStmt->bind_param("iiii", $initiator_id, $partner_id, $partner_id, $initiator_id);
$dupStmt->execute();
$dup = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();
if ($dup) { echo json_encode(['success'=>false,'error'=>'A trade between you two is already recorded.']); exit; }

// Record trade
$tradeStmt = $conn->prepare("INSERT INTO trades (listing_id, initiator_id, partner_id) VALUES (?, ?, ?)");
$tradeStmt->bind_param("iii", $my_listing_id, $initiator_id, $partner_id);
if (!$tradeStmt->execute()) { echo json_encode(['success'=>false,'error'=>'Failed to record trade.']); exit; }
$tradeStmt->close();

// Mark BOTH listings completed
$u1 = $conn->prepare("UPDATE listings SET status='completed' WHERE id=?");
$u1->bind_param("i", $my_listing_id); $u1->execute(); $u1->close();
$u2 = $conn->prepare("UPDATE listings SET status='completed' WHERE id=?");
$u2->bind_param("i", $partner_listing_id); $u2->execute(); $u2->close();

// Get initiator name
$nStmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$nStmt->bind_param("i", $initiator_id);
$nStmt->execute();
$nRow = $nStmt->get_result()->fetch_assoc();
$nStmt->close();
$initiatorName = $nRow['name'] ?? 'A user';
$myTitle = $myListing['title'];
$ptTitle = $partnerListing['title'];

// Notify partner
$msgP = "🤝 Trade done! {$initiatorName} traded \"{$myTitle}\" for your \"{$ptTitle}\". Rate each other now!";
$n1 = $conn->prepare("INSERT INTO notifications (user_id, type, message, item_id) VALUES (?, 'trade_complete', ?, ?)");
$n1->bind_param("isi", $partner_id, $msgP, $my_listing_id); $n1->execute(); $n1->close();

// Notify initiator
$msgI = "🤝 Trade done! You traded \"{$myTitle}\" for \"{$ptTitle}\". Rate your trade partner now!";
$n2 = $conn->prepare("INSERT INTO notifications (user_id, type, message, item_id) VALUES (?, 'trade_complete', ?, ?)");
$n2->bind_param("isi", $initiator_id, $msgI, $my_listing_id); $n2->execute(); $n2->close();

$conn->close();

echo json_encode([
    'success'  => true,
    'message'  => "Trade confirmed! \"{$myTitle}\" ↔ \"{$ptTitle}\". You can now rate each other!",
    'my_title' => $myTitle,
    'pt_title' => $ptTitle,
]);
?>
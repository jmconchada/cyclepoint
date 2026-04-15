<?php
/**
 * submit_rating.php
 * Handles POST: create or update a user-to-user rating.
 * One rating per rater–ratee pair (upsert via ON DUPLICATE KEY).
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$rater_id = (int) $_SESSION['user_id'];

// Accept JSON or form POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$ratee_id = isset($input['ratee_id']) ? (int) $input['ratee_id'] : 0;
$rating   = isset($input['rating'])   ? (int) $input['rating']   : 0;
$comment  = isset($input['comment'])  ? trim($input['comment'])  : '';

// --- Validations ---
if ($ratee_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user.']);
    exit;
}
if ($rater_id === $ratee_id) {
    echo json_encode(['success' => false, 'error' => 'You cannot rate yourself.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5.']);
    exit;
}
if (strlen($comment) > 500) {
    echo json_encode(['success' => false, 'error' => 'Comment is too long (max 500 characters).']);
    exit;
}

// Check ratee exists
$chk = $conn->prepare("SELECT id FROM users WHERE id = ?");
$chk->bind_param("i", $ratee_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}
$chk->close();

// Guard: only allow rating if a completed trade exists between the two users
$tradeChk = $conn->prepare("
    SELECT COUNT(*) as cnt FROM trades
    WHERE (initiator_id = ? AND partner_id = ?)
       OR (initiator_id = ? AND partner_id = ?)
");
$tradeChk->bind_param("iiii", $rater_id, $ratee_id, $ratee_id, $rater_id);
$tradeChk->execute();
$tradeRow = $tradeChk->get_result()->fetch_assoc();
$tradeChk->close();
if ($tradeRow['cnt'] == 0) {
    echo json_encode(['success' => false, 'error' => 'You can only rate users you have completed a trade with.']);
    exit;
}

// Upsert: insert or update if pair already exists
$stmt = $conn->prepare("
    INSERT INTO user_ratings (rater_id, ratee_id, rating, comment)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        rating     = VALUES(rating),
        comment    = VALUES(comment),
        updated_at = current_timestamp()
");
$stmt->bind_param("iiis", $rater_id, $ratee_id, $rating, $comment);

if ($stmt->execute()) {
    // Fetch updated average for ratee
    $avg = $conn->query("
        SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total
        FROM user_ratings WHERE ratee_id = $ratee_id
    ")->fetch_assoc();

    $stmt->close();
    $conn->close();
    echo json_encode([
        'success'    => true,
        'message'    => 'Rating submitted!',
        'avg_rating' => $avg['avg_rating'],
        'total'      => $avg['total']
    ]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}
?>
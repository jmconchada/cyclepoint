<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$profile_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$profile_user_id || $profile_user_id == $current_user_id) {
    header("Location: profile.php");
    exit;
}

// Fetch profile user
$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->bind_param("i", $profile_user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    header("Location: index.php");
    exit;
}

// Fetch posted items
$itemsStmt = $conn->prepare("
    SELECT l.*,
           (SELECT path FROM listing_images WHERE listing_id = l.id LIMIT 1) as image_path,
           (SELECT COUNT(*) FROM listing_images WHERE listing_id = l.id) as image_count
    FROM listings l
    WHERE l.user_id = ? AND l.status = 'active'
    ORDER BY l.created_at DESC
");
$itemsStmt->bind_param("i", $profile_user_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result();
$itemsStmt->close();

$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/images/profile-picture.png';
$is_online = $user['last_seen'] && (strtotime($user['last_seen']) > time() - 300);

// Check completed trade
$hasTrade = false;
$tradeStmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM trades
    WHERE (initiator_id = ? AND partner_id = ?)
       OR (initiator_id = ? AND partner_id = ?)
");
$tradeStmt->bind_param("iiii", $current_user_id, $profile_user_id, $profile_user_id, $current_user_id);
$tradeStmt->execute();
$tradeRow = $tradeStmt->get_result()->fetch_assoc();
$hasTrade = $tradeRow['cnt'] > 0;
$tradeStmt->close();

// Item count
$total_items = $items->num_rows;

function timeAgo($ts) {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'Just now';
    if ($d < 3600) return floor($d/60) . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    if ($d < 604800) return floor($d/86400) . ' days ago';
    return date('M j, Y', strtotime($ts));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($user['name']) ?>'s Profile — CyclePoint</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/view_profile.css">
  <link rel="stylesheet" href="assets/css/rating_widget.css">
</head>
<body>

<!-- ── TOPBAR ── -->
<header class="topbar">
  <div class="topbar-inner">
    <a href="javascript:history.back()" class="btn-back">
      <i class="fas fa-arrow-left"></i> Back
    </a>
    <h1 class="topbar-title">User Profile</h1>
    <div class="topbar-spacer"></div>
  </div>
</header>

<!-- ── PAGE BODY ── -->
<div class="profile-page">

  <!-- PROFILE CARD -->
  <div class="profile-card">
    <div class="profile-stripe"></div>

    <div class="profile-body">

      <!-- Avatar -->
      <div class="profile-avatar-wrap">
        <img src="<?= htmlspecialchars($profile_picture) ?>" alt="<?= htmlspecialchars($user['name']) ?>">
        <?php if ($is_online): ?>
          <span class="avatar-online"></span>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="profile-info">
        <div class="profile-name-row">
          <h2><?= htmlspecialchars($user['name']) ?></h2>
          <?php if ($is_online): ?>
            <span class="status-pill online"><i class="fas fa-circle"></i> Online</span>
          <?php else: ?>
            <span class="status-pill offline"><i class="fas fa-circle"></i>
              <?= $user['last_seen'] ? 'Last seen ' . timeAgo($user['last_seen']) : 'Offline' ?>
            </span>
          <?php endif; ?>
        </div>

        <!-- Rating badge -->
        <div class="profile-rating-area">
          <div id="cp-rating-badge-<?= $profile_user_id ?>"></div>
        </div>

        <ul class="meta-list">
          <?php if (!empty($user['email'])): ?>
          <li>
            <span class="meta-icon"><i class="fas fa-envelope"></i></span>
            <?= htmlspecialchars($user['email']) ?>
          </li>
          <?php endif; ?>
          <?php if (!empty($user['contact_number'])): ?>
          <li>
            <span class="meta-icon"><i class="fas fa-phone"></i></span>
            <?= htmlspecialchars($user['contact_number']) ?>
          </li>
          <?php endif; ?>
          <?php if (!empty($user['area'])): ?>
          <li>
            <span class="meta-icon"><i class="fas fa-map-marker-alt"></i></span>
            <?= htmlspecialchars($user['area']) ?>
          </li>
          <?php endif; ?>
          <li>
            <span class="meta-icon"><i class="fas fa-calendar-alt"></i></span>
            Joined <?= date('F Y', strtotime($user['created_at'])) ?>
          </li>
        </ul>
      </div>

      <!-- Actions -->
      <div class="profile-actions-col">
        <a href="chat.php?user_id=<?= $profile_user_id ?>" class="btn-msg">
          <i class="fas fa-comment-dots"></i> Send Message
        </a>

        <?php if ($hasTrade): ?>
          <button class="btn-rate" id="rateUserBtn" onclick="openRatingModal()">
            <i class="fas fa-star"></i>
            <span id="rateUserBtnText">Rate Trader</span>
          </button>
        <?php else: ?>
          <div class="trade-lock-note">
            <i class="fas fa-lock"></i>
            Complete a trade to leave a rating
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Stats row -->
    <div class="profile-stats">
      <div class="stat-item">
        <span class="stat-num"><?= $total_items ?></span>
        <div class="stat-label">Items Posted</div>
      </div>
      <div class="stat-item">
        <span class="stat-num"><?= $is_online ? '🟢' : '⚫' ?></span>
        <div class="stat-label">Status</div>
      </div>
      <div class="stat-item">
        <span class="stat-num"><?= date('Y') - date('Y', strtotime($user['created_at'])) > 0 ? date('Y') - date('Y', strtotime($user['created_at'])) : '<1' ?></span>
        <div class="stat-label">Years Active</div>
      </div>
    </div>
  </div>

  <!-- ITEMS SECTION -->
  <div class="items-section">
    <div class="section-header">
      <h2><?= htmlspecialchars($user['name']) ?>'s Items</h2>
      <span class="item-count-pill"><?= $total_items ?> item<?= $total_items !== 1 ? 's' : '' ?></span>
    </div>

    <?php if ($total_items > 0): ?>
      <!-- Reset result pointer -->
      <?php $items->data_seek(0); ?>
      <div class="items-grid">
        <?php while ($item = $items->fetch_assoc()): ?>
          <div class="item-card">
            <div class="item-image">
              <?php if ($item['image_path']): ?>
                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                <?php if ($item['image_count'] > 1): ?>
                  <div class="image-count"><i class="fas fa-images"></i> <?= $item['image_count'] ?></div>
                <?php endif; ?>
              <?php else: ?>
                <div class="no-image">
                  <i class="fas fa-image"></i>
                  <span>No Image</span>
                </div>
              <?php endif; ?>
              <div class="item-badge">FOR TRADE</div>
            </div>

            <div class="item-content">
              <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
              <div class="item-meta">
                <span><i class="fas fa-tag"></i><?= htmlspecialchars($item['category']) ?></span>
                <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($item['location']) ?></span>
              </div>
              <p class="item-description">
                <?= htmlspecialchars(substr($item['description'], 0, 90)) ?><?= strlen($item['description']) > 90 ? '…' : '' ?>
              </p>
              <div class="item-date">
                <i class="fas fa-clock"></i>
                Posted <?= date('M d, Y', strtotime($item['created_at'])) ?>
              </div>
            </div>

            <div class="item-actions">
              <a href="item_details.php?id=<?= $item['id'] ?>" class="btn-action btn-view">
                <i class="fas fa-eye"></i> View Details
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-box-open"></i></div>
        <h3>No Items Yet</h3>
        <p><?= htmlspecialchars($user['name']) ?> hasn't posted any items for trade.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- REVIEWS SECTION (only if trade exists) -->
  <?php if ($hasTrade): ?>
  <div id="cp-reviews-container"></div>
  <?php endif; ?>

</div><!-- end .profile-page -->

<script src="assets/js/rating_widget.js"></script>
<script>
const _rateeId   = <?= $profile_user_id ?>;
const _rateeName = <?= json_encode($user['name']) ?>;
const _rateePic  = <?= json_encode($profile_picture) ?>;

document.addEventListener('DOMContentLoaded', function () {
  // Load star badge
  CyclePointRating.renderBadge('cp-rating-badge-<?= $profile_user_id ?>', _rateeId);

  <?php if ($hasTrade): ?>
  // Load reviews
  CyclePointRating.renderReviews('cp-reviews-container', _rateeId);

  // Check existing rating
  fetch('get_ratings.php?user_id=' + _rateeId, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.existing_rating) {
        const btn = document.getElementById('rateUserBtn');
        if (btn) {
          document.getElementById('rateUserBtnText').textContent = 'Update Rating';
        }
        window._existRating  = data.existing_rating.rating;
        window._existComment = data.existing_rating.comment;
      }
    });
  <?php endif; ?>
});

function openRatingModal() {
  CyclePointRating.openModal(
    _rateeId, _rateeName, _rateePic,
    window._existRating  || null,
    window._existComment || null
  );
}
</script>

<script src="assets/js/notification-badges.js"></script>
</body>
</html>
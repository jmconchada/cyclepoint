<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ── Fetch all completed trades ──
$tradesQuery = $conn->query("
    SELECT
        t.id,
        t.created_at,
        l.title        AS item_title,
        l.category     AS item_category,
        ui.name        AS initiator_name,
        ui.email       AS initiator_email,
        up.name        AS partner_name,
        up.email       AS partner_email,
        r1.rating      AS initiator_rating,
        r1.comment     AS initiator_comment,
        r2.rating      AS partner_rating,
        r2.comment     AS partner_comment
    FROM trades t
    JOIN listings l  ON l.id  = t.listing_id
    JOIN users ui    ON ui.id = t.initiator_id
    JOIN users up    ON up.id = t.partner_id
    LEFT JOIN user_ratings r1 ON r1.rater_id = t.initiator_id AND r1.ratee_id = t.partner_id
    LEFT JOIN user_ratings r2 ON r2.rater_id = t.partner_id  AND r2.ratee_id = t.initiator_id
    ORDER BY t.created_at DESC
");

$trades = [];
if ($tradesQuery) {
    while ($row = $tradesQuery->fetch_assoc()) $trades[] = $row;
}

$totalTrades    = count($trades);
$totalRatings   = $conn->query("SELECT COUNT(*) as c FROM user_ratings")->fetch_assoc()['c'] ?? 0;
$avgRating      = $conn->query("SELECT ROUND(AVG(rating),1) as avg FROM user_ratings")->fetch_assoc()['avg'] ?? 0;
$pendingRatings = max(0, $totalTrades * 2 - $totalRatings);

function adminStars($rating) {
    if (!$rating) return '<span class="no-rating-yet">No rating</span>';
    $s = '';
    for ($i = 1; $i <= 5; $i++)
        $s .= $i <= $rating ? '<span class="star-filled">★</span>' : '<span class="star-empty">★</span>';
    return '<div class="stars-row">' . $s . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trades &amp; Ratings — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admindash.css">
</head>
<body>

<div class="admin-layout">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo">
        <i class="fas fa-shield-halved"></i>
        <span>Admin Panel</span>
      </div>
      <a href="index.php" class="visit-site-btn">
        <i class="fas fa-arrow-up-right-from-square"></i>
        <span>Visit Site</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="dashboard.php" class="nav-item">
        <i class="fas fa-chart-line"></i><span>Dashboard</span>
      </a>
      <div class="nav-section-label">Management</div>
      <a href="manage_posts.php" class="nav-item">
        <i class="fas fa-box-archive"></i><span>Manage Posts</span>
      </a>
      <a href="admindash.php" class="nav-item">
        <i class="fas fa-users-gear"></i><span>User Management</span>
      </a>
      <a href="announcements.php" class="nav-item">
        <i class="fas fa-bullhorn"></i><span>Announcements</span>
      </a>
      <a href="admin_trades.php" class="nav-item active">
        <i class="fas fa-handshake"></i><span>Trades &amp; Ratings</span>
      </a>
      <div class="nav-section-label">System</div>
      <a href="logout.php" class="nav-item logout">
        <i class="fas fa-arrow-right-from-bracket"></i><span>Logout</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="admin-profile">
        <div class="admin-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
        <div class="admin-info">
          <div class="admin-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
          <div class="admin-role">Administrator</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ── Main ── -->
  <main class="main-content">

    <!-- Page header -->
    <header class="page-header">
      <h1 class="page-title"><i class="fas fa-handshake"></i> Trades &amp; Ratings</h1>
      <p class="page-subtitle">All completed trades and user ratings recorded on the platform</p>
    </header>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-handshake"></i></div>
        <div class="stat-content">
          <div class="stat-label">Completed Trades</div>
          <div class="stat-value"><?= $totalTrades ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-star"></i></div>
        <div class="stat-content">
          <div class="stat-label">Ratings Given</div>
          <div class="stat-value"><?= $totalRatings ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-chart-bar"></i></div>
        <div class="stat-content">
          <div class="stat-label">Avg Platform Rating</div>
          <div class="stat-value"><?= $avgRating ?: '—' ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
          <div class="stat-label">Pending Ratings</div>
          <div class="stat-value"><?= $pendingRatings ?></div>
        </div>
      </div>
    </div>

    <!-- Trades Table -->
    <div class="table-container">
      <div class="table-header">
        <h2 class="table-title">
          <i class="fas fa-list"></i>
          Trade Records
          <span class="trades-count"><?= $totalTrades ?> total</span>
        </h2>
        <div class="table-actions">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="tradeSearch" placeholder="Search trades..." oninput="filterTrades()">
          </div>
        </div>
      </div>

      <?php if (empty($trades)): ?>
        <div class="empty-state">
          <i class="fas fa-handshake-slash"></i>
          <h3>No Trades Yet</h3>
          <p>Completed trades will appear here once users mark items as traded.</p>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table id="tradesTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Item Traded</th>
                <th>Trader 1 (Initiator)</th>
                <th>Trader 2 (Partner)</th>
                <th>Rating: T1 → T2</th>
                <th>Rating: T2 → T1</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($trades as $i => $trade): ?>
              <tr>
                <td class="row-num"><?= $i + 1 ?></td>

                <td>
                  <div class="item-title-cell"><?= htmlspecialchars($trade['item_title']) ?></div>
                  <span class="item-badge"><i class="fas fa-tag"></i> <?= htmlspecialchars($trade['item_category']) ?></span>
                </td>

                <td>
                  <div class="user-cell col">
                    <span class="name"><?= htmlspecialchars($trade['initiator_name']) ?></span>
                    <span class="email"><?= htmlspecialchars($trade['initiator_email']) ?></span>
                  </div>
                </td>

                <td>
                  <div class="user-cell col">
                    <span class="name"><?= htmlspecialchars($trade['partner_name']) ?></span>
                    <span class="email"><?= htmlspecialchars($trade['partner_email']) ?></span>
                  </div>
                </td>

                <td>
                  <div class="rating-cell">
                    <?= adminStars($trade['initiator_rating']) ?>
                    <?php if ($trade['initiator_comment']): ?>
                      <div class="rating-comment" title="<?= htmlspecialchars($trade['initiator_comment']) ?>">
                        "<?= htmlspecialchars(substr($trade['initiator_comment'], 0, 40)) ?><?= strlen($trade['initiator_comment']) > 40 ? '…' : '' ?>"
                      </div>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <div class="rating-cell">
                    <?= adminStars($trade['partner_rating']) ?>
                    <?php if ($trade['partner_comment']): ?>
                      <div class="rating-comment" title="<?= htmlspecialchars($trade['partner_comment']) ?>">
                        "<?= htmlspecialchars(substr($trade['partner_comment'], 0, 40)) ?><?= strlen($trade['partner_comment']) > 40 ? '…' : '' ?>"
                      </div>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <div class="trade-date">
                    <?= date('M d, Y', strtotime($trade['created_at'])) ?>
                    <br><span><?= date('h:i A', strtotime($trade['created_at'])) ?></span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
function filterTrades() {
    const q = document.getElementById('tradeSearch').value.toLowerCase();
    document.querySelectorAll('#tradesTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
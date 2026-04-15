<?php
session_start();
require __DIR__ . '/db.php';

// ---------------- LOGIN CHECK ----------------
$is_logged_in = !empty($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? 0;

// ---------------- USER DATA ----------------
$profile_picture = 'assets/images/profile-picture.png';
$name = 'Guest';

// If user is logged in, fetch fresh data from database
if ($is_logged_in && $user_id > 0) {
    $userStmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userData = $userResult->fetch_assoc()) {
            $name = $userData['name'];
            $profile_picture = !empty($userData['profile_picture']) ? $userData['profile_picture'] : 'assets/images/profile-picture.png';
        }
        $userStmt->close();
    }
} else {
    // For guests, use session data if available
    $profile_picture = $_SESSION['user_picture'] ?? 'assets/images/profile-picture.png';
    $name = $_SESSION['user_name'] ?? 'Guest';
}

// ---------------- GET UNREAD COUNTS ----------------
$unread_messages = 0;
$unread_notifications = 0;

if ($is_logged_in && $user_id > 0) {
    // Count unread messages (using is_read column, NOT status)
    $msgStmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    if ($msgStmt) {
        $msgStmt->bind_param("i", $user_id);
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
        if ($msgRow = $msgResult->fetch_assoc()) {
            $unread_messages = (int)$msgRow['count'];
        }
        $msgStmt->close();
    }
    
    // Count unread notifications (using is_read column)
    $notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($notifStmt) {
        $notifStmt->bind_param("i", $user_id);
        $notifStmt->execute();
        $notifResult = $notifStmt->get_result();
        if ($notifRow = $notifResult->fetch_assoc()) {
            $unread_notifications = (int)$notifRow['count'];
        }
        $notifStmt->close();
    }
}

// ---------------- LANGUAGE ----------------
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang']; 
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$lang = $_SESSION['lang'] ?? 'en';

// ---------------- SEARCH TERM ----------------
$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

// ---------------- CATEGORY FILTER ----------------
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';

// ---------------- LOCATION FILTER ----------------
$locationFilter = isset($_GET['location']) ? trim($_GET['location']) : '';

// ---------------- DEFINE FIXED CATEGORIES ----------------
// Short keys match what's actually stored in the DB
$fixedCategories = [
    'clothes',
    'gadgets',
    'appliances',
    'furniture',
    'food',
    'tools'
];

// Display labels for each category key
$categoryLabels = [
    'clothes'    => 'Clothes & Bags',
    'gadgets'    => 'Gadgets',
    'appliances' => 'Appliances',
    'furniture'  => 'Furniture',
    'food'       => 'Food & Goods',
    'tools'      => 'Tools',
];

// ---------------- NORMALIZE CATEGORY FUNCTION ----------------
function normalizeCategory($category) {
    return strtolower(trim(preg_replace('/\s+/', ' ', $category)));
}

// ---------------- FETCH LATEST LISTINGS ----------------
$sql = "SELECT id, user_id, title, category, location, created_at, sort_order 
        FROM listings 
        WHERE status = 'active'";

$params = [];
$types = '';

// Add search term filter
if (!empty($searchTerm)) {
    $sql .= " AND (LOWER(title) LIKE ? OR LOWER(location) LIKE ? OR LOWER(category) LIKE ?)";
    $searchTermNormalized = "%" . strtolower($searchTerm) . "%";
    $params[] = $searchTermNormalized;
    $params[] = $searchTermNormalized;
    $params[] = $searchTermNormalized;
    $types .= 'sss';
}

// Add category filter
if (!empty($categoryFilter)) {
    $sql .= " AND (category = ? OR category LIKE ?)";
    $params[] = $categoryFilter;
    $params[] = "%" . $categoryFilter . "%";
    $types .= 'ss';
}

// Add location filter
if (!empty($locationFilter)) {
    $sql .= " AND location LIKE ?";
    $params[] = "%" . $locationFilter . "%";
    $types .= 's';
}

// Order by sort_order if it exists, otherwise by id DESC
$sql .= " ORDER BY COALESCE(sort_order, id) DESC, id DESC LIMIT 12";

// Prepare statement
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

// Execute statement
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$res = $stmt->get_result();

// ---------------- FETCH RESULTS ----------------
$listings = [];
$images = [];

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $listings[] = $row;

        // Fetch the image associated with the listing
        $imageStmt = $conn->prepare("SELECT path FROM listing_images WHERE listing_id = ? LIMIT 1");
        if ($imageStmt) {
            $imageStmt->bind_param("i", $row['id']);
            $imageStmt->execute();
            $imageRes = $imageStmt->get_result();
            
            $images[$row['id']] = $imageRes && $imageRes->num_rows > 0
                                  ? $imageRes->fetch_assoc()['path']
                                  : null;
            $imageStmt->close();
        }
    }
}

$stmt->close();

// ---------------- IF NO RESULTS ----------------
$noItemsMessage = '';
if (empty($listings) && !empty($searchTerm)) {
    $noItemsMessage = "No items found matching your search term.";
} elseif (empty($listings) && !empty($categoryFilter)) {
    $noItemsMessage = "No items found in this category.";
} elseif (empty($listings) && !empty($locationFilter)) {
    $noItemsMessage = "No items found in this location.";
} elseif (empty($listings)) {
    $noItemsMessage = "No items available yet.";
}

// ---------------- TRANSLATION ARRAY ----------------
$translations = [
    'en' => [
        'title' => 'CyclePoint — Barter Only',
        'search_placeholder' => 'Search items to trade (e.g., "headphones")',
        'fresh_recommendations' => 'Fresh recommendations',
        'no_items' => 'No items yet.',
        'categories' => 'All Categories',
        'post_item' => 'Post item',
        'login' => 'Login',
        'location' => 'Choose location',
        'view_profile' => 'View & Edit Profile',
        'logout' => 'Logout',
        'for_trade' => 'For Trade',
        'no_image' => 'No Image',
        'follow_us' => 'Follow Us',
        'contact_us' => 'Contact Us'
    ],
    'fil' => [
        'title' => 'CyclePoint — Barter Lang',
        'search_placeholder' => 'Maghanap ng mga item na ipapalit (hal. "headphones")',
        'fresh_recommendations' => 'Mga bagong rekomendasyon',
        'no_items' => 'Walang mga item.',
        'categories' => 'Lahat ng Kategorya',
        'post_item' => 'Mag-post ng item',
        'login' => 'Mag-login',
        'location' => 'Pumili ng lokasyon',
        'view_profile' => 'Tingnan at I-edit ang Profile',
        'logout' => 'Mag-logout',
        'for_trade' => 'Para sa Pagpapalit',
        'no_image' => 'Walang Larawan',
        'follow_us' => 'Sundan Kami',
        'contact_us' => 'Kontakin Kami'
    ]
];

$translation = $translations[$lang];

// ---------------- PASS DATA TO JS ----------------
echo "<script>
const userProfile = {
    profile_picture: '" . addslashes($profile_picture) . "',
    name: '" . addslashes($name) . "'
};
const currentLang = '" . $lang . "'; 
const isLoggedIn = " . json_encode($is_logged_in) . ";
</script>";
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<title><?= $translation['title'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

<!-- ================= MAIN HEADER ================= -->
<header class="cp-header">
  <div class="cp-wrap">

    <!-- ===== LEFT: Logo + Location ===== -->
    <div class="cp-header-left" style="display:flex; align-items:center; gap:12px;">
      <a class="cp-logo" href="index.php">
        <img src="assets/images/logo.png" alt="CyclePoint Logo" class="cp-logo-img">
      </a>

      <div class="cp-loc">
        <i class="fa-solid fa-magnifying-glass-location"></i>
        <select class="cp-loc-input" onchange="if(this.value) window.location.href='index.php?location=' + encodeURIComponent(this.value); else window.location.href='index.php';">
          <option value="" <?= empty($locationFilter) ? 'selected' : '' ?>><?= $translation['location'] ?></option>
          <option value="Antipolo City" <?= $locationFilter === 'Antipolo City' ? 'selected' : '' ?>>Antipolo City</option>
          <option value="Quezon City" <?= $locationFilter === 'Quezon City' ? 'selected' : '' ?>>Quezon City</option>
          <option value="Brgy.Cuyambay,Tanay Rizal" <?= $locationFilter === 'Brgy.Cuyambay,Tanay Rizal' ? 'selected' : '' ?>>Brgy.Cuyambay,Tanay Rizal</option>
        </select>
      </div>
    </div>

    <!-- ===== MIDDLE SEARCH ===== -->
    <div class="cp-header-middle" style="flex:1; margin:0 12px;">
        <form action="index.php" method="GET" class="cp-search-form">
            <div class="cp-search-container">
                <input type="text" name="term" placeholder="<?= $translation['search_placeholder'] ?>" value="<?= htmlspecialchars($searchTerm ?? '') ?>" class="cp-search-input">
                <button type="submit" class="cp-search-btn">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- LANGUAGE -->
    <div class="cp-lang">
      <button class="cp-lang-btn" id="currentLangBtn" onclick="toggleLangDropdown()">
        <?= $lang === 'en' ? '🇺🇸 ENGLISH' : '🇵🇭 FILIPINO' ?>
        <i class="fa-solid fa-angle-down"></i>
      </button>
      <div class="cp-lang-menu" id="langMenu">
        <button class="<?= $lang === 'en' ? 'active' : '' ?>" onclick="changeLanguage('en')">
          🇺🇸 English <i class="fa-solid fa-check"></i>
        </button>
        <button class="<?= $lang === 'fil' ? 'active' : '' ?>" onclick="changeLanguage('fil')">
          🇵🇭 Filipino <i class="fa-solid fa-check"></i>
        </button>
      </div>
    </div>

  <!-- CHAT + NOTIFICATIONS + PROFILE -->
<?php if ($is_logged_in): ?>
  <div class="cp-header-right" style="display:flex; align-items:center; gap:12px;">

    <!-- Messages with notification badge -->
    <a href="chat.php" class="cp-message" title="Messages">
      <i class="fa-solid fa-comments"></i>
      <?php if ($unread_messages > 0): ?>
        <span class="cp-badge-dot"><?= $unread_messages > 99 ? '99+' : $unread_messages ?></span>
      <?php endif; ?>
    </a>

    <!-- Notifications with notification dot -->
    <a href="notifications.php" class="cp-message" title="Notifications">
      <i class="fa-solid fa-bell"></i>
      <?php if ($unread_notifications > 0): ?>
        <span class="cp-notification-dot"></span>
      <?php endif; ?>
    </a>

    <!-- Profile dropdown -->
    <div class="profile-dropdown">
      <div class="cp-avatar">
        <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile Picture">
      </div>
      <div class="dropdown-content">
        <a href="profile.php"><i class="fa-regular fa-user"></i> <?= $translation['view_profile'] ?></a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <?= $translation['logout'] ?></a>
      </div>
    </div>

    <!-- Post button -->
    <a class="cp-sell" href="<?= $is_logged_in ? 'post_item.php' : 'login.php' ?>">
      <span class="cp-plus">+</span> <?= $translation['post_item'] ?>
    </a>
  </div>
<?php else: ?>
  <a href="login.php" class="login-btn"><?= $translation['login'] ?></a>
<?php endif; ?>

  </div>
</header>

<!-- ================= MINI HEADER CATEGORIES ================= -->
<header class="cp-mini-header">
  <nav class="cp-categories-navbar">
    <ul id="categoriesNavbar" class="cp-categories-list">

      <!-- All Categories -->
      <li class="category <?= empty($categoryFilter) ? 'active' : '' ?>">
        <a href="index.php" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:6px;">
          <span class="cat-icon">🏠</span> <?= $translation['categories'] ?>
        </a>
      </li>

      <?php
      // Always show ALL fixed categories regardless of whether items exist
      $categoryIcons = [
          'clothes'    => '👕',
          'gadgets'    => '📱',
          'appliances' => '🔌',
          'furniture'  => '🛋️',
          'food'       => '🥕',
          'tools'      => '🔧',
      ];

      // Get categories that have active listings
      $activeCategories = [];
      $catCountQuery = $conn->query("SELECT category, COUNT(*) as cnt FROM listings WHERE status='active' AND category IS NOT NULL GROUP BY category");
      if ($catCountQuery) {
          while ($r = $catCountQuery->fetch_assoc()) {
              $activeCategories[strtolower(trim($r['category']))] = $r['cnt'];
          }
      }

      foreach ($fixedCategories as $catKey):
        $displayName = $categoryLabels[$catKey] ?? ucfirst($catKey);
        $icon = $categoryIcons[$catKey] ?? '📦';
        $count = $activeCategories[$catKey] ?? 0;
        $isActive = ($categoryFilter === $catKey);
      ?>
        <li class="category <?= $isActive ? 'active' : '' ?>">
          <a href="index.php?category=<?= urlencode($catKey) ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:6px;">
            <span class="cat-icon"><?= $icon ?></span>
            <?= htmlspecialchars($displayName) ?>
            <?php if ($count > 0): ?>
              <span class="cat-count"><?= $count ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>

    </ul>
  </nav>
</header>

<!-- ================= MAIN CONTENT ================= -->
<main id="pageContent" class="cp-wrap">
  <?php if (!empty($listings)): ?>
    <h2 class="cp-section-title" id="sectionTitle">
      <?php 
      if (!empty($locationFilter)) {
          echo htmlspecialchars($locationFilter);
      } elseif (!empty($categoryFilter)) {
          echo htmlspecialchars($categoryFilter);
      } else {
          echo $translation['fresh_recommendations'];
      }
      ?>
    </h2>
  <?php endif; ?>

  <section class="cp-grid" id="itemsGrid">
    <?php if (!empty($listings)): ?>
      <?php foreach ($listings as $row): ?>
        <article class="cp-card">
          <a class="cp-card-link" href="<?= $is_logged_in ? 'item_details.php?id=' . (int)$row['id'] : 'login.php' ?>">
            <div class="cp-thumb">
              <?php if (!empty($images[$row['id']])): ?>
                <img src="<?= htmlspecialchars($images[$row['id']]) ?>" alt="Item Image">
              <?php else: ?>
                <div class="cp-thumb-ph"><?= $translation['no_image'] ?></div>
              <?php endif; ?>
            </div>
            <div class="cp-info">
              <div class="cp-price cp-barter"><?= $translation['for_trade'] ?></div>
              <div class="cp-title"><?= htmlspecialchars($row['title']) ?></div>
              <div class="cp-meta">
                <span><?= htmlspecialchars($row['location'] ?: '—') ?></span>
                <span><?= $row['created_at'] ? date('d M Y', strtotime($row['created_at'])) : '—' ?></span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p id="noItemsText" style="text-align: center; padding: 40px 20px; color: #666; font-size: 18px;"><?= htmlspecialchars($noItemsMessage) ?></p>
    <?php endif; ?>
  </section>
</main>

<!-- ================= FOOTER ================= -->
<footer class="cp-footer">
  <div class="cp-wrap">
    <div class="cp-footer-top">
      <div class="cp-footer-col">
        <h4><?= $lang === 'en' ? 'About CyclePoint' : 'Tungkol sa CyclePoint' ?></h4>
        <p>CyclePoint is a web-based bartering platform. Trade used clothes, gadgets, appliances, and more — no money involved.</p>
      </div>
      <div class="cp-footer-col cp-social">
        <h4><?= $lang === 'en' ? 'Follow Us' : 'Sundan Kami' ?></h4>
        <div class="cp-social-links">
          <a href="https://facebook.com" target="_blank"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://instagram.com" target="_blank"><i class="fa-brands fa-instagram"></i></a>
        </div>
      </div>
      <div class="cp-footer-col">
        <h4><?= $lang === 'en' ? 'Contact Us' : 'Kontakin Kami' ?></h4>
        <p><i class="fa-solid fa-phone"></i> +63 905 371 6679</p>
        <p><i class="fa-solid fa-envelope"></i> Support@cyclepoint.com</p>
      </div>
    </div>
    <div class="cp-footer-bottom">
      <a class="cp-logo" href="index.php">
        <img src="assets/images/logo.png" alt="CyclePoint Logo" class="cp-logo-img">
      </a>
      <p>© <?= date('Y') ?> CyclePoint — All rights reserved.</p>
    </div>
  </div>
</footer>

<script src="assets/js/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/page-transitions.js"></script>
 <script src="assets/js/notification-badges.js"></script>
</body>
</html>

<?php $conn->close(); ?>
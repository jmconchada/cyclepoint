<?php
session_start();

// Include the database connection file
require __DIR__ . '/db.php';

// ============================
// LOGIN STATE
// ============================
$googleLoggedIn = isset($_SESSION['google_id']);
$localLoggedIn  = isset($_SESSION['user_id']);
$loggedIn       = $googleLoggedIn || $localLoggedIn;

if ($googleLoggedIn) {
    $uid = $_SESSION['google_id']; // string
} elseif ($localLoggedIn) {
    $uid = $_SESSION['user_id'];   // int
} else {
    header('Location: login.php');
    exit;
}

// ============================
// STEP MANAGEMENT
// ============================
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 3) $step = 1;

// ============================
// CATEGORIES
// ============================
$CATS = [
    'clothes'    => 'Used Clothes / Shoes / Bags (no underwear/lingerie)',
    'gadgets'    => 'Used Gadgets & Electronics',
    'appliances' => 'Used Home Appliances',
    'furniture'  => 'Furniture & Home Items',
    'food'       => 'Homegrown Food / Goods',
    'tools'      => 'Tools & Equipment',
];

// ============================
// FIXED LOCATIONS
// ============================
$LOCATIONS = [
    'Antipolo City',
    'Quezon City',
    'Brgy.Cuyambay,Tanay Rizal'
];

// ============================
// RULES
// ============================
$RULES = [
    'clothes' => [
        'note' => 'No underwear or lingerie (panties, bras, boxers, thongs).',
        'banned_keywords' => ['underwear','panty','panties','bra','brassiere','brief','briefs','boxer','boxers','lingerie','thong','g-string','g string','gstring'],
    ],
];

// ============================
// SESSION STORAGE
// ============================
if (!isset($_SESSION['post_item'])) $_SESSION['post_item'] = [];
$state = &$_SESSION['post_item'];

// ============================
// HELPER FUNCTIONS
// ============================
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function violates_rules($category, $title, $desc, $RULES) {
    if (!isset($RULES[$category])) return false;
    $banned = $RULES[$category]['banned_keywords'] ?? [];
    $hay = mb_strtolower($title.' '.$desc);
    foreach ($banned as $kw) {
        if (mb_strpos($hay, mb_strtolower($kw)) !== false) return true;
    }
    return false;
}

$err = '';

// ============================
// FORM PROCESSING
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -------- Step 1: choose category --------
    if ($step === 1) {
        $cat = $_POST['category'] ?? '';
        if (isset($CATS[$cat])) {
            $state['category'] = $cat;
            header('Location: post_item.php?step=2'); exit;
        } else {
            $err = 'Invalid category selected.';
        }
    }

    // -------- Step 2: fill details + upload photos --------
    if ($step === 2) {
        $state['title'] = trim($_POST['title'] ?? '');
        $state['description'] = trim($_POST['description'] ?? '');
        $state['location'] = trim($_POST['location'] ?? '');
        $state['desired_trade'] = trim($_POST['desired_trade'] ?? '');
        $state['category'] = $_POST['category'] ?? $state['category'];

        if ($state['title']=='' || $state['description']=='' || $state['location']=='' || $state['desired_trade']=='') {
            $err = 'Please fill in all required fields.';
        } elseif (!in_array($state['location'], $GLOBALS['LOCATIONS'])) {
            $err = 'Please select a valid location.';
        } elseif (($state['category']??'')==='clothes' && violates_rules('clothes', $state['title'], $state['description'], $RULES)) {
            $err = 'Listings in Used Clothes cannot include underwear or lingerie.';
        } else {
            // Handle photo uploads
            if (!empty($_FILES['photos']['name'][0])) {
                $max = 12;
                $current = count($state['photos'] ?? []);
                $allow = max(0, $max - $current);
                $state['photos'] = $state['photos'] ?? [];
                $state['photos_rel'] = $state['photos_rel'] ?? [];
                $tmpDir = __DIR__.'/uploads/tmp/'.session_id();
                if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

                for ($i=0; $i<count($_FILES['photos']['name']) && $allow>0; $i++) {
                    if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $orig = $_FILES['photos']['name'][$i];
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                    $fname = uniqid('ph_', true).'.'.$ext;
                    $abs = $tmpDir.'/'.$fname;
                    $rel = 'uploads/tmp/'.session_id().'/'.$fname;
                    if (@move_uploaded_file($_FILES['photos']['tmp_name'][$i], $abs)) {
                        $state['photos'][] = $abs;
                        $state['photos_rel'][] = $rel;
                        $allow--;
                    }
                }
            }
            header('Location: post_item.php?step=3'); exit;
        }
    }

    // -------- Step 3: final review and insert into DB --------
    if ($step === 3) {
        $cat = $state['category'] ?? '';
        $tit = $state['title'] ?? '';
        $desc = $state['description'] ?? '';
        $loc = $state['location'] ?? '';
        $want = $state['desired_trade'] ?? '';

        if ($tit=='' || $desc=='' || $loc=='' || $want=='' || !isset($CATS[$cat])) {
            $err = 'Missing required fields.';
        } elseif (!in_array($loc, $LOCATIONS)) {
            $err = 'Invalid location selected.';
        } elseif ($cat==='clothes' && violates_rules('clothes', $tit, $desc, $RULES)) {
            $err = 'Listings in Used Clothes cannot include underwear or lingerie.';
        } else {
            // Insert listing into database
            if ($conn) {
                $stmt = $conn->prepare("INSERT INTO listings(user_id,title,description,category,location,desired_trade,created_at) VALUES(?,?,?,?,?,?,NOW())");
                
                if ($googleLoggedIn) {
                    $stmt->bind_param('ssssss', $uid, $tit, $desc, $cat, $loc, $want); // Google user_id string
                } else {
                    $stmt->bind_param('isssss', $uid, $tit, $desc, $cat, $loc, $want); // Local user_id int
                }

                $stmt->execute();
                $listing_id = $stmt->insert_id;
                $stmt->close();

                // Move photos from tmp to final folder
                $destAbs = __DIR__.'/uploads/listings/'.$listing_id;
                $destRel = 'uploads/listings/'.$listing_id;
                if (!is_dir($destAbs)) mkdir($destAbs, 0777, true);

                if (!empty($state['photos'])) {
                    $ins = $conn->prepare("INSERT INTO listing_images(listing_id,path,sort_order) VALUES(?,?,?)");
                    foreach ($state['photos'] as $idx => $abs) {
                        $ext = pathinfo($abs, PATHINFO_EXTENSION);
                        $finalName = sprintf('img_%02d.%s', $idx+1, $ext ?: 'jpg');
                        $finalAbs = $destAbs.'/'.$finalName;
                        $finalRel = $destRel.'/'.$finalName;
                        if (@rename($abs, $finalAbs)) {
                            $ins->bind_param('isi', $listing_id, $finalRel, $idx);
                            $ins->execute();
                        }
                    }
                    $ins->close();
                }

                $_SESSION['post_item'] = [];
                $_SESSION['flash_message'] = 'Item posted successfully!';
                header('Location: index.php'); exit;
            } else {
                $err = 'Database connection failed.';
            }
        }
    }
}
?>


<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Post Your Item • CyclePoint</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,700;1,9..144,400&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/post_item.css">
</head>
<body>

<header class="site-header">
  <div class="header-inner">

    <!-- Back button — left -->
    <?php if ($step > 1): ?>
      <a href="post_item.php?step=<?= $step-1 ?>" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back
      </a>
    <?php else: ?>
      <a href="index.php" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
      </a>
    <?php endif; ?>

    <!-- Title — center -->
    <h1>Post Your Item</h1>

    <!-- Step indicator — right -->
    <div class="step-indicator">
      <div class="step-pip <?= $step > 1 ? 'done' : ($step == 1 ? 'current' : '') ?>"></div>
      <div class="step-pip <?= $step > 2 ? 'done' : ($step == 2 ? 'current' : '') ?>"></div>
      <div class="step-pip <?= $step == 3 ? 'current' : '' ?>"></div>
      <span class="step-label">Step <?= $step ?> / 3</span>
    </div>

  </div>
</header>

<main class="content">

<?php if ($step===1): ?>
<section class="step step-1">
  <div class="step-bar"></div>
  <div class="step-body">
    <h3>Choose a Category</h3>
    <?php if($err): ?><p class="error-msg"><?= h($err) ?></p><?php endif; ?>
    <form method="post" action="post_item.php?step=1" class="cat-grid">
      <?php foreach($CATS as $k=>$label): ?>
        <button name="category" value="<?= h($k) ?>" type="submit" class="cat-btn">
          <span><i class="fa-solid <?= $k==='clothes'?'fa-shirt':($k==='gadgets'?'fa-mobile-screen':($k==='appliances'?'fa-blender':($k==='furniture'?'fa-couch':($k==='food'?'fa-carrot':'fa-screwdriver-wrench')))) ?>"></i> <?= h($label) ?></span>
          <i class="fa-solid fa-angle-right"></i>
        </button>
      <?php endforeach; ?>
    </form>
  </div>
</section>

<?php elseif ($step===2): ?>
<section class="step step-2">
  <div class="step-bar"></div>
  <div class="step-body">
    <h3>Item Details</h3>
    <form method="post" action="post_item.php?step=2" enctype="multipart/form-data" id="postForm" data-category="<?= h($state['category']??'') ?>">

    <div class="form-field">
      <label class="form-label">Item Title <span class="req">*</span></label>
      <input type="text" name="title" placeholder="e.g. Samsung Galaxy S21, Leather Jacket" value="<?= h($state['title']??'') ?>" required>
    </div>

    <div class="form-field">
      <label class="form-label">Description <span class="req">*</span></label>
      <textarea name="description" placeholder="Describe the condition, brand, size, or any relevant details..." required><?= h($state['description']??'') ?></textarea>
    </div>

    <div class="form-field">
      <label class="form-label">Location <span class="req">*</span></label>
      <select name="location" required>
        <option value="" disabled <?= empty($state['location']) ? 'selected' : '' ?>>Select your location</option>
        <?php foreach($LOCATIONS as $loc): ?>
          <option value="<?= h($loc) ?>" <?= ($state['location']??'')===$loc ? 'selected' : '' ?>><?= h($loc) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-field">
      <label class="form-label">Looking to Trade For <span class="req">*</span></label>
      <input type="text" name="desired_trade" placeholder="e.g. Laptop, Bicycle, Headphones" value="<?= h($state['desired_trade']??'') ?>" required>
    </div>

    <div class="form-field">
      <label class="form-label">Photos <span class="form-hint">(Optional — max 12)</span></label>
      <label for="photos" class="upload-btn"><i class="fa-solid fa-camera"></i> Choose Photos</label>
      <input type="file" id="photos" name="photos[]" multiple accept="image/*" style="display:none;">
      <div id="photoPreviewContainer" class="photo-preview-container"></div>
    </div>

    <?php if($err): ?><p class="error-msg"><?= h($err) ?></p><?php endif; ?>

    <div class="actions">
      <a href="post_item.php?step=1" class="btn-outline">← Back</a>
      <button type="submit" class="btn-primary">Continue →</button>
    </div>
    </form>
  </div>
</section>

<?php elseif ($step===3): ?>
<section class="step step-3">
  <div class="step-bar"></div>
  <div class="step-body">
    <h3>Review & Confirm</h3>
    <?php if($err): ?><p class="error-msg"><?= h($err) ?></p><?php endif; ?>
    <div class="review">
      <div class="review-row">
        <div class="review-key">Category</div>
        <div class="review-val"><?= h($CATS[$state['category']]??'—') ?></div>
      </div>
      <div class="review-row">
        <div class="review-key">Title</div>
        <div class="review-val"><?= h($state['title']??'—') ?></div>
      </div>
      <div class="review-row">
        <div class="review-key">Description</div>
        <div class="review-val"><?= nl2br(h($state['description']??'—')) ?></div>
      </div>
      <div class="review-row">
        <div class="review-key">Location</div>
        <div class="review-val"><?= h($state['location']??'—') ?></div>
      </div>
      <div class="review-row">
        <div class="review-key">Trade For</div>
        <div class="review-val"><?= h($state['desired_trade']??'—') ?></div>
      </div>
    </div>
    <form method="post" action="post_item.php?step=3">
      <div class="actions">
        <a href="post_item.php?step=2" class="btn-outline">← Back</a>
        <button type="submit" class="btn-primary">Post Item →</button>
      </div>
    </form>
  </div>
</section>
<?php endif; ?>

</main>

<!-- ================= FOOTER ================= -->
<footer class="cp-footer">
  <div class="cp-wrap">
    <div class="cp-footer-top">
      <div class="cp-footer-col">
        <h4>About CyclePoint</h4>
        <p>CyclePoint is a web-based bartering platform. Trade used clothes, gadgets, appliances, and more — no money involved.</p>
      </div>
      <div class="cp-footer-col cp-social">
        <h4>Follow Us</h4>
        <div class="cp-social-links">
          <a href="https://facebook.com" target="_blank"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://instagram.com" target="_blank"><i class="fa-brands fa-instagram"></i></a>
        </div>
      </div>
      <div class="cp-footer-col">
        <h4>Contact Us</h4>
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

<script src="assets/js/post_item.js"></script>
<script src="assets/js/page-transitions.js"></script>
 <script src="assets/js/notification-badges.js"></script>
</body>
</html>
<?php
// post_item_functions.php

/**
 * Start session if not already started
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Initialize post item session state
 */
if (!isset($_SESSION['post_item'])) {
    $_SESSION['post_item'] = [];
}
$state = &$_SESSION['post_item'];

/**
 * Categories (barter-oriented)
 */
function getCategories() {
    return [
        'clothes'    => 'Used Clothes / Shoes / Bags (no underwear/lingerie)',
        'gadgets'    => 'Used Gadgets & Electronics',
        'appliances' => 'Used Home Appliances',
        'furniture'  => 'Furniture & Home Items',
        'food'       => 'Homegrown Food / Goods',
        'tools'      => 'Tools & Equipment',
    ];
}

/**
 * Content moderation rules
 */
function getRules() {
    return [
        'clothes' => [
            'note' => 'No underwear or lingerie (panties, bras, boxers, thongs).',
            'banned_keywords' => [
                'underwear','panty','panties','bra','brassiere','brief','briefs','boxer','boxers',
                'lingerie','thong','g-string','g string','gstring'
            ],
        ],
        // Add more category rules here if needed
    ];
}

/**
 * Sanitize output
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validate input against rules
 */
function violatesRules($category, $title, $desc) {
    $RULES = getRules();
    if (!isset($RULES[$category])) return false;
    $banned = $RULES[$category]['banned_keywords'] ?? [];
    $hay = mb_strtolower($title . ' ' . $desc);
    foreach ($banned as $kw) {
        if (mb_strpos($hay, mb_strtolower($kw)) !== false) return true;
    }
    return false;
}

/**
 * Handle Step 1: Category selection
 */
function handleStep1($postData) {
    global $state;
    $categories = getCategories();
    $cat = $postData['category'] ?? '';
    if (isset($categories[$cat])) {
        $state['category'] = $cat;
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Invalid category selected.'];
}

/**
 * Handle Step 2: Item details + photo uploads
 */
function handleStep2($postData, $files) {
    global $state;

    $state['title']         = trim($postData['title'] ?? '');
    $state['description']   = trim($postData['description'] ?? '');
    $state['location']      = trim($postData['location'] ?? '');
    $state['desired_trade'] = trim($postData['desired_trade'] ?? '');

    // Validate required fields
    if ($state['title'] === '' || $state['description'] === '' || $state['location'] === '' || $state['desired_trade'] === '') {
        return ['success' => false, 'error' => 'Please fill in all required fields.'];
    }

    // Content moderation
    if (($state['category'] ?? '') === 'clothes' && violatesRules('clothes', $state['title'], $state['description'])) {
        return ['success' => false, 'error' => 'Listings in Used Clothes cannot include underwear or lingerie.'];
    }

    // Handle photo uploads
    if (!empty($files['photos']['name'][0])) {
        $maxPhotos = 12;
        $current = count($state['photos'] ?? []);
        $allow = max(0, $maxPhotos - $current);
        if (!isset($state['photos'])) $state['photos'] = [];
        if (!isset($state['photos_rel'])) $state['photos_rel'] = [];

        for ($i = 0; $i < count($files['photos']['name']) && $allow > 0; $i++) {
            if ($files['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmpName = $files['photos']['tmp_name'][$i];
            $orig    = $files['photos']['name'][$i];
            $ext     = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) continue;
            $fname  = uniqid('ph_', true) . '.' . $ext;
            $dirTmp = __DIR__ . '/uploads/tmp/' . session_id();
            if (!is_dir($dirTmp)) mkdir($dirTmp, 0777, true);
            $targetAbs = $dirTmp . '/' . $fname;
            $targetRel = 'uploads/tmp/' . session_id() . '/' . $fname;
            if (@move_uploaded_file($tmpName, $targetAbs)) {
                $state['photos'][] = $targetAbs;
                $state['photos_rel'][] = $targetRel;
                $allow--;
            }
        }
    }

    return ['success' => true];
}

/**
 * Handle Step 3: Final submission to DB
 */
function handleStep3($conn, $userId) {
    global $state;
    $categories = getCategories();

    $cat  = $state['category'] ?? '';
    $tit  = $state['title'] ?? '';
    $desc = $state['description'] ?? '';
    $loc  = $state['location'] ?? '';
    $want = $state['desired_trade'] ?? '';

    // Validate
    if ($tit === '' || $desc === '' || $loc === '' || $want === '' || !isset($categories[$cat])) {
        return ['success' => false, 'error' => 'Missing required fields.'];
    }

    if ($cat === 'clothes' && violatesRules('clothes', $tit, $desc)) {
        return ['success' => false, 'error' => 'Listings in Used Clothes cannot include underwear or lingerie.'];
    }

    // Insert listing
    $sql = "INSERT INTO listings (user_id, title, description, category, location, desired_trade, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('isssss', $userId, $tit, $desc, $cat, $loc, $want);
        $stmt->execute();
        $listing_id = $stmt->insert_id;
        $stmt->close();

        // Move images to permanent folder
        $destDirAbs = __DIR__ . '/uploads/listings/' . $listing_id;
        $destDirRel = 'uploads/listings/' . $listing_id;
        if (!is_dir($destDirAbs)) mkdir($destDirAbs, 0777, true);

        if (!empty($state['photos'])) {
            $ins = $conn->prepare("INSERT INTO listing_images (listing_id, path, sort_order) VALUES (?, ?, ?)");
            foreach ($state['photos'] as $idx => $abs) {
                $ext = pathinfo($abs, PATHINFO_EXTENSION);
                $finalName = sprintf('img_%02d.%s', $idx+1, $ext ?: 'jpg');
                $finalAbs  = $destDirAbs . '/' . $finalName;
                $finalRel  = $destDirRel . '/' . $finalName;
                if (@rename($abs, $finalAbs)) {
                    $ins->bind_param('isi', $listing_id, $finalRel, $idx);
                    $ins->execute();
                }
            }
            $ins->close();
        }

        // Clear session and return success
        $_SESSION['post_item'] = [];
        return ['success' => true, 'listing_id' => $listing_id];
    }

    return ['success' => false, 'error' => 'Database error while posting.'];
}

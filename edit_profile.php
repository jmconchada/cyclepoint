<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, email, profile_picture, area, contact_number, password FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $area           = trim($_POST['area'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $_SESSION['error_message'] = "Name is required";
    } elseif (empty($email)) {
        $_SESSION['error_message'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) $_SESSION['error_message'] = "Email is already registered";
        $check->close();

        $password_update = false;
        if (!isset($_SESSION['error_message']) && (!empty($new_password) || !empty($confirm_password))) {
            if (empty($current_password)) {
                $_SESSION['error_message'] = "Current password is required";
            } elseif (!password_verify($current_password, $user['password'])) {
                $_SESSION['error_message'] = "Current password is incorrect";
            } elseif ($current_password === $new_password) {
                $_SESSION['error_message'] = "New password must differ from current";
            } elseif (strlen($new_password) < 6) {
                $_SESSION['error_message'] = "New password must be at least 6 characters";
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['error_message'] = "New passwords do not match";
            } else {
                $password_update = true;
            }
        }

        $profile_picture = $user['profile_picture'];
        if (!isset($_SESSION['error_message']) && !empty($_POST['cropped_image'])) {
            $img = str_replace(['data:image/png;base64,', ' '], ['', '+'], $_POST['cropped_image']);
            $img = base64_decode($img);
            $dir = 'uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $user_id . '_' . time() . '.png';
            if (file_put_contents($dir . $fname, $img)) {
                if ($profile_picture && file_exists($profile_picture)) unlink($profile_picture);
                $profile_picture = $dir . $fname;
            }
        }

        if (!isset($_SESSION['error_message'])) {
            if ($password_update) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $upd  = $conn->prepare("UPDATE users SET name=?,email=?,profile_picture=?,area=?,contact_number=?,password=? WHERE id=?");
                $upd->bind_param("ssssssi", $name, $email, $profile_picture, $area, $contact_number, $hash, $user_id);
            } else {
                $upd = $conn->prepare("UPDATE users SET name=?,email=?,profile_picture=?,area=?,contact_number=? WHERE id=?");
                $upd->bind_param("sssssi", $name, $email, $profile_picture, $area, $contact_number, $user_id);
            }
            if ($upd->execute()) {
                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: profile.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to update profile";
            }
            $upd->close();
        }
    }
}

$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/images/profile-picture.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — CyclePoint</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <link rel="stylesheet" href="assets/css/edit_profile.css">
</head>
<body>

<!-- ── Topbar ── -->
<header class="topbar">
    <div class="topbar-inner">
        <a href="profile.php" class="btn-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Back to Profile
        </a>
        <div class="topbar-title">Edit Profile</div>
        <div class="topbar-right"></div>
    </div>
</header>

<!-- ── Page Body ── -->
<div class="edit-profile-page">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="edit-container">
        <form method="POST" id="editProfileForm">

            <!-- Profile Picture -->
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-camera"></i> Profile Picture</h2>
                </div>
                <div class="card-body">
                    <div class="profile-picture-wrapper">
                        <div class="current-picture">
                            <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile" id="currentProfilePic">
                        </div>
                        <div class="picture-actions">
                            <button type="button" class="btn-upload" onclick="document.getElementById('imageInput').click()">
                                <i class="fas fa-upload"></i>
                                Change Picture
                            </button>
                            <input type="file" id="imageInput" accept="image/*" style="display:none;">
                            <p class="help-text"><i class="fas fa-info-circle"></i> JPG, PNG or GIF — max 5 MB</p>
                        </div>
                    </div>
                    <input type="hidden" name="cropped_image" id="croppedImage">
                </div>
            </div>

            <!-- Personal Information -->
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-user"></i> Personal Information</h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-signature"></i> Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control"
                                   value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_number"><i class="fas fa-phone"></i> Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-control"
                                   value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>"
                                   placeholder="+63 XXX XXX XXXX">
                        </div>
                        <div class="form-group">
                            <label for="area"><i class="fas fa-map-marker-alt"></i> Location</label>
                            <input type="text" id="area" name="area" class="form-control"
                                   value="<?= htmlspecialchars($user['area'] ?? '') ?>"
                                   placeholder="e.g., Marikina City, Metro Manila">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-lock"></i> Change Password</h2>
                </div>
                <div class="card-body">
                    <p class="info-text"><i class="fas fa-info-circle"></i> Leave blank if you don't want to change your password.</p>
                    <div class="form-group">
                        <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="current_password" name="current_password" class="form-control">
                            <button type="button" class="password-toggle" onclick="togglePw('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 characters">
                                <button type="button" class="password-toggle" onclick="togglePw('new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                <button type="button" class="password-toggle" onclick="togglePw('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Crop Modal -->
<div id="cropModal" class="modal">
    <div class="modal-overlay" onclick="closeCropModal()"></div>
    <div class="modal-content">
        <div class="modal-strip"></div>
        <div class="modal-header">
            <h3><i class="fas fa-crop-alt"></i> Crop Profile Picture</h3>
            <button class="modal-close" onclick="closeCropModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="crop-container">
                <img id="cropImage" src="" alt="Crop">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCropModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="applyCrop()">
                <i class="fas fa-check"></i> Apply Crop
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
let cropper = null;

document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
        document.getElementById('cropImage').src = ev.target.result;
        openCropModal();
    };
    reader.readAsDataURL(file);
});

function openCropModal() {
    const modal = document.getElementById('cropModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
    document.body.style.overflow = 'hidden';
    const image = document.getElementById('cropImage');
    if (cropper) { cropper.destroy(); }
    cropper = new Cropper(image, {
        aspectRatio: 1, viewMode: 1, dragMode: 'move',
        autoCropArea: 1, restore: false, guides: true,
        center: true, highlight: false, cropBoxMovable: true,
        cropBoxResizable: true, toggleDragModeOnDblclick: false,
    });
}
function closeCropModal() {
    const modal = document.getElementById('cropModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        if (cropper) { cropper.destroy(); cropper = null; }
    }, 300);
}
function applyCrop() {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' });
    const data   = canvas.toDataURL('image/png');
    document.getElementById('currentProfilePic').src = data;
    document.getElementById('croppedImage').value    = data;
    closeCropModal();
}
function togglePw(id, btn) {
    const f = document.getElementById(id);
    const i = btn.querySelector('i');
    if (f.type === 'password') {
        f.type = 'text';
        i.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        f.type = 'password';
        i.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCropModal(); });
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => el.remove(), 300);
        }, 5000);
    });
});
</script>
</body>
</html>
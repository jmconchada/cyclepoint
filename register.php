<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $contact_number   = trim($_POST['contact'] ?? '');
    $area             = trim($_POST['location'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms            = isset($_POST['terms']);

    if (!$name || !$email || !$contact_number || !$area || !$password || !$confirm_password) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!$terms) {
        $error = "You must agree to the Terms and Conditions.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, contact_number, area, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $name, $email, $contact_number, $area, $hashed);

        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit;
        } else {
            $error = "Registration failed. Email may already be in use.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — CyclePoint</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --forest:  #1a3c2e;
  --emerald: #2d6a4f;
  --sage:    #40916c;
  --mint:    #74c69d;
  --pale:    #d8f3dc;
  --pale-2:  #edf7f0;
  --white:   #ffffff;
  --ink:     #0d1f17;
  --ink-2:   #253d2f;
  --ink-3:   #527060;
  --ink-4:   #94b0a0;
  --border:  #cce8d4;
  --red:     #e63946;
  --sh-md: 0 8px 32px rgba(13,31,23,.13);
  --ease: cubic-bezier(.4,0,.2,1);
}

html, body {
  min-height: 100vh;
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}

body {
  display: flex;
  background: var(--pale-2);
}

/* Left panel */
.auth-side {
  display: none;
  width: 38%;
  background: linear-gradient(160deg, var(--forest) 0%, var(--emerald) 55%, var(--sage) 100%);
  position: relative; overflow: hidden;
  flex-direction: column; align-items: center;
  justify-content: center; padding: 60px 44px;
}
@media (min-width: 960px) { .auth-side { display: flex; } }
.auth-side::before {
  content: ''; position: absolute; top: -80px; right: -80px;
  width: 340px; height: 340px;
  background: radial-gradient(circle, rgba(116,198,157,.18) 0%, transparent 70%);
}
.side-logo img { height: 76px; filter: brightness(0) invert(1); margin-bottom: 36px; }
.side-tagline { position: relative; z-index: 1; text-align: center; }
.side-tagline h2 {
  font-family: 'Fraunces', Georgia, serif;
  font-size: 29px; font-weight: 700;
  color: white; line-height: 1.3; margin-bottom: 12px; letter-spacing: -.02em;
}
.side-tagline p { font-size: 14px; color: rgba(255,255,255,.75); line-height: 1.7; }
.side-steps {
  position: relative; z-index: 1; margin-top: 36px;
  display: flex; flex-direction: column; gap: 12px; width: 100%;
}
.side-step {
  display: flex; align-items: center; gap: 12px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 12px; padding: 12px 16px;
}
.step-num {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--mint); color: var(--forest);
  font-size: 12px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.side-step span { font-size: 13px; color: rgba(255,255,255,.9); font-weight: 600; }

/* Form wrap */
.auth-form-wrap {
  flex: 1; display: flex; align-items: center; justify-content: center;
  padding: 32px 20px; overflow-y: auto;
}

.auth-card {
  background: var(--white);
  border-radius: 24px; border: 1px solid var(--border);
  box-shadow: var(--sh-md);
  padding: 40px 36px;
  width: 100%; max-width: 460px;
  animation: fadeUp .4s var(--ease) both;
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(18px); }
  to   { opacity:1; transform:translateY(0); }
}

.mobile-logo {
  display: flex; flex-direction: column; align-items: center; margin-bottom: 24px;
}
@media (min-width: 960px) { .mobile-logo { display: none; } }
.mobile-logo img { height: 52px; margin-bottom: 8px; }

.card-title {
  font-family: 'Fraunces', Georgia, serif;
  font-size: 24px; font-weight: 700;
  color: var(--ink); letter-spacing: -.02em; margin-bottom: 4px;
}
.card-subtitle { font-size: 13.5px; color: var(--ink-4); margin-bottom: 26px; }

/* Error */
.error-box {
  background: #fff5f5; border: 1px solid #fecaca;
  border-left: 3px solid var(--red);
  border-radius: 10px; padding: 12px 16px;
  margin-bottom: 18px; font-size: 13px; color: #dc2626;
  font-weight: 600; display: flex; align-items: center; gap: 8px;
}

/* Fields */
.field { margin-bottom: 15px; }
.field label {
  display: block; font-size: 11.5px; font-weight: 800;
  color: var(--ink-3); text-transform: uppercase;
  letter-spacing: .7px; margin-bottom: 6px;
}
.input-wrap { position: relative; display: flex; align-items: center; }
.input-wrap i.prefix {
  position: absolute; left: 14px;
  color: var(--ink-4); font-size: 14px; pointer-events: none;
}
.input-wrap input {
  width: 100%; padding: 12px 42px;
  background: var(--pale-2); border: 1.5px solid var(--border);
  border-radius: 11px; font-size: 13.5px;
  font-family: inherit; color: var(--ink);
  outline: none; transition: all .2s var(--ease);
}
.input-wrap input:focus {
  border-color: var(--sage); background: var(--white);
  box-shadow: 0 0 0 3px rgba(64,145,108,.12);
}
.input-wrap input::placeholder { color: var(--ink-4); font-weight: 400; }
.toggle-btn {
  position: absolute; right: 13px;
  background: none; border: none; cursor: pointer;
  color: var(--ink-4); font-size: 14px; padding: 0; transition: color .2s;
}
.toggle-btn:hover { color: var(--sage); }

/* Two-col grid */
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 500px) { .field-row { grid-template-columns: 1fr; } }

/* Terms */
.terms-check {
  display: flex; align-items: center; gap: 10px;
  margin: 16px 0 4px; cursor: pointer;
}
.terms-check input[type=checkbox] {
  width: 18px; height: 18px; accent-color: var(--sage);
  cursor: pointer; flex-shrink: 0;
}
.terms-check label {
  font-size: 13.5px; color: var(--ink-3); cursor: pointer; line-height: 1.5;
}
.terms-check a {
  color: var(--sage); font-weight: 700; text-decoration: none;
}
.terms-check a:hover { text-decoration: underline; }

/* Submit */
.btn-submit {
  width: 100%; height: 50px; margin-top: 18px;
  background: linear-gradient(135deg, var(--forest), var(--sage));
  color: white; border: none; border-radius: 12px;
  font-size: 14.5px; font-weight: 800; font-family: inherit;
  cursor: pointer; letter-spacing: .3px;
  box-shadow: 0 4px 16px rgba(45,106,79,.3);
  transition: all .2s var(--ease);
  position: relative; overflow: hidden;
}
.btn-submit::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.14), transparent);
  transform: translateX(-100%); transition: transform .5s var(--ease);
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(45,106,79,.4); }
.btn-submit:hover::after { transform: translateX(100%); }

.auth-links {
  text-align: center; margin-top: 18px; font-size: 13.5px; color: var(--ink-4);
}
.auth-links a { color: var(--sage); font-weight: 700; text-decoration: none; }
.auth-links a:hover { text-decoration: underline; }

/* ── TERMS MODAL ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.55); backdrop-filter: blur(6px);
  z-index: 9999; align-items: center; justify-content: center;
  padding: 20px; opacity: 0; transition: opacity .25s var(--ease);
}
.modal-overlay.show { opacity: 1; }

.modal-box {
  background: var(--white); border-radius: 20px;
  max-width: 640px; width: 100%; max-height: 86vh;
  overflow: hidden; display: flex; flex-direction: column;
  box-shadow: 0 24px 64px rgba(13,31,23,.22);
  border: 1px solid var(--border);
  transform: scale(.94) translateY(12px);
  transition: transform .25s var(--ease);
}
.modal-overlay.show .modal-box { transform: scale(1) translateY(0); }

/* Modal header */
.modal-head {
  background: linear-gradient(135deg, var(--forest), var(--sage));
  padding: 22px 28px;
  display: flex; align-items: center; gap: 14px;
  position: relative; flex-shrink: 0;
}
.modal-head-icon {
  width: 46px; height: 46px;
  background: rgba(255,255,255,.18); border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: white; flex-shrink: 0;
}
.modal-head h2 {
  font-family: 'Fraunces', Georgia, serif;
  font-size: 20px; font-weight: 700; color: white;
  letter-spacing: -.01em; flex: 1;
}
.modal-head p { font-size: 13px; color: rgba(255,255,255,.75); margin-top: 2px; }
.modal-close {
  position: absolute; top: 14px; right: 14px;
  width: 32px; height: 32px; border-radius: 50%;
  background: rgba(255,255,255,.18); border: 1.5px solid rgba(255,255,255,.3);
  color: white; font-size: 15px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all .2s; flex-shrink: 0;
}
.modal-close:hover { background: rgba(255,255,255,.32); transform: rotate(90deg); }

/* Tab nav */
.modal-tabs {
  display: flex; border-bottom: 1px solid var(--border);
  background: var(--pale-2); flex-shrink: 0;
}
.tab-btn {
  flex: 1; padding: 13px 16px; border: none; background: none;
  font-family: inherit; font-size: 13px; font-weight: 700;
  color: var(--ink-4); cursor: pointer; transition: all .2s;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab-btn.active { color: var(--sage); border-bottom-color: var(--sage); background: var(--white); }
.tab-btn:hover:not(.active) { color: var(--ink-3); background: var(--pale); }

/* Modal body */
.modal-body {
  flex: 1; overflow-y: auto; padding: 28px;
  scrollbar-width: thin; scrollbar-color: var(--border) transparent;
}
.modal-body::-webkit-scrollbar { width: 5px; }
.modal-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

.tab-content { display: none; }
.tab-content.active { display: block; }

.terms-section { margin-bottom: 22px; }
.terms-section h3 {
  font-size: 13.5px; font-weight: 800;
  color: var(--emerald); text-transform: uppercase;
  letter-spacing: .7px; margin-bottom: 8px;
  display: flex; align-items: center; gap: 8px;
}
.terms-section h3 i { color: var(--sage); font-size: 13px; }
.terms-section p {
  font-size: 14px; color: var(--ink-3);
  line-height: 1.75; padding-left: 22px;
}

/* Modal footer */
.modal-foot {
  padding: 16px 28px; border-top: 1px solid var(--border);
  display: flex; gap: 10px; background: var(--pale-2); flex-shrink: 0;
}
.btn-accept {
  flex: 2; height: 44px;
  background: linear-gradient(135deg, var(--forest), var(--sage));
  color: white; border: none; border-radius: 10px;
  font-family: inherit; font-size: 14px; font-weight: 700;
  cursor: pointer; transition: all .2s;
  box-shadow: 0 3px 12px rgba(45,106,79,.28);
}
.btn-accept:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(45,106,79,.38); }
.btn-cancel {
  flex: 1; height: 44px;
  background: var(--white); color: var(--ink-3);
  border: 1.5px solid var(--border); border-radius: 10px;
  font-family: inherit; font-size: 14px; font-weight: 700;
  cursor: pointer; transition: all .2s;
}
.btn-cancel:hover { background: var(--pale-2); border-color: var(--border); }
</style>
</head>
<body>

<!-- Left panel -->
<div class="auth-side">
  <div class="side-logo" style="position:relative;z-index:1;">
    <img src="assets/images/logo.png" alt="CyclePoint">
  </div>
  <div class="side-tagline">
    <h2>Join the Green<br>Trading Community</h2>
    <p>Sign up in minutes and start swapping items you no longer need for things you do.</p>
  </div>
  <div class="side-steps">
    <div class="side-step"><div class="step-num">1</div><span>Create your free account</span></div>
    <div class="side-step"><div class="step-num">2</div><span>Post items you want to trade</span></div>
    <div class="side-step"><div class="step-num">3</div><span>Connect and barter with others</span></div>
  </div>
</div>

<!-- Form -->
<div class="auth-form-wrap">
  <div class="auth-card">

    <div class="mobile-logo">
      <img src="assets/images/logo.png" alt="CyclePoint">
      <span style="font-family:'Fraunces',serif;font-size:20px;font-weight:700;color:var(--forest);">CyclePoint</span>
    </div>

    <h1 class="card-title">Create your account</h1>
    <p class="card-subtitle">It's free — start trading sustainably today</p>

    <?php if (!empty($error)): ?>
    <div class="error-box">
      <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate onsubmit="return checkTerms()">

      <div class="field">
        <label>Full Name</label>
        <div class="input-wrap">
          <i class="fas fa-user prefix"></i>
          <input type="text" name="name" placeholder="Your full name"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
      </div>

      <div class="field">
        <label>Email Address</label>
        <div class="input-wrap">
          <i class="fas fa-envelope prefix"></i>
          <input type="email" name="email" placeholder="you@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Contact Number</label>
          <div class="input-wrap">
            <i class="fas fa-phone prefix"></i>
            <input type="text" name="contact" placeholder="09XXXXXXXXX"
                   value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" required>
          </div>
        </div>
        <div class="field">
          <label>Location</label>
          <div class="input-wrap">
            <i class="fas fa-map-marker-alt prefix"></i>
            <input type="text" name="location" placeholder="Your city/area"
                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
          </div>
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock prefix"></i>
          <input type="password" name="password" id="pwd1" placeholder="Min. 8 characters" required>
          <button type="button" class="toggle-btn" onclick="togglePwd('pwd1','ic1')">
            <i class="fas fa-eye" id="ic1"></i>
          </button>
        </div>
      </div>

      <div class="field">
        <label>Confirm Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock prefix"></i>
          <input type="password" name="confirm_password" id="pwd2" placeholder="Repeat your password" required>
          <button type="button" class="toggle-btn" onclick="togglePwd('pwd2','ic2')">
            <i class="fas fa-eye" id="ic2"></i>
          </button>
        </div>
      </div>

      <div class="terms-check">
        <input type="checkbox" name="terms" id="termsChk">
        <label for="termsChk">
          I agree to the <a href="#" id="openTerms">Terms &amp; Conditions</a> and <a href="#" id="openPrivacy">Privacy Policy</a>
        </label>
      </div>

      <button type="submit" class="btn-submit">
        <i class="fas fa-user-plus"></i>&nbsp; Create Account
      </button>
    </form>

    <div class="auth-links">
      <p>Already have an account? <a href="login.php">Sign in here</a></p>
    </div>
  </div>
</div>

<!-- ── TERMS & PRIVACY MODAL ── -->
<div class="modal-overlay" id="termsModal">
  <div class="modal-box">

    <div class="modal-head">
      <div class="modal-head-icon"><i class="fas fa-file-shield"></i></div>
      <div>
        <h2>Legal Documents</h2>
        <p>Please read carefully before creating your account</p>
      </div>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="modal-tabs">
      <button class="tab-btn active" onclick="switchTab('terms', this)">
        <i class="fas fa-scroll"></i> Terms &amp; Conditions
      </button>
      <button class="tab-btn" onclick="switchTab('privacy', this)">
        <i class="fas fa-user-shield"></i> Privacy Policy
      </button>
    </div>

    <div class="modal-body">

      <!-- Terms tab -->
      <div class="tab-content active" id="tab-terms">
        <div class="terms-section">
          <h3><i class="fas fa-circle-info"></i> Welcome to CyclePoint</h3>
          <p>By creating an account or using our platform, you agree to these Terms. Please read carefully before accepting.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-user-check"></i> 1. Eligibility</h3>
          <p>You must be at least 18 years old to use CyclePoint. All account information must be accurate and truthful.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-handshake"></i> 2. User Responsibilities</h3>
          <p>You are responsible for the security of your account and any activity conducted under it. You must not trade illegal, harmful, or counterfeit items on the platform.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-arrows-left-right"></i> 3. Trades &amp; Transactions</h3>
          <p>CyclePoint provides a platform for users to connect and arrange trades. We are not responsible for the quality, safety, or legality of items exchanged. Disputes must be resolved between users; CyclePoint may intervene if rules are violated.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-ban"></i> 4. Prohibited Conduct</h3>
          <p>Harassment, scams, impersonation, fraudulent activity, or any attempt to hack or exploit the platform are strictly prohibited and may result in immediate account suspension.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-gavel"></i> 5. Account Suspension</h3>
          <p>CyclePoint reserves the right to suspend or permanently ban any accounts that violate these Terms, at our sole discretion.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-shield"></i> 6. Limitation of Liability</h3>
          <p>CyclePoint is not liable for any losses, damages, or disputes arising from trades or user behavior. The platform is used at your own risk.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-rotate"></i> 7. Updates</h3>
          <p>We may update these Terms from time to time. Continued use of the platform constitutes acceptance of the revised Terms.</p>
        </div>
      </div>

      <!-- Privacy tab -->
      <div class="tab-content" id="tab-privacy">
        <div class="terms-section">
          <h3><i class="fas fa-circle-info"></i> Our Commitment to Privacy</h3>
          <p>CyclePoint values your privacy. This Privacy Policy explains how we collect, use, and protect your personal information.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-database"></i> 1. Information We Collect</h3>
          <p>We collect basic account details such as your name, email, and contact information. We also collect activity data including login history, trade activity, and messages.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-gears"></i> 2. How We Use Your Information</h3>
          <p>We use your information to manage accounts, provide platform services, verify users, prevent fraud, and send important updates or notifications.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-share-nodes"></i> 3. Data Sharing</h3>
          <p>We do not sell your personal data. We may share information only when required by law or to protect users from fraud or abuse.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-cookie"></i> 4. Cookies &amp; Tracking</h3>
          <p>CyclePoint uses cookies to support login sessions, perform analytics, and improve user experience. You may disable cookies in your browser settings.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-user-gear"></i> 5. Your Rights</h3>
          <p>You may request access, correction, or deletion of your account and data at any time. Deleted accounts may retain minimal records for security and compliance purposes.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-lock"></i> 6. Data Security</h3>
          <p>We implement reasonable security measures to protect your data. However, no online platform can be guaranteed 100% secure. You use the platform at your own risk.</p>
        </div>
        <div class="terms-section">
          <h3><i class="fas fa-rotate"></i> 7. Policy Updates</h3>
          <p>This Privacy Policy may be updated periodically. Updates will be posted on our platform. Continued use constitutes acceptance of the updated policy.</p>
        </div>
      </div>

    </div>

    <div class="modal-foot">
      <button class="btn-cancel" onclick="closeModal()">Decline</button>
      <button class="btn-accept" onclick="acceptTerms()">
        <i class="fas fa-check"></i> I Accept — Continue
      </button>
    </div>
  </div>
</div>

<script>
// Password toggles
function togglePwd(id, iconId) {
  const f = document.getElementById(id);
  const i = document.getElementById(iconId);
  if (f.type === 'password') { f.type = 'text'; i.className = 'fas fa-eye-slash'; }
  else { f.type = 'password'; i.className = 'fas fa-eye'; }
}

// Modal
const modal = document.getElementById('termsModal');

function openModal(tab) {
  modal.style.display = 'flex';
  setTimeout(() => modal.classList.add('show'), 10);
  if (tab) switchTab(tab, document.querySelector(`.tab-btn:${tab === 'privacy' ? 'last-child' : 'first-child'}`));
}
function closeModal() {
  modal.classList.remove('show');
  setTimeout(() => modal.style.display = 'none', 250);
}
function acceptTerms() {
  document.getElementById('termsChk').checked = true;
  closeModal();
}

function switchTab(tabId, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tabId).classList.add('active');
  btn.classList.add('active');
}

// Triggers
document.getElementById('openTerms').addEventListener('click', e => { e.preventDefault(); openModal('terms'); });
document.getElementById('openPrivacy').addEventListener('click', e => { e.preventDefault(); openModal('privacy'); });
document.getElementById('termsChk').addEventListener('change', function() {
  if (this.checked) openModal('terms');
});

// Close on overlay click
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Validate before submit
function checkTerms() {
  if (!document.getElementById('termsChk').checked) {
    openModal('terms');
    return false;
  }
  return true;
}
</script>
</body>
</html>
<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $name, $hashed, $role);

        if ($stmt->fetch()) {
            if (password_verify($password, $hashed)) {
                $_SESSION['user_id']   = $id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role ?? 'user';

                if ($role === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No account found with that email.";
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
<title>Login — CyclePoint</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
  --forest:#1a3c2e; --emerald:#2d6a4f; --sage:#40916c; --mint:#74c69d;
  --pale:#d8f3dc; --pale-2:#edf7f0; --white:#ffffff;
  --ink:#0d1f17; --ink-2:#253d2f; --ink-3:#527060; --ink-4:#94b0a0;
  --border:#cce8d4; --red:#e63946;
}

html, body { min-height:100vh; font-family:'Plus Jakarta Sans',system-ui,sans-serif; -webkit-font-smoothing:antialiased; }

body { display:flex; background:var(--pale-2); }

/* Left decorative panel */
.auth-side {
  display:none; width:42%;
  background:linear-gradient(160deg,var(--forest) 0%,var(--emerald) 55%,var(--sage) 100%);
  position:relative; overflow:hidden;
  flex-direction:column; align-items:center; justify-content:center; padding:60px 48px;
}
@media(min-width:900px){ .auth-side{ display:flex; } }
.auth-side::before {
  content:''; position:absolute; top:-80px; right:-80px;
  width:360px; height:360px;
  background:radial-gradient(circle,rgba(116,198,157,.18) 0%,transparent 70%);
}

.side-logo { position:relative; z-index:1; margin-bottom:36px; }
.side-logo img { height:80px; filter:brightness(0) invert(1); }

.side-tagline { position:relative; z-index:1; text-align:center; }
.side-tagline h2 {
  font-family:'Fraunces',Georgia,serif; font-size:32px; font-weight:700;
  color:white; line-height:1.25; margin-bottom:14px; letter-spacing:-.02em;
}
.side-tagline p { font-size:15px; color:rgba(255,255,255,.75); line-height:1.7; font-weight:500; }

.side-features { position:relative; z-index:1; margin-top:40px; display:flex; flex-direction:column; gap:14px; width:100%; }
.side-feature {
  display:flex; align-items:center; gap:13px;
  background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15);
  border-radius:12px; padding:13px 16px; backdrop-filter:blur(6px);
}
.side-feature i { color:var(--mint); font-size:18px; flex-shrink:0; }
.side-feature span { font-size:13.5px; color:rgba(255,255,255,.9); font-weight:600; }

/* Right form panel */
.auth-form-wrap {
  flex:1; display:flex; align-items:center; justify-content:center;
  padding:32px 24px; background:var(--pale-2);
}

.auth-card {
  background:var(--white); border-radius:24px;
  border:1px solid var(--border); box-shadow:0 8px 32px rgba(13,31,23,.13);
  padding:44px 40px; width:100%; max-width:420px;
  animation:fadeUp .4s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

.mobile-logo { display:flex; flex-direction:column; align-items:center; margin-bottom:28px; }
@media(min-width:900px){ .mobile-logo{ display:none; } }
.mobile-logo img { height:56px; margin-bottom:10px; }

.card-title { font-family:'Fraunces',Georgia,serif; font-size:26px; font-weight:700; color:var(--ink); letter-spacing:-.02em; margin-bottom:6px; }
.card-subtitle { font-size:14px; color:var(--ink-4); font-weight:500; margin-bottom:30px; }

.error-box {
  background:#fff5f5; border:1px solid #fecaca; border-left:3px solid var(--red);
  border-radius:10px; padding:12px 16px; margin-bottom:20px;
  font-size:13.5px; color:#dc2626; font-weight:600;
  display:flex; align-items:center; gap:8px;
}

.field { margin-bottom:18px; }
.field label { display:block; font-size:12px; font-weight:800; color:var(--ink-3); text-transform:uppercase; letter-spacing:.7px; margin-bottom:7px; }

/* Input wrapper — the key fix */
.input-wrap {
  position:relative;
  display:flex;
  align-items:center;
}
.input-wrap .prefix-icon {
  position:absolute; left:14px;
  color:var(--ink-4); font-size:15px;
  pointer-events:none; z-index:1;
}
.input-wrap input {
  width:100%;
  padding:13px 14px 13px 42px;   /* normal right padding */
  background:var(--pale-2); border:1.5px solid var(--border);
  border-radius:12px; font-size:14px;
  font-family:inherit; color:var(--ink); outline:none;
  transition:border-color .2s, box-shadow .2s, background .2s;
}
/* Password field gets extra right padding for the eye */
.input-wrap.has-toggle input {
  padding-right:46px;
}
.input-wrap input:focus {
  border-color:var(--sage); background:var(--white);
  box-shadow:0 0 0 3px rgba(64,145,108,.12);
}
.input-wrap input::placeholder { color:var(--ink-4); font-weight:400; }

/* Eye toggle — strictly inside the box */
.toggle-btn {
  position:absolute;
  right:14px;           /* inside the input border */
  top:50%;
  transform:translateY(-50%);
  background:none; border:none; cursor:pointer;
  color:var(--ink-4); font-size:15px;
  padding:0; line-height:1;
  display:flex; align-items:center; justify-content:center;
  z-index:2;
  transition:color .2s;
}
.toggle-btn:hover { color:var(--sage); }

.btn-submit {
  width:100%; height:50px; margin-top:8px;
  background:linear-gradient(135deg,var(--forest),var(--sage));
  color:white; border:none; border-radius:12px;
  font-size:15px; font-weight:800; font-family:inherit; cursor:pointer;
  letter-spacing:.3px; box-shadow:0 4px 16px rgba(45,106,79,.3);
  transition:transform .2s, box-shadow .2s;
}
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(45,106,79,.4); }

.auth-links { text-align:center; margin-top:22px; display:flex; flex-direction:column; gap:8px; }
.auth-links p { font-size:13.5px; color:var(--ink-4); }
.auth-links a { color:var(--sage); font-weight:700; text-decoration:none; }
.auth-links a:hover { color:var(--forest); text-decoration:underline; }
</style>
</head>
<body>

<!-- Left panel -->
<div class="auth-side">
  <div class="side-logo">
    <img src="assets/images/logo.png" alt="CyclePoint Logo">
  </div>
  <div class="side-tagline">
    <h2>Trade Smarter,<br>Live Greener.</h2>
    <p>CyclePoint connects people who want to swap their unused items — no money, just community.</p>
  </div>
  <div class="side-features">
    <div class="side-feature"><i class="fas fa-handshake"></i><span>Barter — no cash needed</span></div>
    <div class="side-feature"><i class="fas fa-leaf"></i><span>Reduce waste, help the planet</span></div>
    <div class="side-feature"><i class="fas fa-shield-halved"></i><span>Safe &amp; verified community</span></div>
  </div>
</div>

<!-- Right form panel -->
<div class="auth-form-wrap">
  <div class="auth-card">

    <div class="mobile-logo">
      <img src="assets/images/logo.png" alt="CyclePoint">
      <span style="font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:var(--forest);">CyclePoint</span>
    </div>

    <h1 class="card-title">Welcome back</h1>
    <p class="card-subtitle">Sign in to your CyclePoint account</p>

    <?php if (!empty($error)): ?>
    <div class="error-box">
      <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>

      <div class="field">
        <label>Email Address</label>
        <div class="input-wrap">
          <i class="fas fa-envelope prefix-icon"></i>
          <input type="email" name="email" placeholder="you@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="input-wrap has-toggle">
          <i class="fas fa-lock prefix-icon"></i>
          <input type="password" name="password" id="pwdInput" placeholder="Enter your password" required>
          <button type="button" class="toggle-btn" onclick="togglePwd()">
            <i class="fas fa-eye" id="pwdIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <i class="fas fa-arrow-right-to-bracket"></i>&nbsp; Sign In
      </button>
    </form>

    <div class="auth-links">
      <p>Don't have an account? <a href="register.php">Create one free</a></p>
      <p><a href="password_recovery.php">Forgot your password?</a></p>
    </div>

  </div>
</div>

<script>
function togglePwd() {
  const i = document.getElementById('pwdInput');
  const ic = document.getElementById('pwdIcon');
  i.type = i.type === 'password' ? 'text' : 'password';
  ic.className = i.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
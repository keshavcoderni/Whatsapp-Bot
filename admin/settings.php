<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    header('Location: login.php');
    exit();
}

$adminId = (string) ($_SESSION['admin_id'] ?? $_SESSION['admin_name'] ?? 'Admin');
$adminName = (string) ($_SESSION['admin_name'] ?? $adminId);
$avatarInitial = strtoupper(substr($adminName, 0, 1));
$loginTime = isset($_SESSION['login_time']) ? date('d M Y, h:i A', (int) $_SESSION['login_time']) : 'Current session';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | InfoTag</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/theme.js"></script>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { min-height: 100vh; padding: 32px 20px; color: #f8fafc; background: radial-gradient(circle at top left, #1e293b, #0f172a 48%, #020617); transition: background .25s, color .25s; }
        .settings-card { width: min(100%, 720px); margin: 0 auto; padding: 32px; border: 1px solid rgba(255,255,255,.09); border-radius: 28px; background: rgba(17,24,39,.84); box-shadow: 0 20px 55px rgba(0,0,0,.35); backdrop-filter: blur(18px); animation: fadeUp .45s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        .settings-header { display: flex; justify-content: space-between; align-items: center; gap: 18px; padding-bottom: 22px; border-bottom: 1px solid rgba(255,255,255,.1); }
        .settings-title { display: flex; align-items: center; gap: 14px; }
        .title-icon { display: grid; width: 52px; height: 52px; place-items: center; border-radius: 16px; color: #ddd6fe; background: rgba(124,58,237,.2); font-size: 22px; }
        h1 { font-size: clamp(24px, 4vw, 30px); }
        .admin-badge { display: flex; align-items: center; gap: 8px; padding: 8px 13px; border-radius: 999px; color: #c4b5fd; background: rgba(124,58,237,.16); font-size: 13px; }
        .section-label { margin: 27px 0 12px; color: #94a3b8; font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
        .profile-card, .setting-box { border: 1px solid rgba(255,255,255,.07); border-radius: 20px; background: #1e293b; }
        .profile-card { display: flex; align-items: center; gap: 18px; padding: 20px; }
        .avatar { display: grid; width: 68px; height: 68px; flex: 0 0 68px; place-items: center; border: 3px solid rgba(255,255,255,.16); border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #2563eb); color: white; font-size: 25px; font-weight: 700; box-shadow: 0 8px 20px rgba(37,99,235,.25); }
        .profile-copy { min-width: 0; flex: 1; }
        .profile-copy h2 { overflow: hidden; font-size: 19px; text-overflow: ellipsis; white-space: nowrap; }
        .profile-copy p { margin-top: 3px; color: #94a3b8; font-size: 13px; }
        .verified { display: inline-flex; align-items: center; gap: 5px; margin-top: 9px; color: #86efac; font-size: 12px; font-weight: 600; }
        .profile-details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
        .detail { padding: 12px; border: 1px solid rgba(255,255,255,.06); border-radius: 14px; background: rgba(15,23,42,.42); }
        .detail span { display: block; color: #94a3b8; font-size: 11px; }
        .detail strong { display: block; margin-top: 3px; overflow: hidden; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .setting-box { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; transition: transform .2s, background .2s; }
        .setting-box:hover { background: #2a3850; transform: translateY(-2px); }
        .setting-left { display: flex; align-items: center; gap: 14px; }
        .setting-left > i { display: grid; width: 46px; height: 46px; place-items: center; border-radius: 14px; color: #93c5fd; background: rgba(255,255,255,.06); }
        .setting-info h3 { font-size: 16px; }
        .setting-info p { margin-top: 3px; color: #94a3b8; font-size: 13px; }
        .switch { position: relative; width: 62px; height: 33px; flex: 0 0 62px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; inset: 0; border-radius: 99px; cursor: pointer; background: #475569; transition: .25s; }
        .slider::before { position: absolute; top: 4px; left: 4px; width: 25px; height: 25px; border-radius: 50%; background: white; content: ''; transition: .25s; }
        input:checked + .slider { background: #7c3aed; }
        input:checked + .slider::before { transform: translateX(29px); }
        .action-buttons { display: flex; gap: 12px; margin-top: 27px; }
        .button { display: inline-flex; flex: 1; min-height: 51px; align-items: center; justify-content: center; gap: 9px; border: 0; border-radius: 999px; color: white; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; transition: transform .2s, box-shadow .2s; }
        .button:hover { transform: translateY(-2px); }
        .back-btn { background: #334155; }.save-btn { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }.save-btn:hover { box-shadow: 0 9px 22px rgba(124,58,237,.35); }
        .logout-link { display: inline-flex; align-items: center; gap: 7px; justify-content: center; width: 100%; margin-top: 17px; color: #fca5a5; font-size: 13px; text-decoration: none; }
        body.light-mode { color: #111827; background: #f1f5f9; }.light-mode .settings-card { background: rgba(255,255,255,.9); border-color: rgba(15,23,42,.08); }.light-mode .settings-header { border-color: rgba(15,23,42,.1); }.light-mode .profile-card, .light-mode .setting-box { border-color: rgba(15,23,42,.07); background: #e8eef7; }.light-mode .detail { border-color: rgba(15,23,42,.07); background: rgba(255,255,255,.58); }.light-mode .profile-copy p, .light-mode .setting-info p, .light-mode .detail span, .light-mode .section-label { color: #475569; }.light-mode .back-btn { color: #111827; background: #cbd5e1; }
        @media (max-width: 620px) { body { padding: 16px; }.settings-card { padding: 23px; border-radius: 23px; }.settings-header { align-items: flex-start; flex-direction: column; }.profile-card { align-items: flex-start; }.profile-details { grid-template-columns: 1fr; }.action-buttons { flex-direction: column; } }
    </style>
</head>
<body>
    <main class="settings-card">
        <header class="settings-header">
            <div class="settings-title"><span class="title-icon"><i class="fas fa-sliders-h"></i></span><h1>Settings</h1></div>
            <div class="admin-badge"><i class="fas fa-shield-halved"></i> Admin Panel</div>
        </header>

        <p class="section-label">Your account</p>
        <section class="profile-card" aria-label="Logged-in administrator profile">
            <div class="avatar" title="<?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="profile-copy">
                <h2><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></h2>
                <p>Administrator account</p>
                <span class="verified"><i class="fas fa-circle-check"></i> Authenticated session</span>
            </div>
        </section>
        <div class="profile-details">
            <div class="detail"><span>Login ID</span><strong><?= htmlspecialchars($adminId, ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="detail"><span>Access level</span><strong>Administrator</strong></div>
            <div class="detail"><span>Signed in</span><strong><?= htmlspecialchars($loginTime, ENT_QUOTES, 'UTF-8') ?></strong></div>
        </div>

        <p class="section-label">Appearance</p>
        <section class="setting-box">
            <div class="setting-left"><i class="fas fa-moon"></i><div class="setting-info"><h3>Dark / Light Mode</h3><p>Change the dashboard appearance</p></div></div>
            <label class="switch"><input type="checkbox" id="themeToggle" aria-label="Enable light mode"><span class="slider"></span></label>
        </section>

        <div class="action-buttons">
            <a href="dashboard.php" class="button back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <button type="button" class="button save-btn" id="saveBtn"><i class="fas fa-check-circle"></i> Save Settings</button>
        </div>
        <a href="logout.php" class="logout-link" onclick="return confirm('Are you sure you want to log out?')"><i class="fas fa-right-from-bracket"></i> Log out securely</a>
    </main>
    <script>
        const toggle = document.getElementById('themeToggle');
        const saveButton = document.getElementById('saveBtn');
        toggle.checked = window.InfoTagTheme.current() === 'light';
        toggle.addEventListener('change', () => {
            const theme = toggle.checked ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            window.InfoTagTheme.apply(theme);
        });
        saveButton.addEventListener('click', () => {
            localStorage.setItem('theme', toggle.checked ? 'light' : 'dark');
            window.InfoTagTheme.apply(toggle.checked ? 'light' : 'dark');
            saveButton.innerHTML = '<i class="fas fa-check"></i> Saved';
            setTimeout(() => { saveButton.innerHTML = '<i class="fas fa-check-circle"></i> Save Settings'; }, 1500);
        });
    </script>
</body>
</html>

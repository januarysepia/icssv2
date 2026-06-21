<?php
$user_id = intval($_SESSION['user_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';
$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';
$notif_count = 0;
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT COUNT(*) total FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $notif_count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}
?>
<script>
(function(){
    const savedTheme = localStorage.getItem('icss-theme');
    const preferredDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.theme = savedTheme || (preferredDark ? 'dark' : 'light');
})();
</script>
<style>
.topbar{min-height:64px;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:10px 22px;background:rgba(255,255,255,.96);border-bottom:1px solid #e5e7eb;position:sticky;top:0;z-index:1000;backdrop-filter:blur(10px)}
.topbar-left{min-width:0}.topbar-title{color:#111827;font-size:1.05rem;line-height:1.15;font-weight:800}.topbar-sub{margin-top:2px;color:#6b7280;font-size:.72rem}
.topbar-right{display:flex;align-items:center;gap:9px}.live-clock{margin-right:4px;color:#4b5563;font-size:.76rem;font-weight:650;white-space:nowrap}
.topbar-icon-btn{position:relative;width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;color:#374151;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;font-size:1rem;transition:.18s ease}
.topbar-icon-btn:hover{color:#fff;background:#111827;border-color:#111827}.notification-count{position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;display:flex;align-items:center;justify-content:center;padding:0 5px;color:#fff;background:#dc2626;border:2px solid #fff;border-radius:999px;font-size:.62rem;font-weight:800}
.user-box{min-width:160px;padding:7px 11px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px}.user-name{max-width:190px;overflow:hidden;color:#111827;font-size:.78rem;font-weight:750;text-overflow:ellipsis;white-space:nowrap}.user-role{color:#6b7280;font-size:.65rem}
.logout-btn{padding:8px 11px;color:#fff;background:#dc2626;border-radius:9px;text-decoration:none;font-size:.74rem;font-weight:700}.logout-btn:hover{color:#fff;background:#b91c1c}
body.dashboard-home .topbar{padding-left:max(22px,calc((100vw - 1680px)/2 + 22px));padding-right:max(22px,calc((100vw - 1680px)/2 + 22px))}

html[data-theme="dark"] body{background:#151515!important;color:#ededed}
html[data-theme="dark"] .topbar{background:rgba(27,27,27,.97);border-bottom-color:#3a3a3a}
html[data-theme="dark"] .topbar-title,
html[data-theme="dark"] .user-name{color:#f8fafc}
html[data-theme="dark"] .topbar-sub,
html[data-theme="dark"] .live-clock,
html[data-theme="dark"] .user-role{color:#94a3b8}
html[data-theme="dark"] .topbar-icon-btn,
html[data-theme="dark"] .user-box{color:#ededed;background:#252525;border-color:#414141}
html[data-theme="dark"] .topbar-icon-btn:hover{background:#363636;border-color:#505050}
html[data-theme="dark"] .card,
html[data-theme="dark"] .table-card,
html[data-theme="dark"] .quick-links,
html[data-theme="dark"] .filter-box,
html[data-theme="dark"] .filter-card,
html[data-theme="dark"] .asset-panel{color:#e5e7eb;background:#172033!important;border-color:#2c3a50!important}
html[data-theme="dark"] .table{--bs-table-bg:#172033;--bs-table-color:#e5e7eb;--bs-table-border-color:#334155;--bs-table-hover-bg:#202c40;--bs-table-hover-color:#fff}
html[data-theme="dark"] .table-light{--bs-table-bg:#202c40;--bs-table-color:#e5e7eb}
html[data-theme="dark"] .form-control,
html[data-theme="dark"] .form-select{color:#e5e7eb;background-color:#111a2b;border-color:#3b4a61}
html[data-theme="dark"] .form-control::placeholder{color:#7f8da3}
html[data-theme="dark"] .form-control:focus,
html[data-theme="dark"] .form-select:focus{color:#fff;background:#111a2b;border-color:#60a5fa;box-shadow:0 0 0 .2rem rgba(59,130,246,.18)}
html[data-theme="dark"] .text-muted{color:#94a3b8!important}
html[data-theme="dark"] .bg-light{background-color:#111827!important}
html[data-theme="dark"] .border,
html[data-theme="dark"] .border-bottom{border-color:#334155!important}
html[data-theme="dark"] .alert-info{color:#bae6fd;background:#0c3b55;border-color:#155e75}
html[data-theme="dark"] .alert-warning{color:#fde68a;background:#4b3510;border-color:#785718}
html[data-theme="dark"] .alert-secondary{color:#d1d5db;background:#273244;border-color:#3b4a61}

@media(max-width:768px){.topbar{min-height:58px;padding:8px 12px 8px 64px}body.dashboard-home .topbar{padding:8px 12px}.topbar-title{font-size:.88rem}.topbar-sub,.live-clock{display:none}.topbar-right{gap:6px}.topbar-icon-btn{width:34px;height:34px}.user-box{min-width:0;max-width:120px;padding:6px 8px}.user-name{font-size:.7rem}.user-role{font-size:.58rem}.logout-btn{padding:7px 9px;font-size:.68rem}}
</style>
<header class="topbar">
    <div class="topbar-left"><div class="topbar-title">ICSS v2 ERP</div><div class="topbar-sub">Enterprise Resource Planning System</div></div>
    <div class="topbar-right">
        <div class="live-clock" id="liveClock">Loading time...</div>
        <button type="button" class="topbar-icon-btn" id="themeToggle"
                aria-label="Toggle dark mode" title="Toggle dark mode">🌙</button>
        <a href="../notifications/index.php" class="topbar-icon-btn" aria-label="Notifications" title="Notifications">
            🔔
            <?php if($notif_count>0): ?><span class="notification-count"><?= $notif_count>99?'99+':$notif_count ?></span><?php endif; ?>
        </a>
        <div class="user-box"><div class="user-name"><?= h($fullname) ?></div><div class="user-role"><?= h($role) ?></div></div>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>
<script>
function updateClock(){const clock=document.getElementById('liveClock');if(!clock)return;clock.textContent=new Date().toLocaleString('en-US',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});}
updateClock();setInterval(updateClock,30000);

const themeToggle=document.getElementById('themeToggle');
function updateThemeButton(){
    const isDark=document.documentElement.dataset.theme==='dark';
    themeToggle.textContent=isDark?'☀️':'🌙';
    themeToggle.title=isDark?'Use light mode':'Use dark mode';
}
themeToggle.addEventListener('click',function(){
    const nextTheme=document.documentElement.dataset.theme==='dark'?'light':'dark';
    document.documentElement.dataset.theme=nextTheme;
    localStorage.setItem('icss-theme',nextTheme);
    updateThemeButton();
});
updateThemeButton();
</script>

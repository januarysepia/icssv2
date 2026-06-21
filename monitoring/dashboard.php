<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Supervisor']);
include '../config/database.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Production Monitoring</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f3f5f8; }
        .monitor-page { max-width:1700px; margin:0 auto; padding:14px 18px 28px; }
        .monitor-header {
            display:flex; justify-content:space-between; align-items:center; gap:14px;
            padding:12px 15px; margin-bottom:12px; color:#fff; background:#20242b;
            border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.09);
        }
        .monitor-header h1 { margin:0; font-size:1.05rem; font-weight:750; }
        .monitor-subtitle { margin-top:2px; color:#cbd5e1; font-size:.7rem; }
        .monitor-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .monitor-actions .btn { padding:.3rem .55rem; font-size:.74rem; }
        .live-dot {
            width:7px; height:7px; display:inline-block; margin-right:5px;
            background:#22c55e; border-radius:50%; box-shadow:0 0 0 3px rgba(34,197,94,.16);
        }
        .monitor-stats {
            display:grid; grid-template-columns:repeat(6,minmax(0,1fr));
            gap:8px; margin-bottom:12px;
        }
        .monitor-stat {
            min-height:82px; padding:10px 12px; background:#fff; border:1px solid #e5e7eb;
            border-radius:9px; box-shadow:0 2px 7px rgba(15,23,42,.05);
        }
        .monitor-stat-label {
            color:#6b7280; font-size:.65rem; font-weight:750;
            text-transform:uppercase; letter-spacing:.035em;
        }
        .monitor-stat-value { margin-top:7px; font-size:1.4rem; line-height:1; font-weight:780; }
        .monitor-card {
            margin-bottom:12px; background:#fff; border:1px solid #e5e7eb;
            border-radius:10px; box-shadow:0 2px 8px rgba(15,23,42,.05); overflow:hidden;
        }
        .monitor-card-header {
            display:flex; justify-content:space-between; align-items:center;
            padding:9px 12px; color:#fff; background:#252a31;
            border-bottom:1px solid #15181d;
        }
        .monitor-card-header h2 { margin:0; font-size:.85rem; font-weight:750; }
        .monitor-card-header .last-updated { color:#cbd5e1; }
        .monitor-card .table { margin:0; font-size:.75rem; }
        .monitor-card .table th {
            padding:.48rem .55rem; font-size:.67rem; text-transform:uppercase;
            letter-spacing:.025em; white-space:nowrap; color:#e5e7eb;
            background:#343a42; border-color:#4b5563;
        }
        .monitor-card .table td { padding:.48rem .55rem; }
        .monitor-card .table tbody tr:nth-child(even) td { background:#f7f8fa; }
        .monitor-card .table tbody tr:hover td { background:#eef2f6; }
        .active-jobs-table th:last-child,
        .active-jobs-table td:last-child {
            position:sticky; right:0; z-index:2; text-align:center;
            background:#fff; box-shadow:-5px 0 8px rgba(15,23,42,.08);
        }
        .active-jobs-table th:last-child {
            z-index:3; color:#e5e7eb; background:#343a42;
        }
        .active-jobs-table tbody tr:nth-child(even) td:last-child { background:#f7f8fa; }
        .active-jobs-table tbody tr:hover td:last-child { background:#eef2f6; }
        .monitor-pagination {
            display:flex; justify-content:space-between; align-items:center;
            gap:10px; padding:8px 11px; border-top:1px solid #e5e7eb;
        }
        .pagination-summary { color:#6b7280; font-size:.68rem; }
        .monitor-pagination .page-link { padding:.25rem .48rem; font-size:.68rem; }
        .monitor-card .badge { font-size:.62rem; }
        .monitor-card .btn-sm { padding:.2rem .4rem; font-size:.68rem; }
        .department-name { font-weight:750; }
        .count-pill {
            display:inline-flex; min-width:26px; justify-content:center; padding:3px 7px;
            border-radius:999px; font-size:.66rem; font-weight:750;
        }
        .count-pending { color:#854d0e; background:#fef3c7; }
        .count-ack { color:#0e7490; background:#cffafe; }
        .count-progress { color:#1d4ed8; background:#dbeafe; }
        .count-completed { color:#047857; background:#d1fae5; }
        .workflow-progress { min-width:180px; }
        .workflow-progress .progress { height:6px; background:#e5e7eb; }
        .workflow-progress-label { color:#6b7280; font-size:.65rem; white-space:nowrap; }
        .step-list { display:flex; flex-wrap:wrap; gap:4px; margin-top:5px; }
        .step-chip {
            display:inline-flex; align-items:center; padding:3px 6px;
            border-radius:5px; font-size:.61rem; font-weight:650; white-space:nowrap;
        }
        .step-completed { color:#047857; background:#d1fae5; }
        .step-progress { color:#1d4ed8; background:#dbeafe; }
        .step-ack { color:#0e7490; background:#cffafe; }
        .step-pending { color:#854d0e; background:#fef3c7; }
        .step-other { color:#4b5563; background:#e5e7eb; }
        .monitor-loading { padding:35px; color:#6b7280; text-align:center; font-size:.8rem; }
        .last-updated { color:#6b7280; font-size:.65rem; }

        html[data-theme="dark"] .monitor-header { background:#242424; border:1px solid #3a3a3a; }
        html[data-theme="dark"] .monitor-stat,
        html[data-theme="dark"] .monitor-card { color:#ededed; background:#202020; border-color:#3a3a3a; }
        html[data-theme="dark"] .monitor-stat-label,
        html[data-theme="dark"] .workflow-progress-label,
        html[data-theme="dark"] .last-updated { color:#a3a3a3; }
        html[data-theme="dark"] .monitor-card-header {
            color:#f5f5f5; background:#292929; border-color:#454545;
        }
        html[data-theme="dark"] .monitor-card-header .last-updated { color:#c7c7c7; }
        html[data-theme="dark"] .monitor-card .table th {
            color:#f3f4f6; background:#353535; border-color:#505050;
        }
        html[data-theme="dark"] .monitor-card .table tbody tr:nth-child(even) td { background:#242424; }
        html[data-theme="dark"] .monitor-card .table tbody tr:hover td { background:#303030; }
        html[data-theme="dark"] .active-jobs-table th:last-child { background:#353535; }
        html[data-theme="dark"] .active-jobs-table td:last-child { background:#202020; }
        html[data-theme="dark"] .active-jobs-table tbody tr:nth-child(even) td:last-child { background:#242424; }
        html[data-theme="dark"] .active-jobs-table tbody tr:hover td:last-child { background:#303030; }
        html[data-theme="dark"] .monitor-pagination { border-color:#414141; }
        html[data-theme="dark"] .pagination-summary { color:#b3b3b3; }
        html[data-theme="dark"] .workflow-progress .progress { background:#3a3a3a; }

        @media(max-width:1100px) { .monitor-stats { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media(max-width:768px) {
            .monitor-page { padding:10px 10px 24px; }
            .monitor-header { align-items:flex-start; flex-direction:column; }
            .monitor-actions { width:100%; }
            .monitor-actions .btn { flex:1 1 auto; }
            .monitor-card table { min-width:820px; }
        }
        @media(max-width:480px) { .monitor-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    </style>
</head>
<body>
<div class="monitor-page">
    <header class="monitor-header">
        <div>
            <h1>Production Monitoring</h1>
            <div class="monitor-subtitle">
                <span class="live-dot"></span>Live operational view · refreshes every 5 seconds
            </div>
        </div>
        <div class="monitor-actions">
            <a href="../supervisor/work_queue.php" class="btn btn-primary btn-sm">Work Queue</a>
            <a href="../job_orders/jo_list.php" class="btn btn-light btn-sm">Job Orders</a>
            <a href="../dashboard/index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
        </div>
    </header>

    <div id="monitoringContent">
        <div class="monitor-loading">Loading production data...</div>
    </div>
</div>

<script>
let monitoringRequest = null;
let monitoringPage = 1;

async function loadMonitoring() {
    if (monitoringRequest) monitoringRequest.abort();
    monitoringRequest = new AbortController();

    try {
        const response = await fetch('fetch_dashboard.php?page=' + monitoringPage, {
            signal: monitoringRequest.signal,
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Unable to load monitoring data.');
        document.getElementById('monitoringContent').innerHTML = await response.text();
    } catch (error) {
        if (error.name === 'AbortError') return;
        document.getElementById('monitoringContent').innerHTML =
            '<div class="alert alert-danger py-2 small">Unable to load monitoring data. Retrying automatically...</div>';
    }
}

document.getElementById('monitoringContent').addEventListener('click', function (event) {
    const pageButton = event.target.closest('.monitor-page-link');
    if (!pageButton || pageButton.closest('.disabled')) return;

    monitoringPage = Math.max(1, Number(pageButton.dataset.page) || 1);
    loadMonitoring();
    document.querySelector('.active-jobs-card')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
});

loadMonitoring();
setInterval(loadMonitoring, 5000);
</script>
</body>
</html>

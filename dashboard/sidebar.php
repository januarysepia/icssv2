<?php

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

$notif_count = 0;

if(isset($conn)){

    $notif_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE user_id = '$user_id'
    AND is_read = 0
    ");

    if($notif_result){

        $notif_data = $notif_result->fetch_assoc();

        $notif_count = $notif_data['total'];
    }
}

?>

<style>

.sidebar{
    width:260px;
    height:100vh;
    background:#111827;
    position:fixed;
    left:0;
    top:0;
    padding:20px;
    overflow-y:auto;
    overflow-x:hidden;
    z-index:2000;
    transition:0.3s;
    scrollbar-width:thin;
}
.sidebar::-webkit-scrollbar{
    width:6px;
}
.sidebar::-webkit-scrollbar-thumb{
    background:#4b5563;
    border-radius:10px;
}
.sidebar::-webkit-scrollbar-track{
    background:#111827;
}
.sidebar a{
    white-space:normal;
}
.sidebar-title{
    color:white;
    font-size:23px;
    font-weight:700;
    margin-bottom:25px;
}
.sidebar-section{
    color:#9ca3af;
    font-size:12px;
    font-weight:700;
    margin-top:22px;
    margin-bottom:8px;
    text-transform:uppercase;
}
.sidebar a{
    display:block;
    color:#d1d5db;
    text-decoration:none;
    padding:10px 14px;
    border-radius:10px;
    margin-bottom:6px;
    font-size:15px;
    transition:0.2s;
}
.sidebar a:hover{
    background:#1f2937;
    color:white;
}
.sidebar-badge{
    background:#dc2626;
    color:white;
    font-size:11px;
    padding:2px 7px;
    border-radius:999px;
    float:right;
}
.content-wrapper{
    margin-left:260px;
    transition:0.3s;
}
.mobile-menu-btn{
    display:none;
    position:fixed;
    top:15px;
    left:15px;
    z-index:3000;
    background:#111827;
    color:white;
    border:none;
    padding:10px 14px;
    border-radius:10px;
    font-size:18px;
    box-shadow:0 4px 10px rgba(0,0,0,0.2);
}
@media (max-width: 768px){
    .sidebar{
        left:-260px;
    }
    .sidebar.show{
        left:0;
    }
    .content-wrapper{
        margin-left:0;
    }
    .mobile-menu-btn{
        display:block;
    }
}

</style>

<button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>

<div class="sidebar">

    <div class="sidebar-title">
        ICSS v2 ERP
    </div>

    <div class="sidebar-section">Main</div>

    <a href="../dashboard/index.php">🏠 Dashboard</a>

    <?php if(in_array($role, ['Boss','Technical','Supervisor','Engineer'])){ ?>

        <div class="sidebar-section">Job Orders</div>

        <?php if($role === 'Supervisor'){ ?>
            <a href="../supervisor/work_queue.php">Supervisor Work Queue</a>
        <?php } ?>

        <?php if(in_array($role, ['Boss','Technical'])){ ?>
            <a href="../job_orders/create_jo.php">+ Create Job Order</a>
        <?php } ?>

        <a href="../job_orders/jo_list.php">Job Orders</a>

    <?php } ?>

    <?php if(in_array($role, ['Production'])){ ?>

        <div class="sidebar-section">Production</div>
        <a href="../workflow/my_tasks.php">My Tasks</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Supervisor'])){ ?>

        <div class="sidebar-section">Monitoring</div>
        <a href="../dashboard/activity_logs.php">Activity Logs</a>
        <a href="../monitoring/dashboard.php">Production Monitoring</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Technical','Purchasing'])){ ?>

        <div class="sidebar-section">Material Requests</div>

        <?php if(in_array($role, ['Boss','Technical'])){ ?>
            <a href="../material_requests/create_request.php">+ Create Request</a>
        <?php } ?>

        <a href="../material_requests/request_list.php">Request List</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Purchasing'])){ ?>

        <div class="sidebar-section">Purchasing</div>

        <?php if(in_array($role, ['Purchasing','Admin'])){ ?>
            <a href="../purchase_requests/create_purchase.php">+ Create Purchase</a>
        <?php } ?>

        <a href="../purchase_requests/purchase_list.php">Purchase List</a>
        <a href="../purchase_requests/suppliers.php">Supplier Management</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Purchasing'])){ ?>

        <div class="sidebar-section">Inventory</div>

        <a href="../inventory/inventory_list.php">Inventory Items</a>
        <a href="../inventory/inventory_logs.php">Inventory Logs</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Purchasing'])){ ?>

        <div class="sidebar-section">Asset Module</div>

        <a href="../inventory/asset_dashboard.php">Asset Dashboard</a>
        <a href="../inventory/asset_catalog.php">Asset Catalog</a>
        <a href="../inventory/asset_transactions.php">Borrow / Return</a>
        <a href="../inventory/asset_history.php">Asset History</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Engineer'])){ ?>

        <div class="sidebar-section">QA/QC</div>
        <a href="../qaqc/qaqc_list.php">QA/QC Tasks</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Logistics'])){ ?>

        <div class="sidebar-section">Logistics</div>
        <a href="../logistics/logistics_list.php">Logistics Tasks</a>

    <?php } ?>

    <?php if(in_array($role, ['Boss','Admin'])){ ?>

        <div class="sidebar-section">Administration</div>
        <a href="../users/employee_list.php">Employees</a>
        <?php if($role === 'Admin'){ ?>
            <a href="../users/create_user.php">+ Add User</a>
            <a href="../dashboard/activity_logs.php">Activity Logs</a>
        <?php } ?>

    <?php } ?>

    <div class="sidebar-section">Session</div>

    <a href="../logout.php">Logout</a>

</div>

<script>

function toggleSidebar(){
    document.querySelector('.sidebar').classList.toggle('show');
}

</script>

<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Technical',
    'Supervisor',
    'Engineer'
]);

include '../config/database.php';

$view = $_GET['view'] ?? 'active';
if (!in_array($view, ['active', 'completed'], true)) {
    $view = 'active';
}

$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$is_completed_view = $view === 'completed';

$active_count = $conn->query("
    SELECT COUNT(*) AS total
    FROM job_orders
    WHERE workflow_status <> 'Completed'
      AND COALESCE(overall_status, '') <> 'Completed'
")->fetch_assoc()['total'] ?? 0;

$completed_count = $conn->query("
    SELECT COUNT(*) AS total
    FROM job_orders
    WHERE workflow_status = 'Completed'
       OR overall_status = 'Completed'
")->fetch_assoc()['total'] ?? 0;

$status_condition = $is_completed_view
    ? "(job_orders.workflow_status = 'Completed' OR job_orders.overall_status = 'Completed')"
    : "job_orders.workflow_status <> 'Completed' AND COALESCE(job_orders.overall_status, '') <> 'Completed'";

$search_condition = '';
if ($search !== '') {
    $search_condition = "
        AND (
            job_orders.jo_no LIKE ?
            OR job_orders.client_name LIKE ?
            OR job_orders.project_name LIKE ?
            OR job_orders.engineer_name LIKE ?
        )
    ";
}

$count_sql = "
    SELECT COUNT(*) AS total
    FROM job_orders
    WHERE $status_condition
    $search_condition
";
$count_stmt = $conn->prepare($count_sql);
if ($search !== '') {
    $term = '%' . $search . '%';
    $count_stmt->bind_param('ssss', $term, $term, $term, $term);
}
$count_stmt->execute();
$filtered_total = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int) ceil($filtered_total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT
        job_orders.*,
        users.fullname AS created_by_name
    FROM job_orders
    LEFT JOIN users ON users.id = job_orders.created_by
    WHERE $status_condition
    $search_condition
";

$sql .= $is_completed_view
    ? " ORDER BY COALESCE(job_orders.completed_at, job_orders.created_at) DESC, job_orders.id DESC"
    : " ORDER BY CASE WHEN job_orders.due_date < CURDATE() THEN 0 ELSE 1 END, job_orders.due_date ASC, job_orders.id DESC";
$sql .= " LIMIT $per_page OFFSET $offset";

$jobs_stmt = $conn->prepare($sql);
if ($search !== '') {
    $term = '%' . $search . '%';
    $jobs_stmt->bind_param('ssss', $term, $term, $term, $term);
}
$jobs_stmt->execute();
$jobs = $jobs_stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Job Order List</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; }
        .jo-page { max-width:1700px; margin:0 auto; padding:.9rem; }
        .jo-card {
            border:0;
            border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,.07);
        }
        .jo-card > .card-header { padding:.7rem .9rem; }
        .jo-card > .card-header h4 { font-size:1.05rem; font-weight:700; }
        .jo-card > .card-header .btn { padding:.3rem .55rem; font-size:.75rem; }
        .jo-card > .card-body { padding:.85rem; }
        .jo-toolbar { margin-bottom:.7rem !important; }
        .jo-toolbar .btn { padding:.35rem .65rem; font-size:.78rem; }
        .jo-toolbar .form-control { min-height:34px; font-size:.8rem; }
        .jo-toolbar .badge { font-size:.68rem; }
        .jo-card .alert {
            padding:.55rem .75rem;
            margin-bottom:.7rem;
            font-size:.78rem;
            border-radius:7px;
        }
        .jo-card table { font-size:.8rem; }
        .jo-card table th {
            padding:.55rem .6rem;
            font-size:.72rem;
            text-transform:uppercase;
            letter-spacing:.02em;
            white-space:nowrap;
        }
        .jo-card table td { padding:.5rem .6rem; }
        .jo-card table .badge { font-size:.68rem; }
        .jo-card table .btn-sm { padding:.24rem .45rem; font-size:.72rem; }
        .jo-card table th:last-child,
        .jo-card table td:last-child {
            position:sticky;
            right:0;
            z-index:2;
            width:82px;
            min-width:82px;
            max-width:82px;
            text-align:center;
            white-space:nowrap;
            background:#fff;
            box-shadow:-6px 0 9px rgba(15,23,42,.1);
        }
        .jo-card table th:last-child {
            z-index:3;
            color:#fff;
            background:#212529;
        }
        .jo-card table tbody tr:nth-child(even) td:last-child {
            background:#f7f8fa;
        }
        .jo-card table tbody tr:hover td:last-child {
            background:#eef2f6;
        }
        html[data-theme="dark"] .jo-card table th:last-child {
            color:#fff!important;
            background:#292929!important;
        }
        html[data-theme="dark"] .jo-card table td:last-child {
            background:#202020!important;
            box-shadow:-6px 0 10px rgba(0,0,0,.28);
        }
        html[data-theme="dark"] .jo-card table tbody tr:nth-child(even) td:last-child {
            background:#262626!important;
        }
        html[data-theme="dark"] .jo-card table tbody tr:hover td:last-child {
            background:#303030!important;
        }
        .jo-pagination .page-link { padding:.3rem .55rem; font-size:.75rem; }
        .jo-pagination-info { font-size:.75rem; color:#6b7280; }
        @media(max-width:768px) {
            .jo-page { padding:.65rem; }
            .jo-card > .card-header > .d-flex {
                align-items:flex-start !important;
                flex-direction:column;
                gap:.55rem;
            }
            .jo-toolbar { align-items:stretch !important; }
            .jo-toolbar form { min-width:100% !important; }
            .jo-card table { min-width:1050px; }
            .jo-card table th:last-child,
            .jo-card table td:last-child {
                width:68px!important;
                min-width:68px!important;
                max-width:68px!important;
                padding:.35rem .25rem!important;
            }
            .jo-card table td:last-child .btn {
                padding:.22rem .38rem;
                font-size:.68rem;
            }
        }
    </style>
</head>

<body class="bg-light">

<div class="container-fluid jo-page">

    <div class="card jo-card">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Job Orders
                </h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <?php if(
                        $_SESSION['system_role'] == 'Technical' ||
                        $_SESSION['system_role'] == 'Admin' ||
                        $_SESSION['system_role'] == 'Boss'
                    ){ ?>

                        <a href="create_jo.php" class="btn btn-success btn-sm">
                            + Create JO
                        </a>

                    <?php } ?>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="jo-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="btn-group" role="group" aria-label="Job order view">
                    <a href="jo_list.php?view=active"
                       class="btn <?= $view === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Active <span class="badge bg-light text-dark"><?= (int) $active_count ?></span>
                    </a>
                    <a href="jo_list.php?view=completed"
                       class="btn <?= $view === 'completed' ? 'btn-success' : 'btn-outline-success' ?>">
                        Completed Archive <span class="badge bg-light text-dark"><?= (int) $completed_count ?></span>
                    </a>
                </div>

                <form method="GET" id="joSearchForm" class="d-flex gap-2" style="min-width:min(100%,420px);">
                    <input type="hidden" name="view" value="<?= h($view) ?>">
                    <input type="search" name="search" id="joSearchInput" class="form-control"
                           placeholder="Search JO, client, project, engineer..."
                           autocomplete="off"
                           value="<?= h($search) ?>">
                    <button type="submit" class="btn btn-dark">Search</button>
                    <button type="button" id="clearJoSearch"
                            class="btn btn-outline-secondary <?= $search === '' ? 'd-none' : '' ?>">Clear</button>
                </form>
            </div>

            <?php if ($is_completed_view): ?>
                <div class="alert alert-secondary">
                    Completed job orders are retained for history and audit. Workflow editing is disabled.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Daily operational view. Completed job orders automatically move to the Completed Archive.
                </div>
            <?php endif; ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>JO No</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Created By</th>
                            <th>Engineer</th>
                            <th>Sales</th>
                            <th>Release Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="joTableBody">

                    <?php if ($jobs && $jobs->num_rows > 0): ?>
                    <?php while($row = $jobs->fetch_assoc()){ ?>

                        <tr>

                            <td>
                                <b><?php echo $row['jo_no']; ?></b>
                            </td>

                            <td><?php echo $row['client_name']; ?></td>

                            <td><?php echo $row['project_name']; ?></td>

                            <td>
                                <?php
                                echo !empty($row['created_by_name'])
                                ? $row['created_by_name']
                                : 'System / Unknown';
                                ?>
                            </td>

                            <td><?php echo $row['engineer_name']; ?></td>

                            <td><?php echo $row['sales_name']; ?></td>

                            <td><?php echo $row['release_date']; ?></td>

                            <td>
                                <?php if($row['due_date'] < date('Y-m-d') && $row['workflow_status'] != 'Completed'){ ?>

                                    <span class="badge bg-danger">
                                        <?php echo $row['due_date']; ?>
                                    </span>

                                <?php }else{ ?>

                                    <?php echo $row['due_date']; ?>

                                <?php } ?>
                            </td>

                            <td>
                                <?php if($row['workflow_status'] == 'Completed'){ ?>

                                    <span class="badge bg-success">
                                        Completed
                                    </span>

                                <?php }elseif($row['workflow_status'] == 'For Validation'){ ?>

                                    <span class="badge bg-warning text-dark">
                                        For Validation
                                    </span>

                                <?php }elseif($row['workflow_status'] == 'For QA Inspection'){ ?>

                                    <span class="badge bg-info text-dark">
                                        For QA Inspection
                                    </span>

                                <?php }elseif($row['workflow_status'] == 'QA Passed'){ ?>

                                    <span class="badge bg-primary">
                                        QA Passed
                                    </span>

                                <?php }elseif($row['workflow_status'] == 'Preparing Delivery'){ ?>

                                    <span class="badge bg-warning text-dark">
                                        Preparing Delivery
                                    </span>

                                <?php }elseif($row['workflow_status'] == 'Dispatched'){ ?>

                                    <span class="badge bg-secondary">
                                        Dispatched
                                    </span>

                                <?php }elseif($row['workflow_status'] == 'Returned to Production'){ ?>

                                    <span class="badge bg-danger">
                                        Returned to Production
                                    </span>

                                <?php }else{ ?>

                                    <span class="badge bg-dark">
                                        <?php echo $row['workflow_status']; ?>
                                    </span>

                                <?php } ?>
                            </td>

                            <td>

                                <a href="view_jo.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary btn-sm">
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php } ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <?= $is_completed_view ? 'No completed job orders found.' : 'No active job orders found.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="jo-pagination-info" id="joPaginationInfo">
                    <?php if ($filtered_total > 0): ?>
                        Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $filtered_total) ?>
                        of <?= $filtered_total ?> job orders
                    <?php else: ?>
                        No job orders found
                    <?php endif; ?>
                </div>
                <nav aria-label="Job order pages">
                    <ul class="pagination jo-pagination mb-0" id="joPagination"></ul>
                </nav>
            </div>

        </div>

    </div>

</div>

<script>
const joView = <?= json_encode($view) ?>;
const searchInput = document.getElementById('joSearchInput');
const searchForm = document.getElementById('joSearchForm');
const clearButton = document.getElementById('clearJoSearch');
const tableBody = document.getElementById('joTableBody');
const pagination = document.getElementById('joPagination');
const paginationInfo = document.getElementById('joPaginationInfo');
let searchTimer = null;
let activeRequest = null;
let currentPage = <?= (int) $page ?>;
let totalPages = <?= (int) $total_pages ?>;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
}

function statusBadge(status) {
    const classes = {
        'Completed': 'bg-success',
        'For Validation': 'bg-warning text-dark',
        'For QA Inspection': 'bg-info text-dark',
        'QA Passed': 'bg-primary',
        'Preparing Delivery': 'bg-warning text-dark',
        'Dispatched': 'bg-secondary',
        'Returned to Production': 'bg-danger'
    };
    return `<span class="badge ${classes[status] || 'bg-dark'}">${escapeHtml(status)}</span>`;
}

function renderRows(rows) {
    if (!rows.length) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center text-muted py-4">
                    No matching ${joView === 'completed' ? 'completed' : 'active'} job orders.
                </td>
            </tr>`;
        return;
    }

    tableBody.innerHTML = rows.map(row => {
        const dueDate = row.is_overdue
            ? `<span class="badge bg-danger">${escapeHtml(row.due_date)}</span>`
            : escapeHtml(row.due_date || '-');
        return `
            <tr>
                <td><b>${escapeHtml(row.jo_no)}</b></td>
                <td>${escapeHtml(row.client_name)}</td>
                <td>${escapeHtml(row.project_name)}</td>
                <td>${escapeHtml(row.created_by_name)}</td>
                <td>${escapeHtml(row.engineer_name)}</td>
                <td>${escapeHtml(row.sales_name)}</td>
                <td>${escapeHtml(row.release_date || '-')}</td>
                <td>${dueDate}</td>
                <td>${statusBadge(row.workflow_status)}</td>
                <td>
                    <a href="view_jo.php?id=${row.id}" class="btn btn-primary btn-sm">View</a>
                </td>
            </tr>`;
    }).join('');
}

function paginationButton(label, page, disabled = false, active = false) {
    return `
        <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
            <button type="button" class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>
                ${label}
            </button>
        </li>`;
}

function renderPagination(data) {
    currentPage = Number(data.page || 1);
    totalPages = Number(data.total_pages || 1);
    const total = Number(data.total || 0);
    const perPage = Number(data.per_page || 10);

    if (total > 0) {
        const start = ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, total);
        paginationInfo.textContent = `Showing ${start}–${end} of ${total} job orders`;
    } else {
        paginationInfo.textContent = 'No job orders found';
    }

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = paginationButton('« First', 1, currentPage === 1);
    html += paginationButton('‹ Previous', currentPage - 1, currentPage === 1);

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    for (let page = startPage; page <= endPage; page++) {
        html += paginationButton(page, page, false, page === currentPage);
    }

    html += paginationButton('Next ›', currentPage + 1, currentPage === totalPages);
    html += paginationButton('Last »', totalPages, currentPage === totalPages);
    pagination.innerHTML = html;
}

async function loadJobOrders(page = 1) {
    const query = searchInput.value.trim();

    if (activeRequest) {
        activeRequest.abort();
    }
    activeRequest = new AbortController();

    tableBody.innerHTML = `
        <tr><td colspan="10" class="text-center text-muted py-4">Searching job orders...</td></tr>`;

    try {
        const response = await fetch(
            `ajax_jo_list.php?view=${encodeURIComponent(joView)}&search=${encodeURIComponent(query)}&page=${encodeURIComponent(page)}`,
            { signal: activeRequest.signal }
        );

        if (!response.ok) {
            throw new Error('Search request failed.');
        }

        const data = await response.json();
        renderRows(data.rows || []);
        renderPagination(data);
        clearButton.classList.toggle('d-none', query === '');

        const url = new URL(window.location.href);
        url.searchParams.set('view', joView);
        if (query) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        if (Number(data.page || 1) > 1) {
            url.searchParams.set('page', data.page);
        } else {
            url.searchParams.delete('page');
        }
        window.history.replaceState({}, '', url);
    } catch (error) {
        if (error.name === 'AbortError') return;
        tableBody.innerHTML = `
            <tr><td colspan="10" class="text-center text-danger py-4">
                Unable to search job orders. Please try again.
            </td></tr>`;
    }
}

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadJobOrders(1), 250);
});

searchForm.addEventListener('submit', event => {
    event.preventDefault();
    clearTimeout(searchTimer);
    loadJobOrders(1);
});

clearButton.addEventListener('click', () => {
    searchInput.value = '';
    searchInput.focus();
    loadJobOrders(1);
});

pagination.addEventListener('click', event => {
    const button = event.target.closest('button[data-page]');
    if (!button || button.disabled) return;
    loadJobOrders(Number(button.dataset.page));
});

renderPagination({
    page: <?= (int) $page ?>,
    total_pages: <?= (int) $total_pages ?>,
    total: <?= (int) $filtered_total ?>,
    per_page: <?= (int) $per_page ?>
});
</script>

</body>
</html>

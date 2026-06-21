<?php

include '../auth/auth_check.php';

require_role([
    'Production',
    'Boss',
    'Admin',
    'Supervisor'
]);

include '../config/database.php';
include '../includes/drawing_revision_seen.php';

$id = intval($_GET['id']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

$task = $conn->query("
SELECT
job_workflow_steps.*,
UNIX_TIMESTAMP(job_workflow_steps.started_at) AS started_timestamp,
UNIX_TIMESTAMP(NOW()) AS database_now_timestamp,
DATE_FORMAT(DATE_ADD(job_workflow_steps.started_at, INTERVAL 15 MINUTE), '%h:%i %p') AS complete_available_time,
job_orders.jo_no,
job_orders.client_name,
job_orders.project_name,
job_orders.engineer_name,
job_orders.sales_name,
job_orders.release_date,
job_orders.due_date,
job_orders.workflow_status,
departments.department_name,
users.fullname,
users.employee_no

FROM job_workflow_steps

LEFT JOIN job_orders
ON job_orders.id = job_workflow_steps.jo_id

LEFT JOIN departments
ON departments.id = job_workflow_steps.department_id

LEFT JOIN users
ON users.id = job_workflow_steps.assigned_user_id

WHERE job_workflow_steps.id = '$id'
")->fetch_assoc();

if(!$task){
    die("Task not found.");
}

if($role == 'Production' && $task['assigned_user_id'] != $user_id){
    die("Access Denied. This task is not assigned to you.");
}

$jo_id = $task['jo_id'];
markDrawingRevisionsSeen($conn, (int) $jo_id, (int) $user_id);

$attachment = $conn->query("
SELECT *
FROM job_order_attachments
WHERE jo_id = '$jo_id'
ORDER BY id DESC
LIMIT 1
")->fetch_assoc();

$progress_logs_stmt = $conn->prepare("
    SELECT ppl.*, users.fullname
    FROM production_progress_logs ppl
    LEFT JOIN users ON users.id = ppl.user_id
    WHERE ppl.workflow_step_id = ?
    ORDER BY ppl.id DESC
    LIMIT 30
");
$progress_logs_stmt->bind_param('i', $id);
$progress_logs_stmt->execute();
$progress_logs = $progress_logs_stmt->get_result();

$minimum_work_minutes = 15;
$minimum_work_seconds = $minimum_work_minutes * 60;
$started_timestamp = !empty($task['started_timestamp']) ? (int) $task['started_timestamp'] : null;
$database_now_timestamp = (int) ($task['database_now_timestamp'] ?? time());
$complete_available_timestamp = $started_timestamp ? $started_timestamp + $minimum_work_seconds : null;
$can_complete_now = $task['status'] === 'In Progress'
    && $complete_available_timestamp !== null
    && $database_now_timestamp >= $complete_available_timestamp
    && (int) ($task['progress_percent'] ?? 0) >= 100;

$task_feedback = $_SESSION['task_feedback'] ?? null;
unset($_SESSION['task_feedback']);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Production Task Detail</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .task-page{
            max-width:1700px;
            margin:0 auto;
            padding:12px 16px 28px;
        }

        .card-box{
            border:0;
            border-radius:10px;
            box-shadow:0 4px 14px rgba(0,0,0,0.08);
            overflow:visible;
        }

        .status-box{
            padding:11px 13px;
            border-radius:9px;
            background:#f8fafc;
            border:1px solid #e5e7eb;
        }

        .action-btn{
            min-width:145px;
        }

        .task-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
        }

        .task-header-actions{
            display:flex;
            flex-wrap:wrap;
            gap:5px;
        }

        .task-action-bar{
            position:sticky;
            top:64px;
            z-index:990;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:8px 12px;
            background:#fff;
            border-bottom:1px solid #dfe3e8;
            box-shadow:0 3px 8px rgba(15,23,42,.08);
        }

        .task-action-label{
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:7px;
            font-size:.76rem;
        }

        .task-action-form{
            margin:0;
        }

        .work-timer{
            color:#475569;
            font-size:.7rem;
        }

        .completion-panel{
            display:none;
            padding:12px;
            border-top:1px solid #dfe3e8;
            background:#f8fafc;
        }

        .completion-panel.open{display:block}
        .completion-grid{display:grid;grid-template-columns:1.4fr 1fr auto;gap:10px;align-items:end}
        .completion-grid textarea{min-height:68px}
        .progress-panel{padding:12px;margin-bottom:14px;border:1px solid #dbe3ec;border-radius:9px;background:#f8fafc}
        .progress-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:7px}
        .progress-value{font-size:1.05rem;font-weight:800;color:#2563eb}
        .progress-slider{width:100%;accent-color:#2563eb}
        .progress-scale{display:flex;justify-content:space-between;color:#64748b;font-size:.65rem}
        .progress-save-grid{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:8px}
        .progress-history{font-size:.74rem}
        .progress-history td,.progress-history th{padding:.42rem .5rem}
        .task-feedback-overlay{
            position:fixed;
            inset:0;
            z-index:2000;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:18px;
            background:rgba(15,23,42,.58);
            backdrop-filter:blur(2px);
        }
        .task-feedback-modal{
            width:min(440px,100%);
            overflow:hidden;
            border-radius:14px;
            background:#fff;
            box-shadow:0 24px 65px rgba(0,0,0,.28);
            animation:taskModalIn .18s ease-out;
        }
        .task-feedback-body{
            padding:24px 24px 18px;
            text-align:center;
        }
        .task-feedback-icon{
            display:grid;
            place-items:center;
            width:54px;
            height:54px;
            margin:0 auto 13px;
            border-radius:50%;
            color:#fff;
            background:#dc3545;
            font-size:1.65rem;
            font-weight:800;
        }
        .task-feedback-modal.success .task-feedback-icon{background:#198754}
        .task-feedback-title{margin:0 0 7px;font-size:1.08rem;font-weight:800;color:#172033}
        .task-feedback-message{margin:0;color:#526071;line-height:1.5}
        .task-feedback-code{
            display:inline-block;
            margin-top:13px;
            padding:5px 9px;
            border-radius:6px;
            color:#842029;
            background:#f8d7da;
            font-size:.7rem;
            font-weight:800;
            letter-spacing:.04em;
        }
        .task-feedback-modal.success .task-feedback-code{color:#0f5132;background:#d1e7dd}
        .task-feedback-timer{
            height:4px;
            background:#dc3545;
            transform-origin:left;
            animation:taskFeedbackTimer 3.8s linear forwards;
        }
        .task-feedback-modal.success .task-feedback-timer{background:#198754;animation-duration:2.8s}
        html[data-theme="dark"] .task-feedback-modal{background:#242424;border:1px solid #444}
        html[data-theme="dark"] .task-feedback-title{color:#f8fafc}
        html[data-theme="dark"] .task-feedback-message{color:#cbd5e1}
        @keyframes taskModalIn{
            from{opacity:0;transform:translateY(10px) scale(.97)}
            to{opacity:1;transform:translateY(0) scale(1)}
        }
        @keyframes taskFeedbackTimer{to{transform:scaleX(0)}}

        html[data-theme="dark"] .task-action-bar{
            background:#242424;
            border-color:#444;
            box-shadow:0 3px 9px rgba(0,0,0,.3);
        }
        html[data-theme="dark"] .status-box{
            color:#f8fafc;
            background:#292929;
            border-color:#4a4a4a;
        }
        html[data-theme="dark"] .status-box small{
            color:#aebdd2!important;
        }
        html[data-theme="dark"] .status-box h5{
            color:#fff!important;
        }
        html[data-theme="dark"] .completion-panel{background:#202020;border-color:#444}
        html[data-theme="dark"] .progress-panel{background:#242424;border-color:#444}
        html[data-theme="dark"] .progress-value{color:#93c5fd}
        html[data-theme="dark"] .work-timer{color:#cbd5e1}

        @media(max-width:768px){
            .task-page{
                padding:10px;
            }

            .task-header{
                align-items:stretch;
                flex-direction:column;
            }

            .task-header-actions{
                display:grid;
                grid-template-columns:1fr 1fr;
            }

            .task-header-actions .btn{
                width:100%;
            }

            .task-action-bar{
                top:58px;
                align-items:stretch;
                flex-direction:column;
            }

            .task-action-form,
            .task-action-form .action-btn{
                width:100%;
            }
            .completion-grid{grid-template-columns:1fr}
            .progress-save-grid{grid-template-columns:1fr}
        }
    </style>
</head>

<body>

<?php include '../dashboard/header.php'; ?>

<main class="task-page">

    <?php if ($task_feedback): ?>
        <div class="task-feedback-overlay" id="taskFeedbackModal"
             role="dialog" aria-modal="true" aria-labelledby="taskFeedbackTitle">
            <div class="task-feedback-modal <?= $task_feedback['type'] === 'success' ? 'success' : 'error' ?>">
                <div class="task-feedback-body">
                    <div class="task-feedback-icon" aria-hidden="true">
                        <?= $task_feedback['type'] === 'success' ? '✓' : '!' ?>
                    </div>
                    <h2 class="task-feedback-title" id="taskFeedbackTitle">
                        <?= $task_feedback['type'] === 'success' ? 'Successful' : 'Unable to continue' ?>
                    </h2>
                    <p class="task-feedback-message"><?= h($task_feedback['message']) ?></p>
                    <?php if (!empty($task_feedback['code'])): ?>
                        <span class="task-feedback-code">Error Code: <?= h($task_feedback['code']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="task-feedback-timer"></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card card-box">

        <div class="card-header bg-dark text-white">

            <div class="task-header">

                <h4 class="mb-0">
                    Production Task - <?php echo h($task['department_name']); ?>
                </h4>

                <div class="task-header-actions">
                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="my_tasks.php"
                       class="btn btn-secondary btn-sm">
                        Back to My Tasks
                    </a>
                </div>

            </div>

        </div>

        <div class="task-action-bar">
            <div class="task-action-label">
                <strong>Task Action</strong>
                <span class="badge <?php
                    echo $task['status'] === 'Pending' ? 'bg-warning text-dark' :
                        ($task['status'] === 'Acknowledged' ? 'bg-info text-dark' :
                        ($task['status'] === 'In Progress' ? 'bg-primary' :
                        ($task['status'] === 'Completed' ? 'bg-success' : 'bg-secondary')));
                ?>">
                    <?= h($task['status']) ?>
                </span>
                <?php if ($role !== 'Production'): ?>
                    <span class="text-muted">Read-only view</span>
                <?php endif; ?>
                <?php if ($task['status'] === 'In Progress' && $complete_available_timestamp): ?>
                    <span class="work-timer">
                        Elapsed: <strong id="elapsedWorkTime">--:--</strong>
                        · Complete available at <?= h($task['complete_available_time']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($role === 'Production' && $task['status'] === 'Pending'): ?>
                <form action="update_task.php" method="POST" class="task-action-form"
                      onsubmit="return confirm('Acknowledge this task?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                    <input type="hidden" name="action" value="acknowledge">
                    <button type="submit" class="btn btn-info action-btn">Acknowledge Task</button>
                </form>
            <?php elseif ($role === 'Production' && $task['status'] === 'Acknowledged'): ?>
                <form action="update_task.php" method="POST" class="task-action-form"
                      onsubmit="return confirm('Start this task?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" class="btn btn-primary action-btn">Start Work</button>
                </form>
            <?php elseif ($role === 'Production' && $task['status'] === 'In Progress'): ?>
                <button type="button" id="showCompletionForm"
                        class="btn btn-success action-btn"
                        <?= $can_complete_now ? '' : 'disabled' ?>>
                    <?= $can_complete_now ? 'Complete Task' : ((int)$task['progress_percent'] < 100 ? 'Complete at 100%' : 'Complete Locked') ?>
                </button>
            <?php elseif ($task['status'] === 'Completed'): ?>
                <span class="text-success fw-bold">Task completed</span>
            <?php endif; ?>
        </div>

        <?php if ($role === 'Production' && $task['status'] === 'In Progress'): ?>
            <div class="completion-panel" id="completionPanel">
                <form action="update_task.php" method="POST" enctype="multipart/form-data"
                      onsubmit="return confirm('Submit and complete this task?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                    <input type="hidden" name="action" value="complete">
                    <div class="completion-grid">
                        <div>
                            <label class="form-label">Completion Remarks <span class="text-muted">(Optional)</span></label>
                            <textarea name="completion_remarks" class="form-control"
                                      placeholder="Optional: describe completed work, checks, or important notes..."></textarea>
                        </div>
                        <div>
                            <label class="form-label">Proof Photo <span class="text-muted">(Optional)</span></label>
                            <input type="file" name="completion_proof" class="form-control"
                                   accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                            <div class="form-text">JPG or PNG, maximum 5 MB.</div>
                        </div>
                        <button type="submit" class="btn btn-success">Confirm Completion</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card-body">

            <?php if (in_array($task['status'], ['In Progress','Completed'], true)): ?>
                <section class="progress-panel">
                    <div class="progress-head">
                        <div>
                            <strong>Production Progress</strong>
                            <div class="small text-muted">Update this before the end of the shift or when meaningful progress is made.</div>
                        </div>
                        <div class="progress-value" id="progressValue"><?= (int)$task['progress_percent'] ?>%</div>
                    </div>
                    <div class="progress mb-2" style="height:9px">
                        <div class="progress-bar <?= (int)$task['progress_percent'] >= 100 ? 'bg-success' : 'bg-primary' ?>"
                             id="progressBar" style="width:<?= (int)$task['progress_percent'] ?>%"></div>
                    </div>

                    <?php if ($role === 'Production' && $task['status'] === 'In Progress'): ?>
                        <form action="update_task.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                            <input type="hidden" name="action" value="update_progress">
                            <input type="range" name="progress_percent" id="progressSlider"
                                   class="progress-slider" min="<?= max(1,(int)$task['progress_percent']) ?>"
                                   max="100" step="1" value="<?= max(1,(int)$task['progress_percent']) ?>">
                            <div class="progress-scale"><span><?= max(1,(int)$task['progress_percent']) ?>%</span><span>50%</span><span>100%</span></div>
                            <div class="progress-save-grid">
                                <input type="text" name="progress_remarks" class="form-control"
                                       maxlength="500" placeholder="Optional shift update or work note">
                                <button class="btn btn-primary">Save Progress</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($progress_logs->num_rows > 0): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered mb-0 progress-history">
                                <thead class="table-dark"><tr><th>Date/Time</th><th>Progress</th><th>Update</th><th>By</th></tr></thead>
                                <tbody>
                                <?php while($progress_log=$progress_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= h($progress_log['created_at']) ?></td>
                                        <td><strong><?= (int)$progress_log['progress_percent'] ?>%</strong></td>
                                        <td><?= h($progress_log['remarks'] ?: '-') ?></td>
                                        <td><?= h($progress_log['fullname'] ?: 'User') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="row mb-4">

                <div class="col-md-4 mb-3">
                    <div class="status-box">
                        <small class="text-muted">JO No</small>
                        <h5><?php echo h($task['jo_no']); ?></h5>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="status-box">
                        <small class="text-muted">Department</small>
                        <h5><?php echo h($task['department_name']); ?></h5>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="status-box">
                        <small class="text-muted">Task Status</small>
                        <h5>
                            <?php if($task['status'] == 'Pending'){ ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php }elseif($task['status'] == 'Acknowledged'){ ?>
                                <span class="badge bg-info text-dark">Acknowledged</span>
                            <?php }elseif($task['status'] == 'In Progress'){ ?>
                                <span class="badge bg-primary">In Progress</span>
                            <?php }elseif($task['status'] == 'Completed'){ ?>
                                <span class="badge bg-success">Completed</span>
                            <?php }else{ ?>
                                <span class="badge bg-secondary"><?php echo h($task['status']); ?></span>
                            <?php } ?>
                        </h5>
                    </div>
                </div>

            </div>

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Client:</b> <?php echo h($task['client_name']); ?></p>
                    <p><b>Project:</b> <?php echo h($task['project_name']); ?></p>
                    <p><b>Engineer:</b> <?php echo h($task['engineer_name']); ?></p>
                    <p><b>Sales:</b> <?php echo h($task['sales_name']); ?></p>
                </div>

                <div class="col-md-6">
                    <p><b>Release Date:</b> <?php echo h($task['release_date']); ?></p>
                    <p><b>Due Date:</b> <?php echo h($task['due_date']); ?></p>
                    <p><b>Overall JO Status:</b> <?php echo h($task['workflow_status']); ?></p>
                    <p>
                        <b>Assigned To:</b>
                        <?php echo h($task['employee_no'] . ' - ' . $task['fullname']); ?>
                    </p>
                </div>

            </div>

            <hr>
            <h5>Drawing Attachment</h5>

            <div class="mb-4">

                <?php if($attachment){ ?>

                    <div class="mb-2">
                        <span class="badge <?= ($attachment['version_no'] ?? 'Original') === 'Original' ? 'bg-secondary' : 'bg-warning text-dark' ?>">
                            <?= h($attachment['version_no'] ?? 'Original') ?>
                        </span>
                        <?php if (($attachment['version_no'] ?? 'Original') !== 'Original'): ?>
                            <strong class="text-warning ms-1">Revised Drawing — use this latest version</strong>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($attachment['revision_notes'])): ?>
                        <div class="alert alert-warning py-2">
                            <strong>Revision details:</strong> <?= h($attachment['revision_notes']) ?>
                        </div>
                    <?php endif; ?>
                    <a href="../uploads/drawings/<?php echo rawurlencode(basename($attachment['file_name'])); ?>"
                       target="_blank"
                       class="btn btn-primary">
                        Open Latest Drawing
                    </a>

                <?php }else{ ?>

                    <div class="alert alert-warning">
                        No drawing attachment found.
                    </div>

                <?php } ?>

            </div>

            <hr>

            <h5>Task Timeline</h5>

            <div class="table-responsive mb-4">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Acknowledged At</th>
                            <th>Started At</th>
                            <th>Completed At</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><?php echo $task['acknowledged_at']; ?></td>
                            <td><?php echo $task['started_at']; ?></td>
                            <td><?php echo $task['completed_at']; ?></td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <?php if ($task['status'] === 'Completed' && (!empty($task['completion_remarks']) || !empty($task['completion_proof']))): ?>
                <div class="card border-success mb-3">
                    <div class="card-header bg-success text-white">
                        Completion Record
                    </div>
                    <div class="card-body">
                        <?php if (!empty($task['completion_remarks'])): ?>
                            <p class="mb-2"><strong>Remarks:</strong> <?= nl2br(h($task['completion_remarks'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($task['completion_proof'])): ?>
                            <a href="../uploads/task_proofs/<?= rawurlencode(basename($task['completion_proof'])) ?>"
                               target="_blank" class="btn btn-outline-success btn-sm">
                                View Proof Photo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <hr>

            

        </div>

    </div>

</main>

<script>
const taskFeedbackModal = document.getElementById('taskFeedbackModal');

function dismissTaskFeedback(){
    taskFeedbackModal?.remove();
}

taskFeedbackModal?.addEventListener('click', function(event){
    if(event.target === taskFeedbackModal) dismissTaskFeedback();
});
document.addEventListener('keydown', function(event){
    if(event.key === 'Escape') dismissTaskFeedback();
});
if(taskFeedbackModal){
    setTimeout(dismissTaskFeedback, <?= $task_feedback && $task_feedback['type'] === 'success' ? '2800' : '3800' ?>);
}
</script>

<?php if ($task['status'] === 'In Progress' && $started_timestamp): ?>
<script>
(function(){
    const startedAt = <?= $started_timestamp * 1000 ?>;
    const availableAt = <?= $complete_available_timestamp * 1000 ?>;
    const databaseNowAtLoad = <?= $database_now_timestamp * 1000 ?>;
    const browserNowAtLoad = Date.now();
    const timer = document.getElementById('elapsedWorkTime');
    const completeButton = document.getElementById('showCompletionForm');
    const completionPanel = document.getElementById('completionPanel');
    const progressSlider = document.getElementById('progressSlider');
    const progressValue = document.getElementById('progressValue');
    const progressBar = document.getElementById('progressBar');
    const savedProgressPercent = <?= (int)($task['progress_percent'] ?? 0) ?>;

    progressSlider?.addEventListener('input', function(){
        const previewProgress = Number(this.value);
        if(progressValue) progressValue.textContent = previewProgress + '%';
        if(progressBar) progressBar.style.width = previewProgress + '%';
    });

    function updateWorkTimer(){
        const now = databaseNowAtLoad + (Date.now() - browserNowAtLoad);
        const elapsedSeconds = Math.max(0, Math.floor((now - startedAt) / 1000));
        const hours = Math.floor(elapsedSeconds / 3600);
        const minutes = Math.floor((elapsedSeconds % 3600) / 60);
        const seconds = elapsedSeconds % 60;
        if(timer){
            timer.textContent =
                (hours ? String(hours).padStart(2,'0') + ':' : '') +
                String(minutes).padStart(2,'0') + ':' +
                String(seconds).padStart(2,'0');
        }
        if(completeButton && now >= availableAt && savedProgressPercent >= 100){
            completeButton.disabled = false;
            completeButton.textContent = 'Complete Task';
        }
    }

    completeButton?.addEventListener('click', function(){
        if(this.disabled) return;
        completionPanel?.classList.toggle('open');
        completionPanel?.querySelector('textarea')?.focus();
    });

    updateWorkTimer();
    setInterval(updateWorkTimer, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>

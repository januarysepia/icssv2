<?php

function startGlobalTheme(): void
{
    static $started = false;
    if ($started || PHP_SAPI === 'cli') {
        return;
    }
    $started = true;

    ob_start(function (string $output): string {
        if (stripos($output, '</head>') === false || stripos($output, '<html') === false) {
            if (stripos($output, 'alert(') !== false) {
                $output = str_ireplace('alert(', 'icssNotify(', $output);
                $output = preg_replace(
                    '/window\.location\s*=\s*([^;]+);/i',
                    'setTimeout(function(){ window.location = $1; }, 2600);',
                    $output
                );

                return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ICSS v2 ERP</title>
<style>
body{margin:0;background:#f4f6f9;font-family:Arial,sans-serif}
.icss-toast-layer{position:fixed;inset:0;z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding:28px 16px;pointer-events:none;background:rgba(15,23,42,.16)}
.icss-toast{position:relative;width:min(430px,100%);overflow:hidden;padding:16px 18px 17px 58px;border:1px solid #dbe3ec;border-radius:13px;color:#172033;background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.22);animation:icssToastIn .2s ease-out}
.icss-toast-icon{position:absolute;left:17px;top:16px;display:grid;place-items:center;width:30px;height:30px;border-radius:50%;color:#fff;background:#2563eb;font-weight:800}
.icss-toast.success .icss-toast-icon{background:#198754}.icss-toast.error .icss-toast-icon{background:#dc3545}.icss-toast.warning .icss-toast-icon{background:#f59e0b}
.icss-toast-title{margin-bottom:3px;font-size:14px;font-weight:800}.icss-toast-message{font-size:13px;line-height:1.45;color:#526071}
.icss-toast-timer{position:absolute;left:0;right:0;bottom:0;height:4px;background:#2563eb;animation:icssToastTimer 2.6s linear forwards}
.icss-toast.success .icss-toast-timer{background:#198754}.icss-toast.error .icss-toast-timer{background:#dc3545}
@keyframes icssToastIn{from{opacity:0;transform:translateY(-12px) scale(.98)}to{opacity:1;transform:none}}
@keyframes icssToastTimer{to{transform:translateX(-100%)}}
</style>
<script>
function icssNotify(message,type){
    const text=String(message||'Notification');
    const lowered=text.toLowerCase();
    type=type||(lowered.includes('success')||lowered.includes('saved')||lowered.includes('updated')||lowered.includes('completed')||lowered.includes('approved')?'success':
        (lowered.includes('error')||lowered.includes('invalid')||lowered.includes('failed')||lowered.includes('cannot')||lowered.includes('required')||lowered.includes('denied')?'error':'info'));
    const layer=document.createElement('div');
    layer.className='icss-toast-layer';
    layer.innerHTML='<div class="icss-toast '+type+'"><span class="icss-toast-icon">'+(type==='success'?'✓':type==='error'?'!':'i')+'</span><div class="icss-toast-title">'+(type==='success'?'Successful':type==='error'?'Unable to continue':'Notification')+'</div><div class="icss-toast-message"></div><div class="icss-toast-timer"></div></div>';
    layer.querySelector('.icss-toast-message').textContent=text;
    document.body.appendChild(layer);
    setTimeout(()=>layer.remove(),2600);
}
window.alert=icssNotify;
</script>
</head>
<body>
HTML
                    . $output .
                    '</body></html>';
            }
            return $output;
        }

        $head_assets = <<<'HTML'
<script>
(function(){
    const saved=localStorage.getItem('icss-theme');
    const dark=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.theme=saved||(dark?'dark':'light');
    document.documentElement.dataset.density='compact';
})();
</script>
<style id="icss-global-theme">
:root{color-scheme:light}
html[data-theme="dark"]{color-scheme:dark}

/* System-wide compact density */
html[data-density="compact"] body{
    font-size:.86rem;
    line-height:1.4;
}
html[data-density="compact"] h1{font-size:1.35rem}
html[data-density="compact"] h2{font-size:1.18rem}
html[data-density="compact"] h3{font-size:1.05rem}
html[data-density="compact"] h4{font-size:.96rem}
html[data-density="compact"] h5{font-size:.88rem}
html[data-density="compact"] h6{font-size:.8rem}
html[data-density="compact"] h1,
html[data-density="compact"] h2,
html[data-density="compact"] h3,
html[data-density="compact"] h4,
html[data-density="compact"] h5,
html[data-density="compact"] h6{line-height:1.25}
html[data-density="compact"] .container.mt-3,
html[data-density="compact"] .container.mt-4,
html[data-density="compact"] .container.mt-5,
html[data-density="compact"] .container-fluid.mt-3,
html[data-density="compact"] .container-fluid.mt-4,
html[data-density="compact"] .container-fluid.mt-5{margin-top:.85rem!important}
html[data-density="compact"] .container.mb-3,
html[data-density="compact"] .container.mb-4,
html[data-density="compact"] .container.mb-5,
html[data-density="compact"] .container-fluid.mb-3,
html[data-density="compact"] .container-fluid.mb-4,
html[data-density="compact"] .container-fluid.mb-5{margin-bottom:1rem!important}
html[data-density="compact"] .card{
    border-radius:10px;
}
html[data-density="compact"] .card-header{
    padding:.6rem .82rem;
}
html[data-density="compact"] .card-header h1,
html[data-density="compact"] .card-header h2,
html[data-density="compact"] .card-header h3,
html[data-density="compact"] .card-header h4,
html[data-density="compact"] .card-header h5{
    margin-bottom:0;
}
html[data-density="compact"] .card-body{
    padding:.82rem;
}
html[data-density="compact"] .card-footer{
    padding:.55rem .82rem;
}
html[data-density="compact"] label,
html[data-density="compact"] .form-label{
    margin-bottom:.25rem;
    font-size:.76rem;
    font-weight:650;
}
html[data-density="compact"] .form-control,
html[data-density="compact"] .form-select{
    min-height:34px;
    padding:.36rem .56rem;
    font-size:.8rem;
}
html[data-density="compact"] textarea.form-control{
    min-height:72px;
}
html[data-density="compact"] .form-text{
    margin-top:.2rem;
    font-size:.68rem;
}
html[data-density="compact"] .input-group-text{
    padding:.36rem .56rem;
    font-size:.78rem;
}
html[data-density="compact"] .btn{
    padding:.36rem .62rem;
    font-size:.78rem;
    line-height:1.35;
}
html[data-density="compact"] .btn-sm{
    padding:.24rem .46rem;
    font-size:.7rem;
}
html[data-density="compact"] .btn-lg{
    padding:.5rem .8rem;
    font-size:.88rem;
}
html[data-density="compact"] .alert{
    padding:.58rem .75rem;
    margin-bottom:.75rem;
    font-size:.78rem;
}
html[data-density="compact"] .badge{
    font-size:.67rem;
}
html[data-density="compact"] .table{
    margin-bottom:.75rem;
    font-size:.78rem;
}
html[data-density="compact"] .table > :not(caption) > * > *{
    padding:.48rem .55rem;
}
html[data-density="compact"] .table th{
    font-size:.7rem;
    line-height:1.25;
}
html[data-density="compact"] .pagination{
    --bs-pagination-padding-x:.52rem;
    --bs-pagination-padding-y:.28rem;
    --bs-pagination-font-size:.74rem;
}
html[data-density="compact"] .modal-header{
    padding:.65rem .85rem;
}
html[data-density="compact"] .modal-body{
    padding:.85rem;
}
html[data-density="compact"] .modal-footer{
    padding:.55rem .85rem;
}
html[data-density="compact"] .dropdown-item{
    padding:.36rem .7rem;
    font-size:.78rem;
}
html[data-density="compact"] .list-group-item{
    padding:.55rem .72rem;
}
html[data-density="compact"] hr{
    margin:.75rem 0;
}
html[data-density="compact"] .mb-3{margin-bottom:.65rem!important}
html[data-density="compact"] .mb-4{margin-bottom:.9rem!important}
html[data-density="compact"] .mb-5{margin-bottom:1.15rem!important}
html[data-density="compact"] .mt-3{margin-top:.65rem!important}
html[data-density="compact"] .mt-4{margin-top:.9rem!important}
html[data-density="compact"] .mt-5{margin-top:1.15rem!important}
html[data-density="compact"] .p-3{padding:.7rem!important}
html[data-density="compact"] .p-4{padding:.9rem!important}
html[data-density="compact"] .p-5{padding:1.15rem!important}
html[data-density="compact"] .py-3{padding-top:.7rem!important;padding-bottom:.7rem!important}
html[data-density="compact"] .py-4{padding-top:.9rem!important;padding-bottom:.9rem!important}
html[data-density="compact"] .py-5{padding-top:1.15rem!important;padding-bottom:1.15rem!important}
html[data-density="compact"] .page-container,
html[data-density="compact"] .asset-page{
    padding-top:12px!important;
    padding-bottom:20px!important;
}
@media(max-width:768px){
    html[data-density="compact"] body{font-size:.83rem}
    html[data-density="compact"] h1{font-size:1.2rem}
    html[data-density="compact"] h2{font-size:1.08rem}
    html[data-density="compact"] .card-header{padding:.55rem .68rem}
    html[data-density="compact"] .card-body{padding:.68rem}
    html[data-density="compact"] .table > :not(caption) > * > *{padding:.43rem .48rem}
}

html[data-theme="dark"] body{background:#151515!important;color:#ededed!important}
html[data-theme="dark"] .card,
html[data-theme="dark"] .modal-content,
html[data-theme="dark"] .dropdown-menu,
html[data-theme="dark"] .list-group-item,
html[data-theme="dark"] .table-card,
html[data-theme="dark"] .filter-box,
html[data-theme="dark"] .filter-card,
html[data-theme="dark"] .quick-links,
html[data-theme="dark"] .summary-card,
html[data-theme="dark"] .asset-panel,
html[data-theme="dark"] .asset-card,
html[data-theme="dark"] .queue-card,
html[data-theme="dark"] .stat-card,
html[data-theme="dark"] .jo-card{
    color:#ededed!important;background:#202020!important;border-color:#3a3a3a!important;
    box-shadow:0 2px 10px rgba(0,0,0,.22)!important
}
html[data-theme="dark"] .card-header:not(.bg-dark):not(.bg-primary):not(.bg-danger):not(.bg-success):not(.bg-warning):not(.bg-info),
html[data-theme="dark"] .modal-header,
html[data-theme="dark"] .modal-footer{background:#292929!important;border-color:#414141!important}
html[data-theme="dark"] h1,html[data-theme="dark"] h2,html[data-theme="dark"] h3,
html[data-theme="dark"] h4,html[data-theme="dark"] h5,html[data-theme="dark"] h6,
html[data-theme="dark"] label,html[data-theme="dark"] strong,html[data-theme="dark"] b{color:inherit}
html[data-theme="dark"] .text-dark{color:#e5e7eb!important}
html[data-theme="dark"] .text-muted,html[data-theme="dark"] small{color:#94a3b8!important}
html[data-theme="dark"] .bg-light{background-color:#191919!important}
html[data-theme="dark"] .bg-white{background-color:#202020!important}
html[data-theme="dark"] .border,html[data-theme="dark"] .border-top,
html[data-theme="dark"] .border-bottom,html[data-theme="dark"] .border-start,
html[data-theme="dark"] .border-end,html[data-theme="dark"] hr{border-color:#414141!important}
html[data-theme="dark"] .table{
    --bs-table-bg:#202020;--bs-table-color:#ededed;--bs-table-border-color:#414141;
    --bs-table-striped-bg:#262626;--bs-table-striped-color:#ededed;
    --bs-table-hover-bg:#303030;--bs-table-hover-color:#fff
}
html[data-theme="dark"] table td{background-color:#202020!important;color:#ededed;border-color:#414141!important}
html[data-theme="dark"] table tbody tr:hover td{background-color:#303030!important}
html[data-theme="dark"] .table-light{--bs-table-bg:#292929;--bs-table-color:#ededed}
html[data-theme="dark"] .form-control,html[data-theme="dark"] .form-select,
html[data-theme="dark"] input,html[data-theme="dark"] select,html[data-theme="dark"] textarea{
    color:#ededed!important;background-color:#191919!important;border-color:#4a4a4a!important
}
html[data-theme="dark"] .form-control::placeholder,
html[data-theme="dark"] input::placeholder,html[data-theme="dark"] textarea::placeholder{color:#7f8da3!important}
html[data-theme="dark"] .form-control:focus,html[data-theme="dark"] .form-select:focus,
html[data-theme="dark"] input:focus,html[data-theme="dark"] select:focus,html[data-theme="dark"] textarea:focus{
    color:#fff!important;background:#191919!important;border-color:#60a5fa!important;
    box-shadow:0 0 0 .2rem rgba(59,130,246,.18)!important
}
html[data-theme="dark"] .input-group-text{color:#d4d4d4;background:#2b2b2b;border-color:#4a4a4a}
html[data-theme="dark"] .page-link{color:#bfdbfe;background:#202020;border-color:#414141}
html[data-theme="dark"] .page-item.active .page-link{color:#fff;background:#2563eb;border-color:#2563eb}
html[data-theme="dark"] .page-item.disabled .page-link{color:#737373;background:#191919;border-color:#414141}
html[data-theme="dark"] .alert-info{color:#bae6fd;background:#0c3b55;border-color:#155e75}
html[data-theme="dark"] .alert-warning{color:#fde68a;background:#4b3510;border-color:#785718}
html[data-theme="dark"] .alert-secondary{color:#d1d5db;background:#273244;border-color:#3b4a61}
html[data-theme="dark"] .alert-success{color:#bbf7d0;background:#123d2b;border-color:#216e4a}
html[data-theme="dark"] .alert-danger{color:#fecaca;background:#4a1d25;border-color:#7f2936}
html[data-theme="dark"] a:not(.btn):not(.sidebar a):not(.topbar-icon-btn):not(.logout-btn){color:#93c5fd}
html[data-theme="dark"] .btn-outline-dark{
    color:#e5e7eb!important;
    background:#2a2a2a!important;
    border-color:#6b7280!important
}
html[data-theme="dark"] .btn-outline-dark:hover,
html[data-theme="dark"] .btn-outline-dark:focus{
    color:#fff!important;
    background:#2563eb!important;
    border-color:#60a5fa!important
}
html[data-theme="dark"] .btn-outline-secondary{
    color:#e5e7eb!important;
    border-color:#737373!important
}
html[data-theme="dark"] .btn-outline-primary{
    color:#93c5fd!important;
    border-color:#60a5fa!important
}
html[data-theme="dark"] .search-results{background:#202020!important;border-color:#4a4a4a!important}
html[data-theme="dark"] .search-result{color:#ededed;background:#202020!important;border-color:#414141!important}
html[data-theme="dark"] .search-result:hover{background:#303030!important}

/* Asset module contrast and hierarchy */
html[data-theme="dark"] body.asset-module{background:#151515!important;color:#ededed!important}
html[data-theme="dark"] body.asset-module .asset-module-nav{
    background:#202020!important;border:1px solid #3a3a3a!important;
    box-shadow:0 2px 8px rgba(0,0,0,.24)!important
}
html[data-theme="dark"] body.asset-module .asset-module-nav a{
    color:#bfdbfe!important
}
html[data-theme="dark"] body.asset-module .asset-module-nav a:hover{
    color:#fff!important;background:#333!important
}
html[data-theme="dark"] body.asset-module .asset-module-nav a.active{
    color:#fff!important;background:#2563eb!important
}
html[data-theme="dark"] body.asset-module .asset-page h2,
html[data-theme="dark"] body.asset-module .asset-page h3,
html[data-theme="dark"] body.asset-module .asset-page h4,
html[data-theme="dark"] body.asset-module .asset-page h5{
    color:#f8fafc!important
}
html[data-theme="dark"] body.asset-module .asset-page .cards .card h3,
html[data-theme="dark"] body.asset-module .asset-page .stat-title{
    color:#aebdd2!important
}
html[data-theme="dark"] body.asset-module .asset-page .card .number,
html[data-theme="dark"] body.asset-module .asset-page .stat-number{
    color:#f8fafc!important
}
html[data-theme="dark"] body.asset-module .asset-page .overdue-card .number,
html[data-theme="dark"] body.asset-module .asset-page .text-danger{
    color:#fb7185!important
}
html[data-theme="dark"] body.asset-module .asset-page .empty,
html[data-theme="dark"] body.asset-module .asset-page .small-text,
html[data-theme="dark"] body.asset-module .asset-page .small,
html[data-theme="dark"] body.asset-module .asset-page .text-muted{
    color:#9fb0c8!important
}
html[data-theme="dark"] body.asset-module .asset-page table th{
    color:#fafafa!important;background:#292929!important;border-color:#505050!important
}
html[data-theme="dark"] body.asset-module .asset-page table td{
    color:#ededed!important;background:#202020!important;border-color:#414141!important
}
html[data-theme="dark"] body.asset-module .asset-page table tbody tr:hover td{
    color:#fff!important;background:#303030!important
}
html[data-theme="dark"] body.asset-module .asset-page table td:last-child{
    background:#202020!important;box-shadow:-6px 0 10px rgba(0,0,0,.18)!important
}
html[data-theme="dark"] body.asset-module .asset-page table tbody tr:hover td:last-child{
    background:#303030!important
}
html[data-theme="dark"] body.asset-module .asset-page .detail-item,
html[data-theme="dark"] body.asset-module .asset-page .info-item{
    color:#ededed!important;background:#191919!important;border-color:#414141!important
}
html[data-theme="dark"] body.asset-module .asset-page .detail-item label,
html[data-theme="dark"] body.asset-module .asset-page .info-item label{
    color:#aebdd2!important
}
.icss-floating-theme{
    position:fixed;right:18px;bottom:18px;z-index:5000;width:42px;height:42px;
    display:flex;align-items:center;justify-content:center;border:1px solid #d1d5db;
    border-radius:12px;background:#fff;color:#111827;box-shadow:0 5px 18px rgba(0,0,0,.2);
    cursor:pointer;font-size:1rem
}
html[data-theme="dark"] .icss-floating-theme{color:#fafafa;background:#2b2b2b;border-color:#4a4a4a}
.icss-floating-back{
    position:fixed;left:18px;bottom:18px;z-index:5000;
    display:inline-flex;align-items:center;justify-content:center;gap:5px;
    min-height:40px;padding:0 13px;border:1px solid #cbd5e1;border-radius:11px;
    color:#1f2937;background:#fff;box-shadow:0 5px 18px rgba(0,0,0,.18);
    cursor:pointer;font-size:.76rem;font-weight:700
}
.icss-floating-back:hover{color:#fff;background:#2563eb;border-color:#2563eb}
html[data-theme="dark"] .icss-floating-back{
    color:#f5f5f5;background:#2b2b2b;border-color:#525252
}
html[data-theme="dark"] .icss-floating-back:hover{
    color:#fff;background:#2563eb;border-color:#60a5fa
}
body:has(#themeToggle) .icss-floating-theme{display:none}
@media(max-width:576px){
    .icss-floating-back{left:10px;bottom:10px;min-height:36px;padding:0 10px;font-size:.7rem}
}
@media print{.icss-floating-theme,.icss-floating-back,#themeToggle{display:none!important}}

/* System-wide temporary notifications */
.icss-toast-container{
    position:fixed;top:76px;left:50%;z-index:100000;width:min(430px,calc(100% - 28px));
    transform:translateX(-50%);pointer-events:none
}
.icss-global-toast{
    position:relative;overflow:hidden;padding:14px 17px 16px 55px;border:1px solid #dbe3ec;
    border-radius:13px;color:#172033;background:#fff;box-shadow:0 16px 42px rgba(15,23,42,.24);
    animation:icssGlobalToastIn .2s ease-out
}
.icss-global-toast-icon{
    position:absolute;left:16px;top:14px;display:grid;place-items:center;width:29px;height:29px;
    border-radius:50%;color:#fff;background:#2563eb;font-weight:800
}
.icss-global-toast.success .icss-global-toast-icon{background:#198754}
.icss-global-toast.error .icss-global-toast-icon{background:#dc3545}
.icss-global-toast.warning .icss-global-toast-icon{background:#f59e0b}
.icss-global-toast-title{margin-bottom:2px;font-size:.8rem;font-weight:800}
.icss-global-toast-message{color:#526071;font-size:.76rem;line-height:1.45}
.icss-global-toast-timer{
    position:absolute;left:0;right:0;bottom:0;height:4px;background:#2563eb;
    transform-origin:left;animation:icssGlobalToastTimer var(--toast-duration,3200ms) linear forwards
}
.icss-global-toast.success .icss-global-toast-timer{background:#198754}
.icss-global-toast.error .icss-global-toast-timer{background:#dc3545}
html[data-theme="dark"] .icss-global-toast{color:#f8fafc;background:#262626;border-color:#494949}
html[data-theme="dark"] .icss-global-toast-message{color:#cbd5e1}
@keyframes icssGlobalToastIn{from{opacity:0;transform:translateY(-10px) scale(.98)}to{opacity:1;transform:none}}
@keyframes icssGlobalToastOut{to{opacity:0;transform:translateY(-8px)}}
@keyframes icssGlobalToastTimer{to{transform:scaleX(0)}}
@media(max-width:576px){.icss-toast-container{top:66px}}
</style>
<script>
(function(){
    function inferNotificationType(message,type){
        if(type) return type;
        const text=String(message||'').toLowerCase();
        if(/success|saved|updated|completed|approved|submitted|created|returned|received|generated/.test(text)) return 'success';
        if(/error|invalid|failed|cannot|can't|required|denied|not found|unable|missing|already exists/.test(text)) return 'error';
        if(/warning|pending|please select|please enter|must be/.test(text)) return 'warning';
        return 'info';
    }

    window.icssNotify=function(message,type,duration){
        const text=String(message||'Notification');
        const resolvedType=inferNotificationType(text,type);
        const timeout=Number(duration)||((resolvedType==='error'||resolvedType==='warning')?3800:2800);
        let container=document.getElementById('icssToastContainer');
        if(!container){
            container=document.createElement('div');
            container.id='icssToastContainer';
            container.className='icss-toast-container';
            document.body.appendChild(container);
        }
        container.innerHTML='';
        const toast=document.createElement('div');
        toast.className='icss-global-toast '+resolvedType;
        toast.style.setProperty('--toast-duration',timeout+'ms');
        toast.innerHTML='<span class="icss-global-toast-icon" aria-hidden="true"></span><div class="icss-global-toast-title"></div><div class="icss-global-toast-message"></div><div class="icss-global-toast-timer"></div>';
        toast.querySelector('.icss-global-toast-icon').textContent=resolvedType==='success'?'✓':resolvedType==='error'?'!':resolvedType==='warning'?'!':'i';
        toast.querySelector('.icss-global-toast-title').textContent=resolvedType==='success'?'Successful':resolvedType==='error'?'Unable to continue':resolvedType==='warning'?'Please check':'Notification';
        toast.querySelector('.icss-global-toast-message').textContent=text;
        container.appendChild(toast);
        setTimeout(function(){
            toast.style.animation='icssGlobalToastOut .18s ease-in forwards';
            setTimeout(function(){toast.remove();},190);
        },timeout);
    };

    window.alert=function(message){window.icssNotify(message);};

    function convertStatusAlert(alertBox){
        if(!alertBox || alertBox.dataset.icssNotified==='1' || alertBox.dataset.persistentAlert==='1') return;
        const type=alertBox.classList.contains('alert-success')?'success':
            (alertBox.classList.contains('alert-danger')?'error':'');
        if(!type) return;
        const message=(alertBox.textContent||'').trim().replace(/\s+/g,' ');
        if(!message) return;
        alertBox.dataset.icssNotified='1';
        alertBox.style.display='none';
        window.icssNotify(message,type);
    }

    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.alert-success,.alert-danger').forEach(convertStatusAlert);
        const observer=new MutationObserver(function(mutations){
            mutations.forEach(function(mutation){
                mutation.addedNodes.forEach(function(node){
                    if(node.nodeType!==1) return;
                    if(node.matches?.('.alert-success,.alert-danger')) convertStatusAlert(node);
                    node.querySelectorAll?.('.alert-success,.alert-danger').forEach(convertStatusAlert);
                });
            });
        });
        observer.observe(document.body,{childList:true,subtree:true});
    });
})();
</script>
HTML;

        $body_assets = <<<'HTML'
<button type="button" class="icss-floating-back" id="globalBackButton"
        aria-label="Go back to previous page" title="Back to previous page">← Back</button>
<button type="button" class="icss-floating-theme" id="globalThemeToggle"
        aria-label="Toggle dark mode" title="Toggle dark mode">🌙</button>
<script>
(function(){
    const backButton=document.getElementById('globalBackButton');
    if(backButton){
        const currentPath=window.location.pathname.toLowerCase().replace(/\/+$/,'');
        const hiddenBackPaths=[
            '/icssv2',
            '/icssv2/index.php',
            '/icssv2/login.php',
            '/icssv2/dashboard',
            '/icssv2/dashboard/index.php',
            '/icssv2/purchase_requests/email_approval.php'
        ];
        if(hiddenBackPaths.includes(currentPath)){
            backButton.hidden=true;
        }

        backButton.addEventListener('click',function(){
            let previousInternal=false;
            let previousAllowed=false;
            try{
                if(document.referrer){
                    const previous=new URL(document.referrer);
                    previousInternal=previous.origin===window.location.origin;
                    const previousPath=previous.pathname.toLowerCase().replace(/\/+$/,'');
                    const blockedPaths=[
                        '/icssv2/login.php',
                        '/icssv2/logout.php',
                        '/icssv2/authenticate.php',
                        '/icssv2/auth'
                    ];
                    previousAllowed=!blockedPaths.some(function(path){
                        return previousPath===path||previousPath.startsWith(path+'/');
                    });
                }
            }catch(error){}

            if(previousInternal&&previousAllowed&&window.history.length>1){
                window.history.back();
                return;
            }

            window.location.replace('/icssv2/');
        });
    }

    const button=document.getElementById('globalThemeToggle');
    if(!button)return;
    function sync(){
        const dark=document.documentElement.dataset.theme==='dark';
        button.textContent=dark?'☀️':'🌙';
        button.title=dark?'Use light mode':'Use dark mode';
    }
    button.addEventListener('click',function(){
        const next=document.documentElement.dataset.theme==='dark'?'light':'dark';
        document.documentElement.dataset.theme=next;
        localStorage.setItem('icss-theme',next);
        sync();
    });
    sync();
})();
</script>
HTML;

        $output = preg_replace('/<\/head>/i', $head_assets . "\n</head>", $output, 1);
        if (stripos($output, '</body>') !== false) {
            $output = preg_replace('/<\/body>/i', $body_assets . "\n</body>", $output, 1);
        }
        return $output;
    });
}

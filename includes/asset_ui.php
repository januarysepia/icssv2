<style>
body.asset-module {
    background: #f4f6f9;
    color: #111827;
}

/* Asset module uses the full browser workspace for wide operational tables. */
body.asset-module .sidebar,
body.asset-module .mobile-menu-btn {
    display: none !important;
}

body.asset-module .content-wrapper {
    margin-left: 0 !important;
    width: 100%;
}

.asset-page {
    padding: 16px 18px 26px;
    width: 100%;
    max-width: none;
}

.asset-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.asset-page-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
}

.asset-page-subtitle {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
}

.asset-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.asset-module-nav {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
    padding: 6px;
    margin-bottom: 14px;
    background: #fff;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
}

.asset-module-nav a {
    padding: 8px 10px;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    border-radius: 7px;
}

.asset-module-nav a:hover {
    color: #111827;
    background: #f3f4f6;
}

.asset-module-nav a.active {
    color: #fff;
    background: #111827;
}

.asset-page .card,
.asset-panel,
.asset-page .filter-box,
.asset-page .filter-card,
.asset-page .table-card,
.asset-page .quick-links,
.asset-page .asset-card {
    background: #fff;
    border: 0;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
    margin-bottom: 14px;
}

.asset-page .card,
.asset-panel,
.asset-page .filter-box,
.asset-page .filter-card,
.asset-page .table-card,
.asset-page .quick-links,
.asset-page .asset-card {
    padding: 14px;
}

.asset-page .card-header {
    margin: -14px -14px 14px;
    padding: 11px 14px;
    border-radius: 10px 10px 0 0 !important;
}

.asset-page h3,
.asset-page h5 {
    color: #111827;
    font-weight: 700;
}

.asset-page h2 {
    font-size: 1.35rem;
    font-weight: 750;
}

.asset-page h3 {
    font-size: 1rem;
}

.asset-page h4 {
    font-size: 1.05rem;
    font-weight: 700;
}

.asset-page h5 {
    font-size: .95rem;
}

.asset-page .cards {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 9px;
    margin-bottom: 14px;
}

.asset-page .cards .card {
    margin: 0;
    min-width: 0;
}

.asset-page .cards .card {
    padding: 12px 13px;
    min-height: 92px;
}

.asset-page .cards .card h3 {
    color: #6b7280;
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .035em;
}

.asset-page .cards .card .number {
    margin-top: 7px;
    font-size: 1.55rem;
    line-height: 1;
}

.asset-page .card .number {
    color: #111827;
}

.asset-page .overdue-card {
    border-left: 5px solid #dc3545;
}

.asset-page .quick-links a,
.asset-page .top-actions a {
    display: inline-block;
    margin: 0;
    padding: 6px 9px;
    border-radius: 7px;
    background: #111827;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
}

.asset-page .quick-links a:hover,
.asset-page .top-actions a:hover {
    background: #1f2937;
    color: #fff;
}

.asset-page .table-responsive,
.asset-page .table-wrap,
.asset-page .table-card {
    overflow-x: auto;
}

.asset-page table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: collapse;
    font-size: 12px;
}

.asset-page table th {
    background: #212529;
    color: #fff;
    padding: 8px 9px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .02em;
    white-space: nowrap;
}

.asset-page table td {
    padding: 7px 9px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
}

.asset-page table th:last-child,
.asset-page table td:last-child {
    position: sticky;
    right: 0;
    z-index: 2;
}

.asset-page table th:last-child {
    background: #212529;
}

.asset-page table td:last-child {
    background: #fff;
    box-shadow: -6px 0 10px rgba(15, 23, 42, .06);
}

.asset-page table td:last-child:has(.dropdown-menu.show) {
    z-index: 1080;
}

.asset-page .dropdown {
    position: static;
}

.asset-page .dropdown-menu {
    z-index: 1090;
    min-width: 160px;
}

.asset-page table tbody tr:hover td:last-child {
    background: #f8fafc;
}

.asset-page .asset-row-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(74px, 1fr));
    gap: 4px;
    min-width: 140px;
}

.asset-page .asset-row-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 29px;
    padding: 4px 7px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
}

.asset-page table tbody tr:hover {
    background: #f8fafc;
}

.asset-page input,
.asset-page select,
.asset-page textarea {
    max-width: 100%;
    font-size: 12px;
}

.asset-page .form-control,
.asset-page .form-select {
    min-height: 34px;
    padding: 6px 9px;
}

.asset-page .btn {
    font-size: 12px;
}

.asset-page .btn-sm {
    padding: 4px 7px;
    font-size: 11px;
}

.asset-page .alert {
    padding: 9px 11px;
    margin-bottom: 12px;
    font-size: 12px;
    border-radius: 8px;
}

.asset-page .filter-grid {
    align-items: end;
}

.asset-page .info-grid,
.asset-page .employee-grid,
.asset-page .details-grid {
    gap: 9px;
}

.asset-page .info-item,
.asset-page .detail-item {
    padding: 9px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.asset-page .info-item label,
.asset-page .detail-item label {
    display: block;
    margin-bottom: 3px;
    color: #6b7280;
    font-size: 10px;
    font-weight: 700;
}

.asset-page .info-item strong,
.asset-page .detail-item strong {
    display: block;
}

.asset-page .empty {
    text-align: center;
    color: #6b7280;
    padding: 20px !important;
    font-size: 12px;
}

@media (max-width: 1199px) {
    .asset-page .cards {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    body.asset-module .topbar {
        padding-left: 15px;
    }

    .asset-page {
        padding: 16px 12px 28px;
    }

    .asset-page-header,
    .asset-page .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .asset-actions,
    .asset-page .top-actions {
        width: 100%;
    }

    .asset-actions .btn,
    .asset-page .top-actions a {
        flex: 1 1 auto;
        text-align: center;
    }

    .asset-page .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .asset-module-nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .asset-page .filter-grid,
    .asset-page .info-grid,
    .asset-page .employee-grid,
    .asset-page .details-grid,
    .asset-page .asset-grid {
        grid-template-columns: 1fr !important;
    }

    .asset-page table { min-width: 760px; }

    .asset-page .asset-row-actions {
        grid-template-columns: 1fr;
        min-width: 82px;
    }
}

@media (max-width: 480px) {
    .asset-page .cards {
        grid-template-columns: 1fr;
    }

    .asset-module-nav {
        grid-template-columns: 1fr;
    }
}

@media print {
    .sidebar,
    .mobile-menu-btn,
    .topbar,
    .asset-page-header,
    .asset-page .page-header,
    .asset-actions,
    .asset-page .top-actions {
        display: none !important;
    }

    .content-wrapper {
        margin-left: 0 !important;
    }
}
</style>

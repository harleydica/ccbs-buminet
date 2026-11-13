<style>
/* ==========================================================================
   Ticketing Module Theme Styles (Light Mode & Dark Mode)
   Scoped strictly to .ticketing-page and .dark-mode .ticketing-page
   ========================================================================== */

/* --------------------------------------------------------------------------
   Light Mode Base & Refinements
   -------------------------------------------------------------------------- */
.ticketing-page .ticketing-filter-panel {
    background: #f9f9f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.ticketing-page .ticketing-filter-panel label {
    font-weight: 600;
    color: #4a5568;
}

.ticketing-page .ticketing-table-header th,
.ticketing-page .table>thead>tr>th {
    background-color: #f4f4f4;
    color: #2d3748;
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
}

.ticketing-page .ticketing-empty-state {
    padding: 25px;
    color: #718096;
}

/* --------------------------------------------------------------------------
   Dark Mode Adjustments
   -------------------------------------------------------------------------- */

/* Stat Badges / Info Boxes */
.dark-mode .ticketing-page .info-box {
    background-color: #2d3748 !important;
    color: #cbd5e0 !important;
    border: 1px solid #4a5568 !important;
    box-shadow: none !important;
    border-radius: 4px;
}

.dark-mode .ticketing-page .info-box-content {
    color: inherit;
}

.dark-mode .ticketing-page .info-box-text {
    color: #cbd5e0 !important;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.dark-mode .ticketing-page .info-box-number {
    color: #f7fafc !important;
    font-size: 22px;
    font-weight: 700;
}

/* Filter Panel */
.dark-mode .ticketing-page .ticketing-filter-panel {
    background-color: #1a202c !important;
    border: 1px solid #4a5568 !important;
}

.dark-mode .ticketing-page .ticketing-filter-panel label {
    color: #e2e8f0 !important;
    font-weight: 600;
}

/* Form Controls (Inputs, Selects, Textareas, Datetime) */
.dark-mode .ticketing-page .form-control {
    background-color: #1a202c !important;
    color: #f7fafc !important;
    border: 1px solid #4a5568 !important;
    color-scheme: dark;
}

.dark-mode .ticketing-page .form-control:focus {
    background-color: #1a202c !important;
    color: #ffffff !important;
    border-color: #63b3ed !important;
    box-shadow: 0 0 0 2px rgba(99, 179, 237, 0.25) !important;
    outline: none !important;
}

.dark-mode .ticketing-page .form-control::placeholder {
    color: #718096 !important;
    opacity: 1;
}

.dark-mode .ticketing-page .form-control:disabled,
.dark-mode .ticketing-page .form-control[readonly] {
    background-color: #2d3748 !important;
    color: #a0aec0 !important;
    border-color: #4a5568 !important;
    cursor: not-allowed;
}

.dark-mode .ticketing-page select option {
    background-color: #1a202c;
    color: #f7fafc;
}

.dark-mode .ticketing-page input[type="datetime-local"] {
    color-scheme: dark;
}

.dark-mode .ticketing-page input[type="radio"],
.dark-mode .ticketing-page input[type="checkbox"] {
    color-scheme: dark;
}

.dark-mode .ticketing-page .radio-inline,
.dark-mode .ticketing-page .checkbox-inline,
.dark-mode .ticketing-page label {
    color: #e2e8f0;
}

.dark-mode .ticketing-page .control-label {
    color: #e2e8f0 !important;
}

/* Buttons */
.dark-mode .ticketing-page .btn-default {
    background-color: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #718096 !important;
}

.dark-mode .ticketing-page .btn-default:hover,
.dark-mode .ticketing-page .btn-default:focus,
.dark-mode .ticketing-page .btn-default:active {
    background-color: #718096 !important;
    color: #ffffff !important;
    border-color: #a0aec0 !important;
}

.dark-mode .ticketing-page .btn-primary {
    background-color: #3182ce !important;
    border-color: #2b6cb0 !important;
    color: #ffffff !important;
}

.dark-mode .ticketing-page .btn-primary:hover,
.dark-mode .ticketing-page .btn-primary:focus {
    background-color: #2b6cb0 !important;
    border-color: #2c5282 !important;
    color: #ffffff !important;
}

.dark-mode .ticketing-page .btn-info {
    background-color: #319795 !important;
    border-color: #285e61 !important;
    color: #ffffff !important;
}

.dark-mode .ticketing-page .btn-warning {
    background-color: #d69e2e !important;
    border-color: #b7791f !important;
    color: #1a202c !important;
    font-weight: 600;
}

.dark-mode .ticketing-page .btn-danger {
    background-color: #e53e3e !important;
    border-color: #c53030 !important;
    color: #ffffff !important;
}

.dark-mode .ticketing-page .btn-success {
    background-color: #38a169 !important;
    border-color: #2f855a !important;
    color: #ffffff !important;
}

/* Tables */
.dark-mode .ticketing-page .table {
    background-color: #2d3748 !important;
    color: #cbd5e0 !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .ticketing-table-header th,
.dark-mode .ticketing-page .table>thead>tr>th,
.dark-mode .ticketing-page .table>tbody>tr>th {
    background-color: #242c3d !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .table>tbody>tr>td {
    background-color: #2d3748 !important;
    color: #cbd5e0 !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .table>tbody>tr:nth-child(even)>td,
.dark-mode .ticketing-page .table-striped>tbody>tr:nth-child(even)>td,
.dark-mode .ticketing-page .table-striped>tbody>tr:nth-of-type(even)>td {
    background-color: #252e3d !important;
}

.dark-mode .ticketing-page .table-striped>tbody>tr:nth-child(odd)>td,
.dark-mode .ticketing-page .table-striped>tbody>tr:nth-of-type(odd)>td {
    background-color: #2d3748 !important;
}

.dark-mode .ticketing-page .table-hover>tbody>tr:hover>td,
.dark-mode .ticketing-page .table-hover>tbody>tr:hover>th {
    background-color: #3a475d !important;
    color: #ffffff !important;
}

/* Override phpnuxbill.css global td:first-child hardcoded #4a4949 */
.dark-mode .ticketing-page .table>tbody>tr>td:first-child {
    background-color: #2d3748 !important;
}

.dark-mode .ticketing-page .table>tbody>tr:nth-child(even)>td:first-child,
.dark-mode .ticketing-page .table-striped>tbody>tr:nth-child(even)>td:first-child,
.dark-mode .ticketing-page .table-striped>tbody>tr:nth-of-type(even)>td:first-child {
    background-color: #252e3d !important;
}

.dark-mode .ticketing-page .table>thead>tr>th:first-child,
.dark-mode .ticketing-page .table>tbody>tr>th:first-child,
.dark-mode .ticketing-page .ticketing-table-header th:first-child {
    background-color: #242c3d !important;
}

.dark-mode .ticketing-page .table-hover>tbody>tr:hover>td:first-child {
    background-color: #3a475d !important;
}

/* Table Content Links & Text */
.dark-mode .ticketing-page .table a:not(.btn) {
    color: #63b3ed !important;
}

.dark-mode .ticketing-page .table a:not(.btn):hover {
    color: #90cdf4 !important;
    text-decoration: underline;
}

.dark-mode .ticketing-page .text-muted {
    color: #a0aec0 !important;
}

.dark-mode .ticketing-page .text-success {
    color: #68d391 !important;
    font-weight: 500;
}

.dark-mode .ticketing-page .ticketing-empty-state {
    color: #a0aec0 !important;
}

/* Badges / Labels (High-contrast WCAG AA compliant) */
.dark-mode .ticketing-page .label {
    font-weight: 600;
    letter-spacing: 0.3px;
    padding: 3px 7px;
}

.dark-mode .ticketing-page .label-default {
    background-color: #4a5568 !important;
    color: #f7fafc !important;
    border: 1px solid #718096;
}

.dark-mode .ticketing-page .label-primary {
    background-color: #2b6cb0 !important;
    color: #ebf8ff !important;
}

.dark-mode .ticketing-page .label-info {
    background-color: #2c5282 !important;
    color: #ebf8ff !important;
}

.dark-mode .ticketing-page .label-success {
    background-color: #22543d !important;
    color: #c6f6d5 !important;
}

.dark-mode .ticketing-page .label-warning {
    background-color: #744210 !important;
    color: #fefcbf !important;
}

.dark-mode .ticketing-page .label-danger {
    background-color: #742a2a !important;
    color: #fed7d7 !important;
}

/* Pagination */
.dark-mode .ticketing-page .pagination>li>a,
.dark-mode .ticketing-page .pagination>li>span {
    background-color: #2d3748 !important;
    color: #cbd5e0 !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .pagination>li>a:hover,
.dark-mode .ticketing-page .pagination>li>a:focus {
    background-color: #4a5568 !important;
    color: #ffffff !important;
    border-color: #718096 !important;
}

.dark-mode .ticketing-page .pagination>.active>a,
.dark-mode .ticketing-page .pagination>.active>span,
.dark-mode .ticketing-page .pagination>.active>a:hover,
.dark-mode .ticketing-page .pagination>.active>span:hover {
    background-color: #3182ce !important;
    color: #ffffff !important;
    border-color: #3182ce !important;
}

.dark-mode .ticketing-page .pagination>.disabled>a,
.dark-mode .ticketing-page .pagination>.disabled>span,
.dark-mode .ticketing-page .pagination>.disabled>a:hover,
.dark-mode .ticketing-page .pagination>.disabled>span:hover {
    background-color: #1a202c !important;
    color: #718096 !important;
    border-color: #4a5568 !important;
    cursor: not-allowed !important;
}

/* Panels, Boxes, and Cards on View/Add/Edit Pages */
.dark-mode .ticketing-page .panel {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .panel-primary {
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .panel-primary>.panel-heading,
.dark-mode .ticketing-page .panel-default>.panel-heading {
    background-color: #242c3d !important;
    color: #e2e8f0 !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .panel-body {
    background-color: #2d3748 !important;
    color: #cbd5e0 !important;
}

.dark-mode .ticketing-page .panel-footer {
    background-color: #242c3d !important;
    color: #cbd5e0 !important;
    border-color: #4a5568 !important;
}

.dark-mode .ticketing-page .box {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #cbd5e0 !important;
}

.dark-mode .ticketing-page .box-header {
    background-color: transparent !important;
    color: #f7fafc !important;
    border-bottom: 1px solid #4a5568 !important;
}

.dark-mode .ticketing-page .box-title {
    color: #f7fafc !important;
}

.dark-mode .ticketing-page .box-body {
    background-color: #2d3748 !important;
    color: #cbd5e0 !important;
}

.dark-mode .ticketing-page .box.box-widget {
    border: 1px solid #4a5568 !important;
}

.dark-mode .ticketing-page .box-header.with-border {
    border-bottom: 1px solid #4a5568 !important;
}

.dark-mode .ticketing-page .user-block .username {
    color: #f7fafc !important;
}

.dark-mode .ticketing-page .user-block .description {
    color: #a0aec0 !important;
}

.dark-mode .ticketing-page .box.box-default {
    border-top-color: #718096 !important;
    border-left: 1px solid #4a5568;
    border-right: 1px solid #4a5568;
    border-bottom: 1px solid #4a5568;
}

.dark-mode .ticketing-page .box.box-info {
    border-top-color: #319795 !important;
    border-left: 1px solid #4a5568;
    border-right: 1px solid #4a5568;
    border-bottom: 1px solid #4a5568;
}

.dark-mode .ticketing-page .box.box-warning {
    border-top-color: #d69e2e !important;
    border-left: 1px solid #4a5568;
    border-right: 1px solid #4a5568;
    border-bottom: 1px solid #4a5568;
}

.dark-mode .ticketing-page .alert-warning {
    background-color: #744210 !important;
    border-color: #975a16 !important;
    color: #fefcbf !important;
}

.dark-mode .ticketing-page hr {
    border-top: 1px solid #4a5568 !important;
}

.dark-mode .ticketing-page .help-block,
.dark-mode .ticketing-page .help-block small {
    color: #a0aec0 !important;
}

.dark-mode .ticketing-page strong {
    color: #f7fafc;
}
</style>

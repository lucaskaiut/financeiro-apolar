/** Shared print styles mirroring PhpSpreadsheet XLSX helpers in ReportService. */
export const REPORT_PRINT_STYLES = `
  @page { margin: 12mm; size: auto; }

  * { box-sizing: border-box; }

  html, body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    color-adjust: exact;
  }

  body {
    font-family: Arial, Helvetica, sans-serif;
    color: #111827;
    margin: 24px;
    font-size: 11px;
    line-height: 1.35;
  }

  .report-title {
    font-size: 14px;
    font-weight: 700;
    margin: 0 0 4px;
    color: #111827;
  }

  .report-subtitle,
  .report-meta {
    font-size: 11px;
    color: #4b5563;
    margin: 0 0 2px;
    font-weight: 400;
  }

  .report-summary-line {
    font-size: 11px;
    color: #4b5563;
    margin: 0 0 12px;
    font-weight: 400;
  }

  .report-meta:last-of-type,
  .report-subtitle:last-of-type {
    margin-bottom: 12px;
  }

  table.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin: 0 0 14px;
    table-layout: auto;
  }

  table.report-table th,
  table.report-table td {
    border: 1px solid #e5e7eb;
    padding: 5px 8px;
    vertical-align: middle;
    text-align: left;
  }

  table.report-table thead th,
  table.report-table tr.column-header th,
  table.report-table tr.column-header td {
    background: #dbeafe;
    font-weight: 700;
    color: #111827;
  }

  table.report-table tr.column-header-muted th,
  table.report-table tr.column-header-muted td {
    background: #f3f4f6;
    font-weight: 700;
    color: #111827;
  }

  table.report-table th.amount,
  table.report-table td.amount,
  table.report-table .amount {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }

  table.report-table td.label-bold,
  table.report-table .label-bold {
    font-weight: 700;
  }

  /* applyXlsxSectionBanner: blue fill + bold, no borders, no uppercase */
  table.report-table tr.section-banner td {
    background: #dbeafe;
    font-weight: 700;
    border: none;
    padding: 6px 8px;
  }

  table.report-table tr.section-banner-muted td {
    background: #f3f4f6;
    font-weight: 700;
    border: none;
    padding: 6px 8px;
  }

  table.report-table tr.section-subtitle td {
    border: none;
    font-weight: 700;
    padding: 6px 8px 4px;
    background: transparent;
  }

  /* applyXlsxSubtotalRow default / soft / group */
  table.report-table tr.subtotal-row td {
    background: #dbeafe;
    font-weight: 700;
  }

  table.report-table tr.subtotal-row-soft td {
    background: #eff6ff;
    font-weight: 700;
  }

  table.report-table tr.subtotal-row-group td {
    background: #bfdbfe;
    font-weight: 700;
  }

  /* applyXlsxTotalRow (+ grand top border) */
  table.report-table tr.total-row td {
    background: #dbeafe;
    font-weight: 700;
  }

  table.report-table tr.total-row-grand td {
    background: #dbeafe;
    font-weight: 700;
    border-top: 2px solid #93c5fd;
  }

  /* applyXlsxFooterRow */
  table.report-table tr.footer-row td {
    background: #f9fafb;
    font-weight: 700;
  }

  /* applyXlsxAverageRow */
  table.report-table tr.average-row td {
    background: #ffedd5;
    font-weight: 700;
  }

  table.report-table tr.average-row td:not(.amount) {
    text-align: center;
  }

  table.report-table tr.status-overdue td {
    color: #ff0000;
    font-weight: 700;
    text-align: center;
    background: transparent;
    border: none;
    padding: 6px 8px;
  }

  table.report-table tr.status-paid td {
    color: #008000;
    font-weight: 700;
    text-align: center;
    background: transparent;
    border: none;
    padding: 6px 8px;
  }

  table.report-table tr.footer-overdue td {
    background: #f9fafb;
    color: #ff0000;
    font-weight: 700;
  }

  table.report-table tr.footer-paid td {
    background: #f9fafb;
    color: #008000;
    font-weight: 700;
  }

  table.report-table tr.spacer td {
    border: none;
    height: 10px;
    padding: 0;
    background: transparent;
  }

  table.report-table td.amount-danger,
  table.report-table th.amount-danger {
    color: #dc2626;
  }

  .summary-heading {
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    margin: 16px 0 8px;
  }

  .summary-title-text {
    font-weight: 700;
    margin: 10px 0 4px;
  }

  .summary-title-text.is-success { color: #008000; }
  .summary-title-text.is-danger { color: #ff0000; }

  @media print {
    html, body {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }

    body { margin: 0; }

    table.report-table { page-break-inside: auto; }
    table.report-table tr { page-break-inside: avoid; page-break-after: auto; }

    table.report-table tr.section-banner td,
    table.report-table tr.section-banner-muted td,
    table.report-table thead th,
    table.report-table tr.column-header th,
    table.report-table tr.column-header td,
    table.report-table tr.column-header-muted th,
    table.report-table tr.column-header-muted td,
    table.report-table tr.subtotal-row td,
    table.report-table tr.subtotal-row-soft td,
    table.report-table tr.subtotal-row-group td,
    table.report-table tr.total-row td,
    table.report-table tr.total-row-grand td,
    table.report-table tr.footer-row td,
    table.report-table tr.footer-overdue td,
    table.report-table tr.footer-paid td,
    table.report-table tr.average-row td {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
  }
`

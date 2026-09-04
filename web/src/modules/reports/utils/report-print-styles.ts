/** Shared print styles mirroring XLSX report formatting (PhpSpreadsheet helpers). */
export const REPORT_PRINT_STYLES = `
  @page { margin: 12mm; size: auto; }

  * { box-sizing: border-box; }

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
    font-size: 12px;
    color: #4b5563;
    margin: 0 0 2px;
    font-weight: 400;
  }

  .report-meta:last-of-type,
  .report-subtitle:last-of-type {
    margin-bottom: 16px;
  }

  table.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin: 0 0 18px;
    table-layout: auto;
  }

  table.report-table th,
  table.report-table td {
    border: 1px solid #d1d5db;
    padding: 6px 8px;
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

  table.report-table th.amount,
  table.report-table td.amount,
  table.report-table .amount {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }

  table.report-table tr.section-banner td {
    background: #dbeafe;
    font-weight: 700;
    text-transform: uppercase;
    border: 1px solid #d1d5db;
    padding: 8px;
  }

  table.report-table tr.section-banner-muted td {
    background: #f3f4f6;
    font-weight: 700;
    text-transform: uppercase;
    border: 1px solid #d1d5db;
    padding: 8px;
  }

  table.report-table tr.subtotal-row td {
    background: #dbeafe;
    font-weight: 700;
  }

  table.report-table tr.subtotal-row-soft td {
    background: #eff6ff;
    font-weight: 600;
  }

  table.report-table tr.total-row td {
    background: #dbeafe;
    font-weight: 700;
    border-top: 2px solid #93c5fd;
  }

  table.report-table tr.total-row-strong td {
    background: #93c5fd;
    font-weight: 700;
    border-top: 2px solid #60a5fa;
    color: #111827;
  }

  table.report-table tr.footer-row td {
    background: #f9fafb;
    font-weight: 700;
  }

  table.report-table tr.average-row td {
    background: #ffedd5;
    font-weight: 700;
  }

  table.report-table tr.average-row td:first-child {
    text-align: center;
  }

  table.report-table tr.status-overdue td {
    color: #dc2626;
    font-weight: 700;
    text-align: center;
    text-transform: uppercase;
    background: #fff;
  }

  table.report-table tr.status-paid td {
    color: #15803d;
    font-weight: 700;
    text-align: center;
    text-transform: uppercase;
    background: #fff;
  }

  table.report-table tr.total-overdue td {
    background: #fef2f2;
    color: #dc2626;
    font-weight: 700;
  }

  table.report-table tr.total-paid td {
    background: #f0fdf4;
    color: #15803d;
    font-weight: 700;
  }

  table.report-table tr.spacer td {
    border: none;
    height: 10px;
    padding: 0;
    background: transparent;
  }

  .report-summary {
    margin-top: 8px;
    border-top: 2px solid #93c5fd;
    padding-top: 12px;
    font-size: 12px;
  }

  .report-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 0;
  }

  .report-summary-row strong {
    font-weight: 700;
    white-space: nowrap;
  }

  .summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 8px;
  }

  .summary-box {
    border: 1px solid #d1d5db;
    overflow: hidden;
  }

  .summary-box .summary-title {
    background: #dbeafe;
    font-weight: 700;
    text-transform: uppercase;
    padding: 8px;
    border-bottom: 1px solid #d1d5db;
  }

  .summary-box .summary-title.is-success { color: #15803d; background: #f0fdf4; }
  .summary-box .summary-title.is-danger { color: #dc2626; background: #fef2f2; }

  .summary-heading {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    margin: 20px 0 12px;
  }

  @media print {
    body { margin: 0; }
    table.report-table { page-break-inside: auto; }
    table.report-table tr { page-break-inside: avoid; page-break-after: auto; }
  }
`

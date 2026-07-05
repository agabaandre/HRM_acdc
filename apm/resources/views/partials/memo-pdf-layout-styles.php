    /* Tighter layout + flow control (aligned with weekly-briefing compiled PDF) */
    body {
        margin: 16px 18px !important;
        line-height: 1.45 !important;
    }
    .section-label {
        margin-top: 4px;
        margin-bottom: 4px;
        page-break-after: avoid;
    }
    .memo-field-table {
        width: 100%;
        margin: 4px 0 10px 0;
        border-collapse: collapse;
        page-break-inside: auto;
    }
    .memo-field-row {
        page-break-inside: auto;
    }
    .memo-field-label {
        width: 12%;
        vertical-align: top;
        text-align: left;
        page-break-after: avoid;
    }
    .memo-field-body {
        width: 88%;
        vertical-align: top;
        text-align: justify;
        page-break-inside: auto;
    }
    .memo-field-body .rich-text-content,
    .memo-field-body .html-content {
        margin: 0;
        page-break-inside: auto;
    }
    .memo-field-body .rich-text-content p {
        margin: 0 0 6px 0;
        page-break-inside: auto;
    }
    .memo-major-section {
        margin-top: 12px;
        page-break-before: auto;
    }
    .memo-major-section > .section-label {
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    .page-break-force {
        page-break-before: always;
    }

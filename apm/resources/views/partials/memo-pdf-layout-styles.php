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

    /* Signature block: Signed By → image → date → verify hash */
    .signature-cell {
        vertical-align: top !important;
        text-align: left !important;
    }
    .signature-block {
        text-align: left;
        line-height: 1.35;
        max-width: 100%;
        page-break-inside: avoid;
    }
    .signature-label {
        color: #666;
        font-size: 9px;
        font-style: normal;
        font-weight: normal;
        margin: 0 0 4px 0;
        padding: 0;
        display: block;
    }
    .signature-image {
        width: 130px;
        height: 45px;
        object-fit: contain;
        object-position: left center;
        filter: contrast(1.2);
        display: block;
        margin: 0 0 6px 0;
        padding: 0;
    }
    .signature-fallback {
        color: #666;
        font-size: 9px;
        font-style: normal;
        margin: 0 0 6px 0;
        display: block;
    }
    .signature-date {
        color: #666;
        font-size: 8px;
        margin: 0 0 2px 0;
        padding: 0;
        line-height: 1.35;
        display: block;
    }
    .signature-date--pending {
        color: #999;
        font-style: italic;
    }
    .signature-hash {
        color: #999;
        font-size: 8px;
        margin: 0;
        padding: 0;
        line-height: 1.35;
        display: block;
        word-break: break-all;
    }
    .sig-table td {
        height: auto;
        min-height: 90px;
        vertical-align: top;
    }

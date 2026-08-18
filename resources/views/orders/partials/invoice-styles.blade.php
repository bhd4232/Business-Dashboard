{{--
    Shared invoice CSS — used by both the single-order print view
    (orders.print) and the bulk print view (orders.print-bulk), so the two
    never drift out of visual sync. Kept as its own partial rather than a
    separate .css asset because these print views are opened standalone
    (new tab, no app layout/build pipeline) and need to be fully
    self-contained.
--}}
<style>
    /*
     * margin: 0 here on purpose — the printable A4 margin is baked into
     * .invoice's own padding (--page-margin-x/--page-margin-y) below
     * instead. Print destinations (a physical printer, "Microsoft Print
     * to PDF", Chrome's own "Save as PDF") don't all honor a CSS @page
     * margin the same way — some ignore it, some add their own default
     * margin on top of it — and either way the invoice was rendering
     * wider than the printable area and getting clipped on the right
     * edge. With zero @page margin there's nothing left for a print
     * destination to double up or disagree about: the .invoice box is
     * exactly one A4 sheet, edge to edge, with its whitespace built in.
     */
    @page {
        margin: 0;
        size: A4;
    }

    :root {
        --page-width: 210mm;
        --page-height: 297mm;
        --page-margin-x: 12mm;
        --page-margin-y: 10mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        color: #111827;
        font-family: 'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 10px;
        margin: 0;
        padding: 24px;
        background: #f3f4f6;
    }

    .invoice {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 4px rgba(17, 24, 39, 0.08);
        display: flex;
        flex-direction: column;
        margin: 0 auto;
        min-height: var(--page-height);
        padding: var(--page-margin-y) var(--page-margin-x);
        width: var(--page-width);
    }

    /*
     * The contact strip, thank-you note and courier slip are grouped
     * as the invoice footer. margin-top: auto (inside the flex column
     * above) pins this group to the bottom of the page for the common
     * single-page invoice, instead of floating right under the totals.
     */
    .invoice-footer {
        margin-top: auto;
    }

    .inv-header {
        display: grid;
        grid-template-columns: 170px 1fr 170px;
        align-items: center;
        gap: 12px;
        page-break-inside: avoid;
    }

    .inv-header .logo {
        align-items: center;
        display: flex;
        height: 17.2mm;
        justify-content: flex-start;
    }

    .inv-header .logo img {
        display: block;
        max-height: 17.2mm;
        max-width: 31.8mm;
        object-fit: contain;
        object-position: left center;
    }

    .inv-header .title {
        text-align: center;
    }

    .inv-header .title h1 {
        font-size: 28px;
        font-weight: 800;
        margin: 0;
    }

    .inv-header .title .hotline {
        color: #374151;
        font-size: 16px;
        margin-top: 2px;
    }

    .inv-meta {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 24px;
        margin-top: 28px;
        page-break-inside: avoid;
    }

    .bill-to p {
        margin: 2px 0;
    }

    .bill-to .label {
        font-size: 12px;
        font-weight: 700;
    }

    .bill-to .name {
        font-weight: 700;
        font-size: 14px;
    }

    .bill-to .phone {
        font-size: 12px;
        font-weight: 700;
    }

    .bill-to p:not(.label):not(.name):not(.phone) {
        font-size: 12px;
    }

    .inv-ref {
        text-align: right;
        min-width: 260px;
    }

    .inv-ref .barcode svg {
        max-width: 250px;
        height: 44px;
    }

    .inv-ref p {
        margin: 3px 0;
        font-size: 14px;
    }

    .inv-ref strong {
        font-weight: 800;
    }

    table.items {
        border-collapse: collapse;
        margin-top: 24px;
        width: 100%;
    }

    table.items thead {
        display: table-header-group;
    }

    table.items th {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        color: #111827;
        font-size: 10px;
        font-weight: 700;
        padding: 7px 6px;
        text-align: left;
    }

    table.items td {
        border: 1px solid #e5e7eb;
        font-size: 10px;
        padding: 5px 6px;
        vertical-align: middle;
    }

    table.items tr {
        page-break-inside: avoid;
    }

    .num {
        text-align: right;
    }

    .center {
        text-align: center;
    }

    .item-image {
        width: 52px;
    }

    .item-image img {
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        display: block;
        height: 34px;
        object-fit: cover;
        width: 34px;
    }

    .item-name {
        font-weight: 700;
    }

    .item-variant {
        color: #6b7280;
        font-size: 9px;
        font-weight: 400;
    }

    .totals-wrap {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        margin-top: 0;
        page-break-inside: avoid;
    }

    .contact-block {
        flex: 1;
        padding-top: 16px;
    }

    .contact-block p {
        align-items: center;
        display: flex;
        gap: 8px;
        margin: 7px 0;
    }

    .contact-icon {
        align-items: center;
        border: 1px solid #9ca3af;
        border-radius: 50%;
        color: #374151;
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 10px;
        font-weight: 700;
        height: 18px;
        justify-content: center;
        width: 18px;
    }

    table.totals {
        border-collapse: collapse;
        margin-top: 8px;
        min-width: 380px;
    }

    table.totals td {
        font-size: 10px;
        padding: 6px 10px;
    }

    table.totals tr.row {
        background: #f3f4f6;
        border-bottom: 2px solid #ffffff;
    }

    table.totals td.t-label {
        font-weight: 700;
        text-align: right;
        width: 55%;
    }

    table.totals tr.due {
        background: #000000;
        color: #ffffff;
    }

    table.totals tr.due td {
        font-weight: 800;
    }

    .contact-strip {
        background: #f3f4f6;
        display: flex;
        justify-content: space-around;
        gap: 8px;
        margin-top: 22px;
        padding: 10px 12px;
        page-break-inside: avoid;
    }

    .contact-strip span {
        font-size: 10px;
    }

    .contact-strip strong {
        font-weight: 800;
    }

    .thank-you {
        background: #f9fafb;
        font-size: 12px;
        font-weight: 800;
        margin-top: 14px;
        padding: 22px 12px;
        page-break-inside: avoid;
        text-align: center;
    }

    .cut-line {
        border: 0;
        border-top: 2px dashed #6b7280;
        margin: 30px 0 4px;
        position: relative;
    }

    .cut-line-wrap {
        page-break-inside: avoid;
        position: relative;
    }

    .cut-line-wrap .scissors {
        background: #ffffff;
        color: #374151;
        font-size: 16px;
        position: absolute;
        right: -6px;
        top: -14px;
    }

    .slip {
        margin-top: 14px;
        page-break-inside: avoid;
    }

    .slip-header {
        display: grid;
        grid-template-columns: 120px 1fr 120px;
        align-items: center;
    }

    .slip-header .logo {
        align-items: center;
        display: flex;
        height: 9.2mm;
        justify-content: flex-start;
    }

    .slip-header .logo img {
        display: block;
        max-height: 9.2mm;
        max-width: 16.9mm;
        object-fit: contain;
        object-position: left center;
    }

    .slip-header .title {
        text-align: center;
    }

    .slip-header .title h2 {
        font-size: 19px;
        font-weight: 800;
        margin: 0;
    }

    .slip-header .title .hotline {
        color: #374151;
        font-size: 12px;
    }

    .slip-body {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 24px;
        margin-top: 14px;
    }

    .slip-body .bill-to {
        font-size: 10px;
        max-width: 320px;
    }

    .slip-body .bill-to .label,
    .slip-body .bill-to .name {
        font-size: 12px;
    }

    .slip-body .bill-to .phone,
    .slip-body .bill-to p:not(.label):not(.name):not(.phone) {
        font-size: 10px;
    }

    .slip-ref {
        text-align: right;
    }

    .slip-ref .barcode svg {
        max-width: 220px;
        height: 36px;
    }

    .slip-ref p {
        font-size: 10px;
        margin: 2px 0;
    }

    .slip-due {
        background: #000000;
        color: #ffffff;
        display: inline-block;
        font-size: 10px;
        font-weight: 800;
        margin-top: 8px;
        padding: 7px 14px;
    }

    .print-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 14px;
    }

    .print-button {
        background: #111827;
        border: 0;
        border-radius: 6px;
        color: #ffffff;
        cursor: pointer;
        min-width: 92px;
        padding: 10px 14px;
    }

    /*
     * Bulk print only: each order's invoice sits in its own .invoice-page
     * wrapper so it forces a page break before the next one. The
     * single-order print view never has more than one .invoice-page, so
     * this rule is a no-op there.
     */
    .invoice-page + .invoice-page {
        page-break-before: always;
    }

    @media print {
        * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            background: #ffffff;
            padding: 0;
        }

        /*
         * width/padding are NOT overridden here — .invoice keeps the same
         * 210mm box with its built-in 10mm/12mm padding it uses on
         * screen, so what's on screen is exactly what prints (see the
         * @page rule above for why). min-height goes back to the full
         * page height since there's no external @page margin to subtract
         * anymore.
         */
        .invoice {
            border: 0;
            box-shadow: none;
            margin: 0;
            min-height: var(--page-height);
        }

        .print-actions {
            display: none;
        }
    }

    /* Keep the stacked layout screen-only. An A4 printable area is about
     * 703 CSS pixels wide, so an unqualified max-width query also matched
     * during printing and collapsed the desktop invoice columns. */
    @media screen and (max-width: 720px) {
        body {
            padding: 10px;
        }

        .invoice {
            min-height: auto;
            padding: 16px;
            width: 100%;
        }

        .inv-header,
        .slip-header {
            grid-template-columns: 1fr;
            justify-items: center;
            text-align: center;
        }

        .inv-header .logo,
        .slip-header .logo {
            justify-content: center;
        }

        .inv-meta,
        .totals-wrap,
        .slip-body,
        .contact-strip {
            display: block;
        }

        .inv-ref,
        .slip-ref {
            margin-top: 14px;
            text-align: left;
        }

        table.totals {
            min-width: 0;
            width: 100%;
        }
    }
</style>

{{--
    Shared print CSS for the investor payout report and the project register.
    Self-contained (these views open standalone, no app layout / build
    pipeline). Bengali-first font stack so investor names, addresses and
    labels render correctly when printed from the browser or the Android
    app's WebView.
--}}
<style>
    @page { margin: 12mm; size: A4; }

    * { box-sizing: border-box; }

    body {
        color: #111827;
        font-family: 'Noto Sans Bengali', 'Nikosh', 'SolaimanLipi', 'Kalpurush', 'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial, sans-serif;
        font-size: 12px;
        line-height: 1.5;
        margin: 0;
        padding: 24px;
        background: #f3f4f6;
    }

    .sheet {
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 4px rgba(17, 24, 39, 0.08);
        margin: 0 auto;
        max-width: 190mm;
        padding: 22px 26px 30px;
    }

    .head {
        align-items: flex-start;
        border-bottom: 2px solid #f59e0b;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding-bottom: 12px;
    }

    .head .brand { font-size: 16px; font-weight: 700; }
    .head .brand .sub { color: #4b5563; font-size: 11px; font-weight: 400; }
    .head img { max-height: 44px; max-width: 150px; object-fit: contain; }

    h1.title { font-size: 17px; margin: 16px 0 2px; }
    .deal { color: #374151; margin-bottom: 14px; }

    table { border-collapse: collapse; width: 100%; margin: 8px 0 14px; }
    th, td { border: 1px solid #d1d5db; padding: 6px 9px; text-align: left; }
    th { background: #111827; color: #fff; font-size: 10px; text-transform: uppercase; }
    td.num, th.num { text-align: right; }
    tr.total td { background: #f3f4f6; font-weight: 700; }

    .kv { display: flex; justify-content: space-between; padding: 5px 0; }
    .kv + .kv { border-top: 1px solid #f3f4f6; }
    .kv .k { color: #374151; }
    .kv .v { font-weight: 700; }

    .callout {
        background: #fffbeb;
        border: 1px solid #fcd34d;
        border-radius: 6px;
        margin: 14px 0;
        padding: 12px 14px;
    }
    .callout .big { font-size: 15px; font-weight: 700; }

    .paid { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
    .pending { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
    .status-line { border-radius: 6px; margin-top: 14px; padding: 10px 14px; }

    .foot { color: #6b7280; font-size: 10px; margin-top: 20px; text-align: right; }

    .print-actions { display: flex; justify-content: flex-end; margin-bottom: 14px; }
    .print-button {
        background: #111827; border: 0; border-radius: 6px; color: #fff;
        cursor: pointer; min-width: 92px; padding: 10px 16px;
    }

    @media print {
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { background: #fff; padding: 0; }
        .sheet { border: 0; box-shadow: none; max-width: none; padding: 0; }
        .print-actions { display: none; }
    }
</style>

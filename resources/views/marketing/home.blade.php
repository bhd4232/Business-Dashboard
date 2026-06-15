<x-layouts.marketing title="ZamZam ERP">
    <main class="hero">
        <section>
            <div class="eyebrow">Inventory, purchase costing, and sales ERP</div>
            <h1>ZamZam ERP</h1>
            <p>Run products, stock, purchases, China-to-BD costing, invoices, payments, dues, expenses, reports, backups, roles, and audit logs from one Laravel and Filament admin panel.</p>
            <div class="actions">
                <a class="button" href="{{ url('/install') }}">Start Installer</a>
                <a class="button secondary" href="{{ route('marketing.docs') }}">Read Docs</a>
            </div>
        </section>
        <section class="preview" aria-label="Dashboard preview">
            <div class="preview-bar"></div>
            <div class="preview-grid">
                <div class="preview-card">Today Sales<strong>BDT 84,500</strong></div>
                <div class="preview-card">Customer Due<strong>BDT 32,100</strong></div>
                <div class="preview-card">Low Stock<strong>12</strong></div>
                <div class="preview-card">Gross Profit<strong>BDT 18,900</strong></div>
            </div>
        </section>
    </main>
    <section class="section">
        <div class="cards">
            <div class="card"><strong>Sellable Setup</strong><p>Installer, onboarding checklist, company branding, currency, timezone, and license activation.</p></div>
            <div class="card"><strong>Business Modules</strong><p>Inventory, purchases, sales, accounts, dues, expenses, backups, reports, and audit trails.</p></div>
            <div class="card"><strong>Client Ready</strong><p>Demo mode, public documentation pages, pricing page, and white-label admin branding.</p></div>
        </div>
    </section>
</x-layouts.marketing>

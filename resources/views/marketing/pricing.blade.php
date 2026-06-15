<x-layouts.marketing title="Pricing - ZamZam ERP">
    <main class="section">
        <h1>Pricing</h1>
        <p>Use this page as the client-facing pricing template for installable deployments.</p>
        <div class="cards">
            <div class="card"><strong>Starter Install</strong><p>Single business, core ERP modules, local backup, basic setup support.</p><a class="button" href="{{ url('/install') }}">Install</a></div>
            <div class="card"><strong>Business Install</strong><p>Branding, reports, Google Drive backup, data import, and staff training.</p><a class="button" href="{{ route('marketing.docs') }}">Details</a></div>
            <div class="card"><strong>Custom</strong><p>Custom workflows, integrations, hosting, and long-term maintenance.</p><a class="button" href="mailto:support@example.com">Contact</a></div>
        </div>
    </main>
</x-layouts.marketing>

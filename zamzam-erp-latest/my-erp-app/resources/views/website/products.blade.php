@extends('website.layout', [
    'title' => 'Products - '.($settings?->site_name ?? 'ZamZam International'),
    'description' => 'ZamZam International product catalog will be managed from the ERP dashboard.',
    'canonical' => route('website.products.index'),
])

@section('content')
    <section class="page-content">
        <h1>Products</h1>
        <div class="page-body">
            <p>The public product catalog will connect with the ERP product and inventory module in Phase 3.</p>
            <p>For now, product information can be introduced through Website Sections and Website Pages from the dashboard.</p>
        </div>
    </section>
@endsection

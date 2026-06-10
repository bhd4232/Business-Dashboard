@php
    use App\Http\Controllers\WebsiteController;

    $hero = $banners->first();
    $heroImage = WebsiteController::mediaUrl($hero?->image);
@endphp

@extends('website.layout', [
    'title' => $settings?->seo_title ?: ($settings?->site_name ?? 'ZamZam International'),
    'description' => $settings?->seo_description ?: $settings?->tagline,
    'canonical' => route('website.home'),
    'ogImage' => WebsiteController::absoluteMediaUrl($settings?->og_image ?: $hero?->image ?: $settings?->logo),
])

@section('content')
    <div class="hero">
        <section>
            <span class="hero-kicker">{{ $hero?->subtitle ?: 'China to Bangladesh Wholesale' }}</span>
            <h1>{{ $hero?->title ?: 'ZamZam International' }}</h1>
            <p>
                {{ $hero?->description ?: 'Manage import purchasing, landed costing, inventory, wholesale sales, customer dues, supplier payments, accounts, reports, and website content from one connected ERP dashboard.' }}
            </p>
            <div class="hero-actions">
                <a class="button button-primary" href="{{ $hero?->primary_button_url ?: '/admin' }}">
                    {{ $hero?->primary_button_label ?: 'Open Dashboard' }}
                </a>
                <a class="button button-secondary" href="{{ $hero?->secondary_button_url ?: '#contact' }}">
                    {{ $hero?->secondary_button_label ?: 'Contact Us' }}
                </a>
            </div>
        </section>

        <aside class="hero-media" aria-label="ZamZam business overview">
            @if($heroImage)
                <img src="{{ $heroImage }}" alt="{{ $hero?->title ?: 'ZamZam International' }}">
            @else
                <div class="hero-media-fallback">
                    <strong>One dashboard for trading, stock, sales, accounts, and website operations.</strong>
                    <span>Upload a banner from Tyro Dashboard > Resources > Website Banners to replace this panel.</span>
                </div>
            @endif
        </aside>
    </div>

    <section class="metric-row" aria-label="Business coverage">
        <div class="metric"><strong>CN-BD</strong><span>Import purchase costing</span></div>
        <div class="metric"><strong>Stock</strong><span>Movement-based inventory</span></div>
        <div class="metric"><strong>Due</strong><span>Customer and supplier balance</span></div>
        <div class="metric"><strong>ERP</strong><span>Reports, accounts, and audit</span></div>
    </section>

    <section id="about" class="section">
        <h2>Managed From The Dashboard</h2>
        <div class="section-grid">
            @forelse($sections as $section)
                @php($sectionImage = WebsiteController::mediaUrl($section->image))
                <article class="content-card {{ $section->layout === 'wide' ? 'content-card-wide' : '' }} section-type-{{ $section->section_type }}">
                    @if($sectionImage)
                        <img class="content-card-image" src="{{ $sectionImage }}" alt="{{ $section->title }}">
                    @endif
                    <span class="card-kicker">{{ $section->subtitle ?: str($section->section_type)->replace('_', ' ')->title() }}</span>
                    <h3>{{ $section->title }}</h3>
                    @if($section->body)
                        <div class="card-body">{!! $section->body !!}</div>
                    @endif
                    @if($section->button_label && $section->button_url)
                        <a class="text-link" href="{{ $section->button_url }}">{{ $section->button_label }}</a>
                    @endif
                </article>
            @empty
                @forelse($pages->take(3) as $page)
                    <a class="content-card" href="{{ route('website.pages.show', $page->slug) }}">
                        <h3>{{ $page->title }}</h3>
                        <p>{{ $page->excerpt ?: str($page->content)->stripTags()->limit(130) }}</p>
                    </a>
                @empty
                    <div class="content-card">
                        <h3>Purchase Costing</h3>
                        <p>Track purchase item cost, China-to-Bangladesh expenses, supplier due, and landed cost planning.</p>
                    </div>
                    <div class="content-card">
                        <h3>Inventory Control</h3>
                        <p>Keep stock traceable through opening, purchase, sale, return, and adjustment movements.</p>
                    </div>
                    <div class="content-card">
                        <h3>Sales and Accounts</h3>
                        <p>Connect invoices, customer payments, supplier payments, expenses, ledgers, and reports.</p>
                    </div>
                @endforelse
            @endforelse
        </div>
    </section>

    <section id="contact" class="section">
        <h2>Contact</h2>
        <div class="contact-layout">
            <div class="contact-details">
                <div class="content-card">
                    <h3>Phone</h3>
                    <p>{{ $settings?->phone ?: 'Add phone from Website Settings.' }}</p>
                </div>
                <div class="content-card">
                    <h3>Email</h3>
                    <p>{{ $settings?->email ?: 'Add email from Website Settings.' }}</p>
                </div>
                <div class="content-card">
                    <h3>Address</h3>
                    <p>{{ $settings?->address ?: 'Add address from Website Settings.' }}</p>
                </div>
            </div>

            <form class="contact-form" method="POST" action="{{ route('website.contact.store') }}">
                @csrf

                @if(session('contact_status'))
                    <div class="form-status">{{ session('contact_status') }}</div>
                @endif

                <label>
                    <span>Name</span>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                    @error('name') <small>{{ $message }}</small> @enderror
                </label>

                <div class="form-row">
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                        @error('email') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone" value="{{ old('phone') }}">
                        @error('phone') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <label>
                    <span>Subject</span>
                    <input type="text" name="subject" value="{{ old('subject') }}">
                    @error('subject') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Message</span>
                    <textarea name="message" rows="5" required>{{ old('message') }}</textarea>
                    @error('message') <small>{{ $message }}</small> @enderror
                </label>

                <button class="button button-primary" type="submit">Send Message</button>
            </form>
        </div>
    </section>
@endsection

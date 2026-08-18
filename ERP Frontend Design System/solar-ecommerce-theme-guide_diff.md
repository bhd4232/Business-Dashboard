--- solar-ecommerce-theme-guide.md (原始)


+++ solar-ecommerce-theme-guide.md (修改后)
# Laravel Livewire + Filament: Solar Energy E-commerce Storefront Theme
## Complete Step-by-Step Guide for Building a Modern 3D Animated Website

---

## 📋 Table of Contents

1. [Project Overview](#project-overview)
2. [Prerequisites & Setup](#prerequisites--setup)
3. [Installation & Configuration](#installation--configuration)
4. [Database Schema for Solar Products](#database-schema-for-solar-products)
5. [Frontend Stack Selection](#frontend-stack-selection)
6. [3D Animation Integration](#3d-animation-integration)
7. [Theme Structure & Components](#theme-structure--components)
8. [Product Catalog System](#product-catalog-system)
9. [Shopping Cart & Checkout](#shopping-cart--checkout)
10. [User Authentication & Dashboard](#user-authentication--dashboard)
11. [Admin Panel Integration (Filament)](#admin-panel-integration-filament)
12. [Performance Optimization](#performance-optimization)
13. [Deployment Checklist](#deployment-checklist)

---

## 1. Project Overview

### Objective
Build a professional, modern 3D animated e-commerce storefront for solar energy products (solar panels, batteries, inverters, accessories) using:
- **Backend**: Laravel 10/11
- **Frontend**: Livewire 3 + Alpine.js + Tailwind CSS
- **Admin Panel**: Filament ERP (already configured)
- **3D Elements**: Three.js / Spline / GSAP animations
- **Database**: MySQL/PostgreSQL

### Key Features
- ✅ 3D product visualization (solar panels, batteries)
- ✅ Interactive product configurator
- ✅ Real-time cart management
- ✅ Order tracking system
- ✅ Customer dashboard
- ✅ Payment gateway integration (Stripe, bKash, Nagad)
- ✅ SEO optimized
- ✅ Mobile responsive
- ✅ Bangla + English language support

---

## 2. Prerequisites & Setup

### System Requirements
```bash
- PHP 8.2+
- Composer 2.5+
- Node.js 18+ & NPM
- MySQL 8.0+ or PostgreSQL 14+
- Redis (for caching & sessions)
- Git
```

### Verify Existing Filament Setup
```bash
cd /workspace
php artisan --version
composer show filament/filament
```

---

## 3. Installation & Configuration

### Step 3.1: Install Livewire
```bash
composer require livewire/livewire
php artisan livewire:install --no-interaction
```

### Step 3.2: Install Tailwind CSS & Alpine.js
```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
npm install alpinejs
```

### Step 3.3: Configure `tailwind.config.js`
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#FDB813', // Solar yellow
        secondary: '#1E3A5F', // Deep blue
        accent: '#4CAF50', // Green energy
        dark: '#0F172A',
      },
      animation: {
        'float': 'float 6s ease-in-out infinite',
        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'rotate-slow': 'spin 20s linear infinite',
      },
      keyframes: {
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-20px)' },
        }
      }
    },
  },
  plugins: [],
}
```

### Step 3.4: Update `resources/css/app.css`
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

[x-cloak] { display: none !important; }

/* Custom 3D styles */
.perspective-1000 { perspective: 1000px; }
.transform-style-3d { transform-style: preserve-3d; }
.rotate-y-12 { transform: rotateY(12deg); }
```

### Step 3.5: Install Three.js for 3D Animations
```bash
npm install three @types/three gsap @gsap/triggers
```

---

## 4. Database Schema for Solar Products

### Step 4.1: Create Migrations
```bash
php artisan make:migration create_products_table
php artisan make:migration create_categories_table
php artisan make:migration create_product_attributes_table
php artisan make:migration create_orders_table
php artisan make:migration create_order_items_table
php artisan make:migration create_carts_table
php artisan make:migration create_wishlists_table
php artisan make:migration create_reviews_table
```

### Step 4.2: Products Migration
```php
// database/migrations/xxxx_create_products_table.php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description');
    $table->decimal('price', 10, 2);
    $table->decimal('compare_price', 10, 2)->nullable();
    $table->integer('stock')->default(0);
    $table->string('sku')->unique();
    $table->enum('type', ['solar_panel', 'battery', 'inverter', 'accessory']);
    $table->json('specifications')->nullable(); // Wattage, Voltage, Capacity etc.
    $table->string('model_number')->nullable();
    $table->integer('warranty_months')->nullable();
    $table->boolean('is_featured')->default(false);
    $table->boolean('is_available')->default(true);
    $table->string('main_image');
    $table->json('images')->nullable();
    $table->string('video_url')->nullable();
    $table->string('model_3d_url')->nullable(); // For 3D viewer
    $table->foreignId('category_id')->constrained()->onDelete('cascade');
    $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['type', 'is_featured', 'is_available']);
});
```

### Step 4.3: Categories Migration
```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('icon')->nullable();
    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Step 4.4: Seed Initial Data
```bash
php artisan make:seeder CategorySeeder
php artisan make:seeder ProductSeeder
```

---

## 5. Frontend Stack Selection

### Recommended Stack
| Component | Technology | Purpose |
|-----------|-----------|---------|
| UI Framework | Livewire 3 | Dynamic components |
| Styling | Tailwind CSS | Rapid UI development |
| Interactivity | Alpine.js | Light-weight JS interactions |
| 3D Graphics | Three.js | Product visualization |
| Animations | GSAP | Smooth scroll & reveal animations |
| Icons | Heroicons / FontAwesome | UI icons |
| Fonts | Google Fonts (Hind Siliguri for Bangla) | Typography |

### Install Additional Packages
```bash
npm install @alpinejs/focus @alpinejs/persist
composer require intervention/image
composer require spatie/laravel-sitemap
```

---

## 6. 3D Animation Integration

### Step 6.1: Create 3D Product Viewer Component
```bash
php artisan make:livewire Product3DViewer
```

**`resources/views/livewire/product-3d-viewer.blade.php`**
```blade
<div
    x-data="threeDViewer('{{ $modelUrl }}')"
    x-init="initViewer()"
    class="relative w-full h-[500px] bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl overflow-hidden"
>
    <canvas x-ref="canvas" class="w-full h-full"></canvas>

    <!-- Controls -->
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
        <button @click="rotateLeft()" class="p-2 bg-white/20 backdrop-blur rounded-full hover:bg-white/30">
            ←
        </button>
        <button @click="resetView()" class="p-2 bg-white/20 backdrop-blur rounded-full hover:bg-white/30">
            ⟲
        </button>
        <button @click="rotateRight()" class="p-2 bg-white/20 backdrop-blur rounded-full hover:bg-white/30">
            →
        </button>
    </div>

    <!-- Loading Indicator -->
    <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-black/50">
        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
    </div>
</div>

@push('scripts')
<script>
function threeDViewer(modelUrl) {
    return {
        loading: true,
        scene: null,
        camera: null,
        renderer: null,
        model: null,

        initViewer() {
            const canvas = this.$refs.canvas;

            // Scene setup
            this.scene = new THREE.Scene();
            this.scene.background = new THREE.Color(0x1a1a2e);

            // Camera
            this.camera = new THREE.PerspectiveCamera(75, canvas.clientWidth / canvas.clientHeight, 0.1, 1000);
            this.camera.position.z = 5;

            // Renderer
            this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
            this.renderer.setSize(canvas.clientWidth, canvas.clientHeight);
            this.renderer.setPixelRatio(window.devicePixelRatio);

            // Lighting
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
            this.scene.add(ambientLight);

            const pointLight = new THREE.PointLight(0xffffff, 1);
            pointLight.position.set(5, 5, 5);
            this.scene.add(pointLight);

            // Load model (GLTF/GLB format)
            const loader = new THREE.GLTFLoader();
            loader.load(modelUrl, (gltf) => {
                this.model = gltf.scene;
                this.scene.add(this.model);
                this.loading = false;
                this.animate();
            });

            // Handle resize
            window.addEventListener('resize', () => {
                this.camera.aspect = canvas.clientWidth / canvas.clientHeight;
                this.camera.updateProjectionMatrix();
                this.renderer.setSize(canvas.clientWidth, canvas.clientHeight);
            });
        },

        animate() {
            requestAnimationFrame(() => this.animate());
            if (this.model) {
                this.model.rotation.y += 0.005;
            }
            this.renderer.render(this.scene, this.camera);
        },

        rotateLeft() {
            if (this.model) this.model.rotation.y -= 0.5;
        },

        rotateRight() {
            if (this.model) this.model.rotation.y += 0.5;
        },

        resetView() {
            if (this.model) {
                this.model.rotation.set(0, 0, 0);
                this.camera.position.set(0, 0, 5);
            }
        }
    }
}
</script>
@endpush
```

### Step 6.2: Add GSAP Scroll Animations
**`resources/js/app.js`**
```javascript
import Alpine from 'alpinejs';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

window.Alpine = Alpine;
window.gsap = gsap;
window.ScrollTrigger = ScrollTrigger;

Alpine.start();

// Initialize scroll animations
document.addEventListener('DOMContentLoaded', () => {
    gsap.utils.toArray('.fade-in-up').forEach(element => {
        gsap.from(element, {
            scrollTrigger: {
                trigger: element,
                start: 'top 80%',
                toggleActions: 'play none none none'
            },
            opacity: 0,
            y: 50,
            duration: 0.8
        });
    });
});
```

---

## 7. Theme Structure & Components

### Step 7.1: Layout Structure
```
resources/views/
├── layouts/
│   ├── app.blade.php          # Main layout
│   ├── guest.blade.php        # Guest layout
│   └── components/
│       ├── header.blade.php
│       ├── footer.blade.php
│       ├── navigation.blade.php
│       └── cart-sidebar.blade.php
├── livewire/
│   ├── home/
│   │   ├── hero-section.blade.php
│   │   ├── featured-products.blade.php
│   │   ├── categories-grid.blade.php
│   │   └── testimonials.blade.php
│   ├── products/
│   │   ├── listing.blade.php
│   │   ├── detail.blade.php
│   │   └── search-results.blade.php
│   ├── cart/
│   │   ├── index.blade.php
│   │   └── mini-cart.blade.php
│   ├── checkout/
│   │   ├── index.blade.php
│   │   └── success.blade.php
│   └── user/
│       ├── dashboard.blade.php
│       └── orders.blade.php
└── pages/
    ├── home.blade.php
    ├── about.blade.php
    ├── contact.blade.php
    └── faq.blade.php
```

### Step 7.2: Main Layout (`layouts/app.blade.php`)
```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SolarShop') }} - @yield('title')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles -->
    @vite(['resources/css/app.css'])
    @livewireStyles

    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">

    <!-- Header -->
    @include('layouts.components.header')

    <!-- Main Content -->
    <main class="min-h-screen">
        {{ $slot }}
    </main>

    <!-- Footer -->
    @include('layouts.components.footer')

    <!-- Cart Sidebar -->
    @livewire('cart.mini-cart')

    <!-- Scripts -->
    @vite(['resources/js/app.js'])
    @livewireScripts
    @stack('scripts')
</body>
</html>
```

### Step 7.3: Header Component
```bash
php artisan make:livewire Layout.Header
```

**`resources/views/livewire/layout/header.blade.php`**
```blade
<header
    x-data="{ mobileMenuOpen: false, scrolled: false }"
    @scroll.window="scrolled = (window.pageYOffset > 20)"
    :class="{ 'bg-white shadow-lg': scrolled, 'bg-transparent': !scrolled }"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
>
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">

            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="/" class="flex items-center gap-2">
                    <img src="{{ asset('images/logo.svg') }}" alt="SolarShop" class="h-10">
                    <span class="text-2xl font-bold text-primary">Solar<span class="text-secondary">Shop</span></span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/" class="text-gray-700 hover:text-primary transition">হোম</a>
                <a href="/products" class="text-gray-700 hover:text-primary transition">পণ্যসমূহ</a>
                <a href="/categories/solar-panels" class="text-gray-700 hover:text-primary transition">সোলার প্যানেল</a>
                <a href="/categories/batteries" class="text-gray-700 hover:text-primary transition">ব্যাটারি</a>
                <a href="/about" class="text-gray-700 hover:text-primary transition">আমাদের সম্পর্কে</a>
                <a href="/contact" class="text-gray-700 hover:text-primary transition">যোগাযোগ</a>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-4">
                <!-- Search -->
                <div class="relative hidden lg:block">
                    <input
                        type="text"
                        placeholder="অনুসন্ধান করুন..."
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <!-- Wishlist -->
                <a href="/wishlist" class="relative p-2 text-gray-700 hover:text-primary">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </a>

                <!-- Cart -->
                <button
                    @click="$dispatch('toggle-cart')"
                    class="relative p-2 text-gray-700 hover:text-primary"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    @if($cartCount > 0)
                        <span class="absolute -top-1 -right-1 bg-primary text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ $cartCount }}
                        </span>
                    @endif
                </button>

                <!-- User -->
                @auth
                    <a href="/dashboard" class="p-2 text-gray-700 hover:text-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </a>
                @else
                    <a href="/login" class="px-4 py-2 bg-primary text-white rounded-full hover:bg-yellow-600 transition">লগইন</a>
                @endauth

                <!-- Mobile Menu Button -->
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden p-2 text-gray-700"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div
            x-show="mobileMenuOpen"
            x-transition
            class="md:hidden py-4 border-t"
        >
            <div class="flex flex-col space-y-3">
                <a href="/" class="text-gray-700 hover:text-primary">হোম</a>
                <a href="/products" class="text-gray-700 hover:text-primary">পণ্যসমূহ</a>
                <a href="/categories" class="text-gray-700 hover:text-primary">ক্যাটাগরি</a>
                <a href="/about" class="text-gray-700 hover:text-primary">আমাদের সম্পর্কে</a>
                <a href="/contact" class="text-gray-700 hover:text-primary">যোগাযোগ</a>
            </div>
        </div>
    </nav>
</header>
```

---

## 8. Product Catalog System

### Step 8.1: Product Listing Page
```bash
php artisan make:livewire Products.Listing
```

**`resources/views/livewire/products/listing.blade.php`**
```blade
<div class="pt-20 pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Filters & Sort -->
        <div class="flex flex-col md:flex-row gap-4 mb-8">
            <!-- Filter Sidebar -->
            <div class="w-full md:w-64 flex-shrink-0">
                <div class="bg-white rounded-xl shadow-sm p-6 sticky top-24">
                    <h3 class="font-semibold text-lg mb-4">ফিল্টার</h3>

                    <!-- Category Filter -->
                    <div class="mb-6">
                        <h4 class="font-medium mb-2">ক্যাটাগরি</h4>
                        <div class="space-y-2">
                            @foreach($categories as $category)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedCategories"
                                        value="{{ $category->id }}"
                                        class="rounded border-gray-300 text-primary focus:ring-primary"
                                    >
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="mb-6">
                        <h4 class="font-medium mb-2">মূল্য পরিসর</h4>
                        <input
                            type="range"
                            min="0"
                            max="100000"
                            wire:model.live="priceRange"
                            class="w-full accent-primary"
                        >
                        <div class="flex justify-between text-sm text-gray-600 mt-1">
                            <span>৳0</span>
                            <span>৳{{ number_format($priceRange) }}</span>
                        </div>
                    </div>

                    <!-- Product Type -->
                    <div>
                        <h4 class="font-medium mb-2">প্রকার</h4>
                        <select wire:model.live="productType" class="w-full border border-gray-300 rounded-lg p-2">
                            <option value="">সব</option>
                            <option value="solar_panel">সোলার প্যানেল</option>
                            <option value="battery">ব্যাটারি</option>
                            <option value="inverter">ইনভার্টার</option>
                            <option value="accessory">অন্যান্য</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="flex-1">
                <!-- Sort & View Options -->
                <div class="flex justify-between items-center mb-6">
                    <p class="text-gray-600">{{ $products->total() }}টি পণ্য পাওয়া গেছে</p>
                    <select wire:model.live="sortBy" class="border border-gray-300 rounded-lg p-2">
                        <option value="latest">নতুন arriving</option>
                        <option value="price_low">মূল্য: কম থেকে বেশি</option>
                        <option value="price_high">মূল্য: বেশি থেকে কম</option>
                        <option value="popular">জনপ্রিয়</option>
                    </select>
                </div>

                <!-- Products -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($products as $product)
                        <div class="fade-in-up group bg-white rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden">
                            <!-- Image -->
                            <div class="relative aspect-square overflow-hidden bg-gray-100">
                                <img
                                    src="{{ asset('storage/' . $product->main_image) }}"
                                    alt="{{ $product->name }}"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                >

                                <!-- Badges -->
                                @if($product->is_featured)
                                    <span class="absolute top-3 left-3 bg-primary text-white px-3 py-1 rounded-full text-xs font-medium">
                                        ফিচারড
                                    </span>
                                @endif
                                @if($product->compare_price && $product->compare_price > $product->price)
                                    <span class="absolute top-3 right-3 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                                        -{{ round((($product->compare_price - $product->price) / $product->compare_price) * 100) }}%
                                    </span>
                                @endif

                                <!-- Quick Actions -->
                                <div class="absolute inset-x-0 bottom-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                    <button
                                        wire:click="$dispatch('quick-view', { productId: {{ $product->id }} })"
                                        class="flex-1 bg-white/90 backdrop-blur text-gray-900 py-2 rounded-lg font-medium hover:bg-primary hover:text-white transition"
                                    >
                                        দ্রুত দেখুন
                                    </button>
                                    <button
                                        wire:click="$dispatch('add-to-wishlist', { productId: {{ $product->id }} })"
                                        class="p-2 bg-white/90 backdrop-blur rounded-lg hover:bg-red-500 hover:text-white transition"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="p-4">
                                <p class="text-xs text-gray-500 mb-1">{{ $product->category->name }}</p>
                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">{{ $product->name }}</h3>

                                <!-- Specifications Preview -->
                                <div class="flex gap-2 mb-3 text-xs text-gray-600">
                                    @if($product->specifications['wattage'] ?? null)
                                        <span class="bg-gray-100 px-2 py-1 rounded">{{ $product->specifications['wattage'] }}W</span>
                                    @endif
                                    @if($product->specifications['voltage'] ?? null)
                                        <span class="bg-gray-100 px-2 py-1 rounded">{{ $product->specifications['voltage'] }}V</span>
                                    @endif
                                </div>

                                <!-- Price -->
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-xl font-bold text-primary">৳{{ number_format($product->price) }}</span>
                                    @if($product->compare_price)
                                        <span class="text-sm text-gray-400 line-through">৳{{ number_format($product->compare_price) }}</span>
                                    @endif
                                </div>

                                <!-- Add to Cart -->
                                <button
                                    wire:click="$dispatch('add-to-cart', { productId: {{ $product->id }}, quantity: 1 })"
                                    :disabled="{{ !$product->is_available || $product->stock <= 0 }}"
                                    class="w-full bg-secondary text-white py-2.5 rounded-lg font-medium hover:bg-blue-800 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ $product->is_available ? 'কার্টে যোগ করুন' : 'স্টক শেষ' }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-16">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="text-gray-500 text-lg">কোনো পণ্য পাওয়া যায়নি</p>
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                <div class="mt-12">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
```

### Step 8.2: Product Detail Page with 3D Viewer
```bash
php artisan make:livewire Products.Detail
```

**Key Features:**
- 3D product model viewer
- Image gallery with zoom
- Specifications table
- Customer reviews
- Related products
- Add to cart functionality

---

## 9. Shopping Cart & Checkout

### Step 9.1: Cart Component
```bash
php artisan make:livewire Cart.Index
php artisan make:livewire Cart.MiniCart
```

**Cart Logic:**
- Store cart in session/database
- Real-time price calculation
- Coupon/discount code support
- Shipping cost calculator
- Stock validation

### Step 9.2: Checkout Process
```bash
php artisan make:livewire Checkout.Index
php artisan make:livewire Checkout.Success
```

**Checkout Steps:**
1. **Shipping Address** - Form with validation
2. **Shipping Method** - Courier/Pickup options
3. **Payment Method** - Stripe, bKash, Nagad, Bank Transfer
4. **Order Review** - Summary before confirmation
5. **Success Page** - Order confirmation with tracking

### Payment Integration Example (bKash)
```php
// app/Services/Payment/BkashService.php
class BkashService {
    public function createPayment($amount, $orderId) {
        // bKash API integration
    }

    public function executePayment($paymentId) {
        // Confirm payment
    }
}
```

---

## 10. User Authentication & Dashboard

### Step 10.1: Install Jetstream or Breeze
```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
```

### Step 10.2: User Dashboard Components
```bash
php artisan make:livewire User.Dashboard
php artisan make:livewire User.Orders
php artisan make:livewire User.Profile
php artisan make:livewire User.Wishlist
```

**Dashboard Features:**
- Order history with status tracking
- Profile management
- Address book
- Wishlist
- Password change
- Notification preferences

---

## 11. Admin Panel Integration (Filament)

Since you already have Filament ERP setup, integrate these resources:

### Step 11.1: Create Filament Resources
```bash
php artisan make:filament-resource Product
php artisan make:filament-resource Category
php artisan make:filament-resource Order
php artisan make:filament-resource Customer
```

### Step 11.2: Product Resource Configuration
```php
// app/Filament/Resources/ProductResource.php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Basic Information')
                ->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('slug')->required(),
                    Textarea::make('description')->required(),
                    Select::make('category_id')
                        ->relationship('category', 'name')
                        ->required(),
                    Select::make('type')
                        ->options([
                            'solar_panel' => 'Solar Panel',
                            'battery' => 'Battery',
                            'inverter' => 'Inverter',
                            'accessory' => 'Accessory',
                        ])
                        ->required(),
                ]),

            Section::make('Pricing & Stock')
                ->schema([
                    TextInput::make('price')
                        ->numeric()
                        ->prefix('৳')
                        ->required(),
                    TextInput::make('compare_price')
                        ->numeric()
                        ->prefix('৳'),
                    TextInput::make('stock')
                        ->numeric()
                        ->required(),
                    Toggle::make('is_available')->default(true),
                    Toggle::make('is_featured'),
                ]),

            Section::make('Specifications')
                ->schema([
                    KeyValue::make('specifications')
                        ->keyLabel('Attribute')
                        ->valueLabel('Value')
                        ->default([
                            'wattage' => '',
                            'voltage' => '',
                            'capacity' => '',
                        ]),
                    TextInput::make('model_number'),
                    TextInput::make('warranty_months')->numeric(),
                ]),

            Section::make('Media')
                ->schema([
                    FileUpload::make('main_image')
                        ->image()
                        ->directory('products')
                        ->required(),
                    FileUpload::make('images')
                        ->image()
                        ->directory('products')
                        ->multiple(),
                    TextInput::make('model_3d_url')
                        ->label('3D Model URL (.glb/.gltf)'),
                ]),
        ]);
}
```

### Step 11.3: Order Management
- Order status workflow (Pending → Confirmed → Processing → Shipped → Delivered)
- Invoice generation
- Refund handling
- Export to CSV/PDF

---

## 12. Performance Optimization

### Step 12.1: Caching Strategy
```bash
# Enable Redis cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 12.2: Image Optimization
```bash
npm install -g sharp
```

**Use Intervention Image:**
```php
use Intervention\Image\Facades\Image;

$image = Image::make($request->file('image'))
    ->fit(800, 800)
    ->encode('webp', 80);
```

### Step 12.3: Lazy Loading
- Implement lazy loading for images
- Use `loading="lazy"` attribute
- Paginate product listings

### Step 12.4: Database Optimization
- Add proper indexes
- Use eager loading (`with()`)
- Implement query caching

---

## 13. Deployment Checklist

### Pre-Deployment
- [ ] Run all migrations: `php artisan migrate --force`
- [ ] Seed initial data: `php artisan db:seed`
- [ ] Build assets: `npm run build`
- [ ] Optimize autoloader: `composer optimize-autoloader`
- [ ] Clear all caches: `php artisan optimize:clear`
- [ ] Set correct file permissions
- [ ] Configure environment variables (.env)
- [ ] Setup SSL certificate
- [ ] Configure CDN for static assets
- [ ] Test payment gateways in production mode
- [ ] Setup email notifications (SMTP)
- [ ] Configure backup strategy
- [ ] Setup monitoring (Sentry, LogRocket)

### Post-Deployment
- [ ] Test complete user journey
- [ ] Verify 3D models load correctly
- [ ] Test on multiple devices/browsers
- [ ] Check SEO meta tags
- [ ] Submit sitemap to Google Search Console
- [ ] Setup Google Analytics
- [ ] Configure social media sharing
- [ ] Test performance (PageSpeed Insights)

---

## 🎨 Design Guidelines for Solar Energy Theme

### Color Palette
```
Primary: #FDB813 (Solar Yellow)
Secondary: #1E3A5F (Deep Blue)
Accent: #4CAF50 (Green Energy)
Dark: #0F172A (Night Sky)
Light: #F8FAFC (Clean White)
```

### Typography
- **Headings**: Inter Bold (English), Hind Siliguri Bold (Bangla)
- **Body**: Inter Regular (English), Hind Siliguri Regular (Bangla)
- **Font Sizes**: 14px base, scalable with rem units

### 3D Animation Best Practices
1. Use GLB/GLTF format for 3D models (compressed)
2. Keep model file size under 2MB
3. Implement LOD (Level of Detail) for performance
4. Add loading skeletons during 3D model load
5. Provide fallback images for unsupported browsers

### Accessibility
- WCAG 2.1 AA compliance
- Keyboard navigation support
- Screen reader friendly
- High contrast mode option
- Bangla language support throughout

---

## 📦 Additional Resources

### Useful Packages
```bash
# SEO
composer require archtechx/seo

# Sitemap
composer require spatie/laravel-sitemap

# Analytics
composer require thephpleague/oauth2-client

# Backup
composer require spatie/laravel-backup

# Activity Log
composer require spatie/laravel-activitylog
```

### 3D Model Sources
- [Sketchfab](https://sketchfab.com) - Solar panel models
- [TurboSquid](https://turbosquid.com) - Battery 3D models
- [Free3D](https://free3d.com) - Free renewable energy assets

### Learning Resources
- [Livewire Documentation](https://livewire.laravel.com)
- [Filament Docs](https://filamentphp.com/docs)
- [Three.js Fundamentals](https://threejs.org/manual/)
- [GSAP Documentation](https://greensock.com/docs/)

---

## 🚀 Next Steps

1. **Week 1-2**: Setup project structure, install dependencies, configure database
2. **Week 3-4**: Build core components (header, footer, product listing)
3. **Week 5-6**: Implement 3D viewers and animations
4. **Week 7-8**: Develop cart, checkout, and payment integration
5. **Week 9-10**: Create user dashboard and authentication
6. **Week 11-12**: Testing, optimization, and deployment

---

## 💡 Pro Tips

1. **Start Mobile-First**: Design for mobile before desktop
2. **Use Filament Widgets**: Create dashboard widgets for admin stats
3. **Implement Queue System**: For emails, image processing, notifications
4. **Add Multi-language**: Use Laravel localization for Bangla/English
5. **Security First**: Implement CSRF, XSS protection, rate limiting
6. **Monitor Performance**: Use Laravel Telescope for debugging
7. **Backup Regularly**: Schedule automated database backups

---

**Good luck building your Solar Energy E-commerce platform! 🌞⚡**

*Last Updated: 2024*
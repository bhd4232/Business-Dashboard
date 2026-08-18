--- solar-3d-theme-setup.md (原始)


+++ solar-3d-theme-setup.md (修改后)
# 🌞 Solar Energy 3D Storefront Setup Guide (via ERP Site Builder)

এই গাইডলাইনটি আপনার বিদ্যমান **ERP/Filament Admin Panel**-এর "Site Build" মডিউল ব্যবহার করে **Solar Energy**-এর জন্য একটি আধুনিক **3D এনিমেটেড লারাভেল Livewire** স্টোরফ্রন্ট তৈরি করার জন্য তৈরি করা হয়েছে।

যেহেতু ব্যাকএন্ড (Product, Order, Inventory) সব রেডি আছে, আমরা শুধুমাত্র **Frontend Design & 3D Integration** নিয়ে আলোচনা করব।

---

## 📋 প্রি-রিকোয়ারমেন্টস (Pre-requisites)

আপনার এডমিন প্যানেলে লগইন করার আগে নিশ্চিত করুন যে আপনার কাছে নিচের রিসোর্সগুলো রেডি আছে:
1.  **3D Models (.glb/.gltf):** সোলার প্যানেল, ইনভার্টার এবং ব্যাটারির হাই-কোয়ালিটি 3D মডেল।
2.  **Texture/Images:** সোলার সেল এর টেক্সচার, ব্যাকগ্রাউন্ড ইমেজ।
3.  **Libraries:** Three.js এবং GSAP (GreenSock) এর CDN লিংক অথবা লোকাল ফাইল পাথ।

---

## 🚀 ধাপ ১: এডমিন প্যানেলে নতুন পেজ/থিম তৈরি

আপনার ERP ড্যাশবোর্ডে যান এবং **Site Builder / Theme Manager** মডিউলে প্রবেশ করুন।

### ১.১ নতুন পেজ কনফিগারেশন
*   **Page Name:** `Solar Home` বা `3D Showroom`
*   **Route/Slug:** `/` (হোমপেজ) অথবা `/showroom`
*   **Layout Template:** `Blank Canvas` বা `Full Width` সিলেক্ট করুন (যাতে আমরা কাস্টম 3D ক্যানভাস ব্যবহার করতে পারি)।
*   **Livewire Component:** যদি মডিউলটি লাইভওয়্যার কম্পোনেন্ট বাইন্ডিং সাপোর্ট করে, তবে একটি নতুন কম্পোনেন্ট নাম দিন: `SolarShowroom`।

---

## 🎨 ধাপ ২: 3D এনভায়রনমেন্ট সেটআপ (Asset Injection)

বেশিরভাগ বিল্ডারে "Header Code" বা "Custom CSS/JS" সেকশন থাকে। সেখানে নিচের কোডগুলো ইনজেক্ট করুন।

### ২.১ প্রয়োজনীয় লাইব্রেরি যুক্ত করা
আপনার এডমিন প্যানেলের **Settings > Assets** অথবা পেজ এডিটরের **Head Section**-এ নিচের CDN লিংকগুলো যুক্ত করুন:

```html
<!-- Three.js for 3D Rendering -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<!-- GLTFLoader for loading 3D models -->
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
<!-- GSAP for Smooth Animations -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/ScrollTrigger.min.js"></script>
```

### ২.২ কাস্টম CSS (Styling)
**Custom CSS** সেকশনে নিচের কোডটি যুক্ত করুন যাতে 3D ক্যানভাস ফুল স্ক্রিন হয় এবং UI এলিমেন্টগুলো তার উপরে ভাসে:

```css
<style>
    #solar-canvas-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        z-index: -1; /* Background e rakhar jonno */
        background: linear-gradient(to bottom, #0f2027, #203a43, #2c5364); /* Space/Sky gradient */
        overflow: hidden;
    }

    .solar-ui-layer {
        position: relative;
        z-index: 10;
        pointer-events: none; /* Click through to 3D model where needed */
    }

    .product-card {
        pointer-events: auto;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        transition: transform 0.3s ease;
    }

    .product-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.2);
    }

    .cta-button {
        background: #fbbf24; /* Solar Yellow */
        color: #000;
        font-weight: bold;
        padding: 12px 24px;
        border-radius: 30px;
        text-transform: uppercase;
        letter-spacing: 1px;
        pointer-events: auto;
    }
</style>
```

---

## 🧱 ধাপ ৩: Livewire কম্পোনেন্ট স্ট্রাকচার (Logic)

আপনার বিল্ডারে যদি "Custom Blade View" বা "Livewire Component" এডিট করার অপশন থাকে, তবে নিচের স্ট্রাকচারটি ব্যবহার করুন।

### ৩.১ কম্পোনেন্ট লজিক (PHP)
`app/Http/Livewire/SolarShowroom.php` (অথবা আপনার বিল্ডারে যেভাবে কাস্টম কোড লেখা যায়):

```php
namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Product; // আপনার ERP এর প্রোডাক্ট মডেল

class SolarShowroom extends Component
{
    public $products;
    public $selectedCategory = 'panel'; // panel, battery, inverter

    public function mount()
    {
        // ERP থেকে সোলার প্রোডাক্টগুলো লোড করা
        $this->products = Product::where('type', 'solar')
                                 ->where('status', 'active')
                                 ->get();
    }

    public function filterProducts($category)
    {
        $this->selectedCategory = $category;
        // এখানে AJAX বা Alpine.js দিয়ে 3D সিন আপডেট ট্রিগার করা যেতে পারে
    }

    public function render()
    {
        return view('livewire.solar-showroom');
    }
}
```

### ৩.২ ভিউ ফাইল (Blade Template)
আপনার বিল্ডারের **Body Content** বা **View Editor**-এ নিচের কোডটি পেস্ট করুন। এটি 3D ক্যানভাস এবং UI লেয়ারকে একত্রিত করবে।

```html
<div wire:ignore.self>
    <!-- 3D Background Container -->
    <div id="solar-canvas-container"></div>

    <!-- UI Overlay Layer -->
    <div class="solar-ui-layer container mx-auto px-4 py-10">

        <!-- Header / Nav -->
        <nav class="flex justify-between items-center mb-20">
            <h1 class="text-4xl font-bold text-white tracking-widest">SOLAR<span class="text-yellow-400">TECH</span></h1>
            <div class="space-x-4">
                <button wire:click="filterProducts('panel')" class="text-white hover:text-yellow-400">Panels</button>
                <button wire:click="filterProducts('battery')" class="text-white hover:text-yellow-400">Batteries</button>
                <button wire:click="filterProducts('inverter')" class="text-white hover:text-yellow-400">Inverters</button>
            </div>
            <a href="/cart" class="text-white border border-white px-4 py-2 rounded-full">Cart (0)</a>
        </nav>

        <!-- Hero Section with 3D Interaction Hint -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center h-screen">
            <div class="space-y-6">
                <h2 class="text-6xl font-extrabold text-white leading-tight">
                    Power Your Future <br> with <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-orange-500">Sun Energy</span>
                </h2>
                <p class="text-gray-300 text-lg">Explore our high-efficiency solar panels and batteries in 3D.</p>
                <button class="cta-button">Explore Products</button>
            </div>

            <!-- Right side is empty for 3D Model visibility -->
            <div></div>
        </div>

        <!-- Product Grid (Floating over 3D) -->
        <section class="py-20">
            <h3 class="text-3xl text-white mb-10 text-center">Featured Solutions</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($products as $product)
                <div class="product-card p-6 rounded-xl shadow-lg">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-48 object-cover rounded-lg mb-4">
                    <h4 class="text-xl font-bold">{{ $product->name }}</h4>
                    <p class="text-yellow-400 text-lg">${{ $product->price }}</p>
                    <button class="w-full mt-4 bg-white/20 hover:bg-white/40 text-white py-2 rounded transition">
                        View Details
                    </button>
                </div>
                @endforeach
            </div>
        </section>
    </div>
</div>

<!-- 3D Logic Script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Scene Setup
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });

        renderer.setSize(window.innerWidth, window.innerHeight);
        document.getElementById('solar-canvas-container').appendChild(renderer.domElement);

        // Lighting (Crucial for Solar theme)
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        scene.add(ambientLight);

        const sunLight = new THREE.DirectionalLight(0xfbbf24, 1.5); // Solar yellow light
        sunLight.position.set(10, 10, 10);
        scene.add(sunLight);

        // Camera Position
        camera.position.z = 5;

        // Placeholder Geometry (Replace with GLTF Loader for real models)
        // Example: A rotating Solar Panel representation
        const geometry = new THREE.BoxGeometry(2, 2, 0.2);
        const material = new THREE.MeshStandardMaterial({
            color: 0x1e3a8a, // Deep Blue
            roughness: 0.2,
            metalness: 0.8
        });
        const solarPanel = new THREE.Mesh(geometry, material);
        scene.add(solarPanel);

        // Animation Loop
        function animate() {
            requestAnimationFrame(animate);

            // Rotate the panel slowly
            solarPanel.rotation.y += 0.005;
            solarPanel.rotation.x = Math.sin(Date.now() * 0.001) * 0.1;

            renderer.render(scene, camera);
        }
        animate();

        // Responsive Resize
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        // GSAP Scroll Animation Example
        gsap.registerPlugin(ScrollTrigger);
        gsap.to(solarPanel.position, {
            scrollTrigger: {
                trigger: ".product-grid",
                start: "top bottom",
                end: "bottom top",
                scrub: 1
            },
            x: -2, // Move panel to left on scroll
            z: 1
        });
    });
</script>
```

---

## ⚙️ ধাপ ৪: 3D মডেল ইন্টিগ্রেশন (Advanced)

উপরের কোডে একটি সাধারণ বক্স ব্যবহার করা হয়েছে। প্রফেশনাল লুকের জন্য আপনার `.glb` ফাইল লোড করতে হবে।

1.  আপনার ERP এর **Media Manager** বা **File Upload** সেকশনে সোলার প্যানেলের `.glb` ফাইল আপলোড করুন।
2.  ফাইলটির URL কপি করুন।
3.  উপরের `<script>` সেকশনের `Placeholder Geometry` অংশটি মুছে নিচের `GLTFLoader` কোডটি বসান:

```javascript
const loader = new THREE.GLTFLoader();
loader.load('YOUR_UPLOADED_GLB_URL_HERE', function (gltf) {
    const model = gltf.scene;
    model.scale.set(1, 1, 1);
    model.position.set(2, 0, 0); // Right side e rakha
    scene.add(model);

    // Model Entry Animation
    model.scale.set(0,0,0);
    gsap.to(model.scale, {x:1, y:1, z:1, duration: 2, ease: "elastic.out(1, 0.3)"});
}, undefined, function (error) {
    console.error(error);
});
```

---

## 🛠 ধাপ ৫: ইন্টারঅ্যাকশন ও অ্যানিমেশন টিউনিং

আপনার এডমিন প্যানেলের **Animation Settings** (যদি থাকে) অথবা কাস্টম JS সেকশনে নিচের ফিচারগুলো যুক্ত করুন:

1.  **Mouse Move Parallax:** মাউস নড়লে যেন 3D মডেলটি একটু ঘুরে।
2.  **Click to Explode:** প্রোডাক্টে ক্লিক করলে যেন মডেলটি এক্সপ্লোড ভিউতে যায় (Parts আলাদা হয়ে যায়) - এটি Three.js এর `animation.mixer` দিয়ে কন্ট্রোল করতে হয়।
3.  **Loading Screen:** 3D মডেল লোড হওয়ার সময় একটি সুন্দর "Solar Loading" স্পিনার দেখান।

---

## ✅ ধাপ ৬: সেভ এবং পাবলিশ

1.  এডমিন প্যানেলে **Save Draft** এ ক্লিক করুন।
2.  **Preview** বাটনে ক্লিক করে দেখুন 3D মডেলটি ঠিকমতো লোড হচ্ছে কিনা এবং মোবাইল রেসপন্সিভ কিনা।
3.  সব ঠিক থাকলে **Publish** করুন।

---

## 💡 প্রো টিপস (Pro Tips for Solar Theme)

*   **Performance:** 3D মডেলের সাইজ ৫এমবি এর নিচে রাখুন (`.glb` ফরম্যাটে কম্প্রেস করে)।
*   **Lighting:** সোলার থিমের জন্য `DirectionalLight` (সূর্যের আলো) এবং `HemisphereLight` (আকাশের আলো) এর কম্বিনেশন ব্যবহার করুন।
*   **Colors:** কালার প্যালেট হিসেবে `Deep Blue` (#1e3a8a), `Solar Yellow` (#fbbf24), এবং `Clean White` ব্যবহার করুন।
*   **Filament Integration:** যদি আপনি Filament এর `Form` বা `Table` থেকে ডাটা ডাইনামিকভাবে 3D সিনে পাঠাতে চান, তবে Livewire এর `$dispatch` ইভেন্ট ব্যবহার করে JS কে ট্রিগার করুন।

---

এই গাইডলাইন অনুসরণ করে আপনি আপনার ERP এর বিল্ট-ইন মডিউল ব্যবহার করেই একটি বিশ্বমানের 3D সোলার ইকমার্স সাইট দাঁড় করাতে পারবেন। কোনো নির্দিষ্ট কোড স্নিপেট বা এরর আসলে জানাতে পারেন!
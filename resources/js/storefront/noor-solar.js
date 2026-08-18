const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function supportsWebGL() {
    try {
        const canvas = document.createElement('canvas');

        return Boolean(window.WebGLRenderingContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
    } catch {
        return false;
    }
}

async function mountModuleLab(lab) {
    const stage = lab.querySelector('[data-noor-module-stage]');
    const fallback = lab.querySelector('[data-noor-stage-fallback]');
    const status = lab.querySelector('[data-noor-module-status]');
    const controls = [...lab.querySelectorAll('[data-noor-module-view]')];

    if (!stage || !supportsWebGL()) {
        if (status) status.textContent = 'Interactive 3D is unavailable. Use the product details and view controls as the accessible fallback.';
        return;
    }

    if (status) status.textContent = 'Loading the interactive module preview…';

    let THREE;
    try {
        THREE = await import('three');
    } catch {
        if (status) status.textContent = 'The 3D preview could not load. The static product preview remains available.';
        return;
    }

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 100);
    camera.position.set(0, 0.1, 8.4);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.setClearColor(0x000000, 0);
    renderer.domElement.setAttribute('aria-hidden', 'true');
    stage.prepend(renderer.domElement);

    scene.add(new THREE.AmbientLight(0xffffff, 1.7));

    const keyLight = new THREE.DirectionalLight(0xffffff, 3);
    keyLight.position.set(4, 5, 6);
    scene.add(keyLight);

    const goldLight = new THREE.PointLight(0xf5bf17, 10, 16);
    goldLight.position.set(-3.5, 2.5, 4);
    scene.add(goldLight);

    const greenLight = new THREE.PointLight(0x1cc38a, 7, 14);
    greenLight.position.set(3.5, -2.2, 3);
    scene.add(greenLight);

    const product = new THREE.Group();
    scene.add(product);

    const width = 3.6;
    const height = 5.55;
    const depth = 0.16;
    const bodyMaterial = new THREE.MeshStandardMaterial({ color: 0x17221e, metalness: 0.42, roughness: 0.38 });
    product.add(new THREE.Mesh(new THREE.BoxGeometry(width, height, depth), bodyMaterial));

    const glass = new THREE.Mesh(
        new THREE.PlaneGeometry(width - 0.12, height - 0.12),
        new THREE.MeshPhysicalMaterial({
            color: 0x061e27,
            metalness: 0.15,
            roughness: 0.18,
            transparent: true,
            opacity: 0.94,
            clearcoat: 1,
            clearcoatRoughness: 0.12,
        }),
    );
    glass.position.z = depth / 2 + 0.012;
    product.add(glass);

    const cells = new THREE.Group();
    const columns = 6;
    const rows = 12;
    const horizontalGap = 0.12;
    const verticalGap = 0.1;
    const usableWidth = width - 0.32;
    const usableHeight = height - 0.38;
    const cellWidth = (usableWidth - horizontalGap * (columns - 1)) / columns;
    const cellHeight = (usableHeight - verticalGap * (rows - 1)) / rows;
    const cellGeometry = new THREE.PlaneGeometry(cellWidth, cellHeight);
    const cellMaterial = new THREE.MeshStandardMaterial({ color: 0x0b3444, metalness: 0.38, roughness: 0.28, emissive: 0x03131a, emissiveIntensity: 0.38 });

    for (let row = 0; row < rows; row += 1) {
        for (let column = 0; column < columns; column += 1) {
            const cell = new THREE.Mesh(cellGeometry, cellMaterial);
            cell.position.x = -usableWidth / 2 + cellWidth / 2 + column * (cellWidth + horizontalGap);
            cell.position.y = usableHeight / 2 - cellHeight / 2 - row * (cellHeight + verticalGap);
            cell.position.z = depth / 2 + 0.024;
            cells.add(cell);
        }
    }
    product.add(cells);

    const frameMaterial = new THREE.MeshStandardMaterial({ color: 0xc0c8c4, metalness: 0.9, roughness: 0.22 });
    const frameBar = 0.075;
    const horizontalFrame = new THREE.BoxGeometry(width + 0.1, frameBar, depth + 0.1);
    const verticalFrame = new THREE.BoxGeometry(frameBar, height, depth + 0.1);

    [height / 2, -height / 2].forEach((y) => {
        const part = new THREE.Mesh(horizontalFrame, frameMaterial);
        part.position.y = y;
        product.add(part);
    });

    [-width / 2, width / 2].forEach((x) => {
        const part = new THREE.Mesh(verticalFrame, frameMaterial);
        part.position.x = x;
        product.add(part);
    });

    const junction = new THREE.Mesh(
        new THREE.BoxGeometry(0.82, 0.58, 0.22),
        new THREE.MeshStandardMaterial({ color: 0x111713, metalness: 0.18, roughness: 0.65 }),
    );
    junction.position.set(0, 0.65, -depth / 2 - 0.14);
    product.add(junction);

    const ring = new THREE.Mesh(
        new THREE.RingGeometry(2.35, 2.52, 72),
        new THREE.MeshBasicMaterial({ color: 0xf5bf17, transparent: true, opacity: 0.16, side: THREE.DoubleSide }),
    );
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = -3.05;
    scene.add(ring);

    let targetX = -0.05;
    let targetY = -0.38;
    let frame = null;
    let dragging = false;
    let previousX = 0;
    let previousY = 0;

    const render = () => {
        frame = null;
        const motionFactor = reducedMotion.matches ? 1 : 0.12;
        product.rotation.x += (targetX - product.rotation.x) * motionFactor;
        product.rotation.y += (targetY - product.rotation.y) * motionFactor;
        renderer.render(scene, camera);

        const moving = Math.abs(targetX - product.rotation.x) > 0.001 || Math.abs(targetY - product.rotation.y) > 0.001;
        if (moving || dragging) frame = requestAnimationFrame(render);
    };

    const requestRender = () => {
        if (frame === null) frame = requestAnimationFrame(render);
    };

    const setView = (view) => {
        controls.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.noorModuleView === view)));

        if (view === 'back') {
            targetX = -0.03;
            targetY = Math.PI - 0.22;
            cells.visible = false;
        } else if (view === 'cells') {
            targetX = 0;
            targetY = 0;
            cells.visible = true;
        } else {
            targetX = -0.05;
            targetY = -0.38;
            cells.visible = true;
        }

        if (status) status.textContent = `${view.charAt(0).toUpperCase() + view.slice(1)} view selected.`;
        requestRender();
    };

    controls.forEach((button) => button.addEventListener('click', () => setView(button.dataset.noorModuleView)));

    renderer.domElement.addEventListener('pointerdown', (event) => {
        dragging = true;
        previousX = event.clientX;
        previousY = event.clientY;
        renderer.domElement.setPointerCapture(event.pointerId);
        requestRender();
    });

    renderer.domElement.addEventListener('pointermove', (event) => {
        if (!dragging || reducedMotion.matches) return;
        targetY += (event.clientX - previousX) * 0.008;
        targetX = Math.max(-0.45, Math.min(0.45, targetX + (event.clientY - previousY) * 0.005));
        previousX = event.clientX;
        previousY = event.clientY;
        requestRender();
    });

    const stopDragging = (event) => {
        dragging = false;
        if (renderer.domElement.hasPointerCapture(event.pointerId)) renderer.domElement.releasePointerCapture(event.pointerId);
    };

    renderer.domElement.addEventListener('pointerup', stopDragging);
    renderer.domElement.addEventListener('pointercancel', stopDragging);

    const resize = () => {
        const width = stage.clientWidth;
        const height = stage.clientHeight;
        if (!width || !height) return;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        requestRender();
    };

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(stage);
    product.rotation.set(targetX, targetY, 0.03);
    resize();
    if (fallback) fallback.hidden = true;
    if (status) status.textContent = 'Interactive 3D preview ready. Use drag or the Front, Cells and Back controls.';

    window.addEventListener('pagehide', () => {
        if (frame !== null) cancelAnimationFrame(frame);
        resizeObserver.disconnect();
        scene.traverse((object) => {
            object.geometry?.dispose?.();
            if (Array.isArray(object.material)) object.material.forEach((material) => material.dispose());
            else object.material?.dispose?.();
        });
        renderer.dispose();
    }, { once: true });
}

export function initNoorSolarTheme() {
    document.querySelectorAll('[data-noor-module-lab]').forEach((lab) => {
        if (typeof IntersectionObserver === 'undefined') {
            mountModuleLab(lab);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            observer.disconnect();
            mountModuleLab(lab);
        }, { rootMargin: '240px 0px' });

        observer.observe(lab);
    });
}

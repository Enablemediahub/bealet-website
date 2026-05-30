<?php
/**
 * Bealet Website - AR Try-On Page
 */

require_once __DIR__ . '/inc/header.php';

global $db;

$tryOnProducts = [];
$productColumnMap = [];

try {
    foreach ($db->fetchAll("SHOW COLUMNS FROM products") as $column) {
        if (!empty($column['Field'])) {
            $productColumnMap[$column['Field']] = true;
        }
    }
} catch (Throwable $e) {
    $productColumnMap = [];
}

try {
    $selectFields = [
        'id',
        'name',
        'brand',
        'category',
        'price',
        'main_image',
        'ar_model_2d',
        isset($productColumnMap['ar_model_2d_left']) ? 'ar_model_2d_left' : "'' AS ar_model_2d_left",
        isset($productColumnMap['ar_model_2d_right']) ? 'ar_model_2d_right' : "'' AS ar_model_2d_right",
        'ar_model_3d',
        'ar_position_x',
        'ar_position_y',
        'ar_scale',
        'is_featured',
    ];

    $tryOnProducts = $db->fetchAll(
        "SELECT " . implode(', ', $selectFields) . "
         FROM products
         WHERE is_active = 1
           AND (
                (ar_model_2d IS NOT NULL AND ar_model_2d <> '')
                OR (ar_model_3d IS NOT NULL AND ar_model_3d <> '')
           )
         ORDER BY is_featured DESC, name ASC"
    );
} catch (Throwable $e) {
    $tryOnProducts = [];
}

$tryOnCatalog = [];
$requestedFrameId = isset($_GET['frame']) ? (int) $_GET['frame'] : 0;
foreach ($tryOnProducts as $product) {
    $tryOnCatalog[] = [
        'id' => (int) ($product['id'] ?? 0),
        'name' => (string) ($product['name'] ?? ''),
        'brand' => (string) ($product['brand'] ?? ''),
        'price' => isset($product['price']) ? formatCurrency((float) $product['price']) : '',
        'image_url' => getProductImagePath($product),
        'front_asset' => getTryOnAssetUrl($product['ar_model_2d'] ?? ''),
        'left_asset' => getTryOnAssetUrl($product['ar_model_2d_left'] ?? ''),
        'right_asset' => getTryOnAssetUrl($product['ar_model_2d_right'] ?? ''),
        'glb_asset' => getTryOnAssetUrl($product['ar_model_3d'] ?? ''),
        'position_x' => (int) ($product['ar_position_x'] ?? 0),
        'position_y' => (int) ($product['ar_position_y'] ?? 0),
        'scale' => (float) ($product['ar_scale'] ?? 1),
        'featured' => !empty($product['is_featured']),
    ];
}
?>

<style>
    .tryon-shell-card {
        border-radius: 1.75rem;
        overflow: hidden;
    }

    .tryon-stage {
        position: relative;
        background:
            radial-gradient(circle at top, rgba(59, 130, 246, 0.18), transparent 34%),
            linear-gradient(180deg, #020617 0%, #0f172a 100%);
        min-height: 620px;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        touch-action: none;
        user-select: none;
    }

    .tryon-stage canvas,
    .tryon-stage video {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .tryon-overlay-pill {
        background: rgba(2, 6, 23, 0.56);
        backdrop-filter: blur(10px);
    }

    .tryon-gesture-hint {
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
        border: 1px solid rgba(37, 99, 235, 0.12);
        color: #334155;
    }

    .tryon-control-value {
        min-width: 64px;
        text-align: right;
        font-weight: 700;
        color: #1d4ed8;
    }

    .tryon-frame-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .tryon-frame-card:hover,
    .tryon-frame-card:focus {
        transform: translateY(-1px);
        box-shadow: 0 14px 26px rgba(15, 23, 42, 0.08);
    }

    @media (max-width: 991px) {
        .tryon-stage {
            min-height: 70vh;
            aspect-ratio: auto;
        }
    }

    @media (max-width: 768px) {
        .tryon-stage {
            min-height: 78vh;
            border-radius: 1.2rem;
        }

        .tryon-stage-topbar,
        .tryon-stage-bottombar {
            padding: 0.85rem !important;
        }

        .tryon-stage-topbar .btn {
            min-height: 42px;
            padding-inline: 0.95rem;
        }

        .tryon-gesture-hint {
            font-size: 0.92rem;
        }
    }
</style>

<nav aria-label="breadcrumb" class="mt-4 mb-4">
    <div class="container-lg">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
            <li class="breadcrumb-item active">AR Try-On</li>
        </ol>
    </div>
</nav>

<section class="mb-4">
    <div class="container-lg">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <h1 class="mb-2">Virtual Try-On</h1>
                <p class="text-muted mb-0">Turn on your phone or laptop camera and test frame styles that track your face live.</p>
            </div>
            <div class="small text-muted">
                Powered by client-side face tracking and your admin-managed try-on assets.
            </div>
        </div>
    </div>
</section>

<section class="section-spacing pt-0">
    <div class="container-lg">
        <?php if (empty($tryOnCatalog)): ?>
        <div class="alert alert-info border-0 rounded-4 shadow-sm">
            No try-on-ready frame assets have been uploaded yet. Add a frame product in Admin and upload either a front transparent PNG or a GLB model in the AR Try-On Assets section.
        </div>
        <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm tryon-shell-card">
                    <div class="card-body p-0">
                        <div id="tryOnStage" class="tryon-stage">
                            <video id="tryOnVideo" autoplay playsinline muted style="display:none;"></video>
                            <canvas id="tryOnCanvas" style="display:block;"></canvas>
                            <canvas id="tryOnGlbCanvas" style="pointer-events:none;"></canvas>

                            <div id="tryOnLoadingState" class="position-absolute top-50 start-50 translate-middle text-center text-white px-4">
                                <div class="spinner-border text-light mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <h5 class="mb-2">Preparing camera and face tracking</h5>
                                <p class="mb-0 text-white-50">Allow camera access when your browser asks.</p>
                            </div>

                            <div class="tryon-stage-topbar position-absolute top-0 start-0 end-0 p-3 d-flex justify-content-between align-items-start gap-2">
                                <div class="tryon-overlay-pill text-white rounded-pill px-3 py-2 small">
                                    <span id="trackingStatus">Waiting for camera...</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <button id="flipCameraBtn" type="button" class="btn btn-light btn-sm rounded-pill">
                                        <i class="fas fa-camera-rotate me-1"></i> Flip
                                    </button>
                                    <button id="captureTryOnBtn" type="button" class="btn btn-primary btn-sm rounded-pill">
                                        <i class="fas fa-camera me-1"></i> Capture
                                    </button>
                                </div>
                            </div>

                            <div class="tryon-stage-bottombar position-absolute bottom-0 start-0 end-0 p-3">
                                <div class="tryon-overlay-pill text-white rounded-4 p-3 d-flex flex-wrap gap-3 align-items-center justify-content-between">
                                    <div>
                                        <div class="small text-uppercase text-white-50 mb-1">Active Frame</div>
                                        <div id="activeFrameLabel" class="fw-semibold">Choose a frame</div>
                                    </div>
                                    <div class="small text-white-50">
                                        Drag to nudge the frame. Pinch on mobile or scroll on laptop to resize quickly.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-sliders me-2 text-primary"></i>Try-On Controls</h5>

                        <div class="tryon-gesture-hint p-3 mb-3">
                            <div class="fw-semibold mb-1">Touch-friendly controls</div>
                            <div class="small">
                                Use one finger to move the frame, two fingers to zoom on mobile, or your mouse wheel to zoom on laptop.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="framePicker">Frame</label>
                            <select id="framePicker" class="form-select"></select>
                        </div>

                        <div class="border rounded-4 p-3 bg-white mb-3">
                            <div class="small text-uppercase text-muted mb-1">Selected Frame</div>
                            <div id="activeFramePanelName" class="fw-semibold text-dark">Choose a frame</div>
                            <div id="activeFramePanelBrand" class="small text-muted mb-1"></div>
                            <div id="activeFramePanelPrice" class="small text-primary fw-semibold mb-3"></div>
                            <div class="d-grid gap-2">
                                <button type="button" id="buyActiveFrameBtn" class="btn btn-primary rounded-pill">
                                    <i class="fas fa-bag-shopping me-2"></i> Buy This Frame
                                </button>
                                <a href="#" id="viewActiveFrameBtn" class="btn btn-outline-primary rounded-pill">
                                    <i class="fas fa-eye me-2"></i> View Product
                                </a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="cameraPicker">Camera</label>
                            <select id="cameraPicker" class="form-select">
                                <option value="">Loading cameras...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <label class="form-label mb-1">Horizontal Fine Tune</label>
                                <span id="manualOffsetXValue" class="tryon-control-value">0 px</span>
                            </div>
                            <input type="range" id="manualOffsetX" class="form-range" min="-80" max="80" step="1" value="0">
                            <small class="text-muted">Shift the frame left or right if needed.</small>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <label class="form-label mb-1">Vertical Fine Tune</label>
                                <span id="manualOffsetYValue" class="tryon-control-value">0 px</span>
                            </div>
                            <input type="range" id="manualOffsetY" class="form-range" min="-80" max="80" step="1" value="0">
                            <small class="text-muted">Raise or lower the frame on your face.</small>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <label class="form-label mb-1">Size Fine Tune</label>
                                <span id="manualScaleValue" class="tryon-control-value">100%</span>
                            </div>
                            <input type="range" id="manualScale" class="form-range" min="0.7" max="1.5" step="0.02" value="1">
                            <small class="text-muted">Make the frame slightly larger or smaller.</small>
                        </div>

                        <div class="d-grid">
                            <button type="button" id="resetTryOnTuningBtn" class="btn btn-outline-primary rounded-pill">
                                <i class="fas fa-rotate-left me-2"></i> Reset Tuning
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="fas fa-glasses me-2 text-primary"></i>Available Frames</h5>
                            <span class="badge bg-light text-dark border"><?php echo count($tryOnCatalog); ?> Ready</span>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($tryOnCatalog as $index => $frame): ?>
                            <div class="col-12">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary w-100 text-start rounded-4 p-3 tryon-frame-card"
                                    data-frame-index="<?php echo (int) $index; ?>"
                                >
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo sanitize($frame['image_url']); ?>" alt="<?php echo sanitize($frame['name']); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:14px;border:1px solid #e2e8f0;">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold text-dark"><?php echo sanitize($frame['name']); ?></div>
                                            <div class="small text-muted"><?php echo sanitize($frame['brand']); ?></div>
                                            <div class="small text-primary mt-1"><?php echo sanitize($frame['price']); ?></div>
                                        </div>
                                        <?php if ($frame['featured']): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($tryOnCatalog)): ?>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/examples/js/loaders/GLTFLoader.js" crossorigin="anonymous"></script>
<script>
    const tryOnFrames = <?php echo json_encode($tryOnCatalog, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const requestedFrameId = <?php echo (int) $requestedFrameId; ?>;

    const video = document.getElementById('tryOnVideo');
    const canvas = document.getElementById('tryOnCanvas');
    const glbCanvas = document.getElementById('tryOnGlbCanvas');
    const ctx = canvas.getContext('2d');
    const loadingState = document.getElementById('tryOnLoadingState');
    const framePicker = document.getElementById('framePicker');
    const cameraPicker = document.getElementById('cameraPicker');
    const buyActiveFrameBtn = document.getElementById('buyActiveFrameBtn');
    const viewActiveFrameBtn = document.getElementById('viewActiveFrameBtn');
    const flipCameraBtn = document.getElementById('flipCameraBtn');
    const captureTryOnBtn = document.getElementById('captureTryOnBtn');
    const trackingStatus = document.getElementById('trackingStatus');
    const activeFrameLabel = document.getElementById('activeFrameLabel');
    const activeFramePanelName = document.getElementById('activeFramePanelName');
    const activeFramePanelBrand = document.getElementById('activeFramePanelBrand');
    const activeFramePanelPrice = document.getElementById('activeFramePanelPrice');
    const manualOffsetX = document.getElementById('manualOffsetX');
    const manualOffsetXValue = document.getElementById('manualOffsetXValue');
    const manualOffsetY = document.getElementById('manualOffsetY');
    const manualOffsetYValue = document.getElementById('manualOffsetYValue');
    const manualScale = document.getElementById('manualScale');
    const manualScaleValue = document.getElementById('manualScaleValue');
    const resetTryOnTuningBtn = document.getElementById('resetTryOnTuningBtn');
    const tryOnStage = document.getElementById('tryOnStage');

    let activeFrameIndex = 0;
    let currentFacingMode = 'user';
    let cameraController = null;
    let faceMesh = null;
    let cachedAssets = {};
    let cachedGlbAssets = {};
    let glbLoader = null;
    let glbRenderer = null;
    let glbScene = null;
    let glbCamera = null;
    let glbModelRoot = null;
    let glbLights = [];
    let glbRuntimeEnabled = false;
    const activePointers = new Map();
    let dragOrigin = null;
    let pinchOrigin = null;

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function syncFineTuneLabels() {
        manualOffsetXValue.textContent = `${Math.round(Number(manualOffsetX.value || 0))} px`;
        manualOffsetYValue.textContent = `${Math.round(Number(manualOffsetY.value || 0))} px`;
        manualScaleValue.textContent = `${Math.round(Number(manualScale.value || 1) * 100)}%`;
    }

    function applyFineTuneValue(input, nextValue) {
        const min = Number(input.min);
        const max = Number(input.max);
        const step = Number(input.step || 1);
        const normalized = clamp(nextValue, min, max);
        const snapped = step > 0 ? Math.round(normalized / step) * step : normalized;
        input.value = String(Number(snapped.toFixed(4)));
        syncFineTuneLabels();
    }

    function resetFineTuneControls() {
        manualOffsetX.value = '0';
        manualOffsetY.value = '0';
        manualScale.value = '1';
        syncFineTuneLabels();
    }

    function getActiveFrameProductUrl(frame) {
        return `${window.BASE_URL || ''}/shop.php?view_product=${Number(frame.id)}`;
    }

    function syncActiveFramePanel() {
        const activeFrame = getActiveFrame();
        activeFrameLabel.textContent = activeFrame.name || 'Choose a frame';
        activeFramePanelName.textContent = activeFrame.name || 'Choose a frame';
        activeFramePanelBrand.textContent = activeFrame.brand || '';
        activeFramePanelPrice.textContent = activeFrame.price || '';
        viewActiveFrameBtn.href = getActiveFrameProductUrl(activeFrame);
    }

    function getPointerDistance(first, second) {
        return Math.hypot(second.x - first.x, second.y - first.y);
    }

    function beginDrag(pointer) {
        dragOrigin = {
            x: pointer.x,
            y: pointer.y,
            offsetX: Number(manualOffsetX.value || 0),
            offsetY: Number(manualOffsetY.value || 0),
        };
    }

    function beginPinch() {
        const pointers = Array.from(activePointers.values());
        if (pointers.length < 2) {
            pinchOrigin = null;
            return;
        }

        pinchOrigin = {
            distance: getPointerDistance(pointers[0], pointers[1]),
            scale: Number(manualScale.value || 1),
        };
    }

    function updateGestureState() {
        const pointers = Array.from(activePointers.values());
        if (pointers.length >= 2) {
            if (!pinchOrigin) {
                beginPinch();
            }
            dragOrigin = null;
            return;
        }

        pinchOrigin = null;
        if (pointers.length === 1 && !dragOrigin) {
            beginDrag(pointers[0]);
        }
    }

    function handleStagePointerMove(event) {
        if (!activePointers.has(event.pointerId)) {
            return;
        }

        activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        const pointers = Array.from(activePointers.values());

        if (pointers.length >= 2 && pinchOrigin) {
            const distance = getPointerDistance(pointers[0], pointers[1]);
            const zoomMultiplier = distance / Math.max(pinchOrigin.distance, 1);
            applyFineTuneValue(manualScale, pinchOrigin.scale * zoomMultiplier);
            return;
        }

        if (pointers.length === 1 && dragOrigin) {
            const pointer = pointers[0];
            const deltaX = pointer.x - dragOrigin.x;
            const deltaY = pointer.y - dragOrigin.y;
            applyFineTuneValue(manualOffsetX, dragOrigin.offsetX + (deltaX * 0.35));
            applyFineTuneValue(manualOffsetY, dragOrigin.offsetY + (deltaY * 0.35));
        }
    }

    function handleStagePointerEnd(event) {
        activePointers.delete(event.pointerId);
        if (activePointers.size === 0) {
            dragOrigin = null;
            pinchOrigin = null;
            return;
        }
        updateGestureState();
    }

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            canvas.width = rect.width;
            canvas.height = rect.height;
            glbCanvas.width = rect.width;
            glbCanvas.height = rect.height;
            if (glbRenderer) {
                glbRenderer.setSize(rect.width, rect.height, false);
            }
            if (glbCamera) {
                glbCamera.left = -rect.width / 2;
                glbCamera.right = rect.width / 2;
                glbCamera.top = rect.height / 2;
                glbCamera.bottom = -rect.height / 2;
                glbCamera.updateProjectionMatrix();
            }
        }
    }

    function getPoint(landmark) {
        const x = landmark.x * canvas.width;
        const y = landmark.y * canvas.height;
        return {
            x: currentFacingMode === 'user' ? canvas.width - x : x,
            y,
        };
    }

    function getAveragePoint(landmarks, indices) {
        let totalX = 0;
        let totalY = 0;

        indices.forEach((index) => {
            const point = getPoint(landmarks[index]);
            totalX += point.x;
            totalY += point.y;
        });

        return {
            x: totalX / indices.length,
            y: totalY / indices.length,
        };
    }

    function getActiveFrame() {
        return tryOnFrames[activeFrameIndex] || tryOnFrames[0];
    }

    function preloadImage(url) {
        if (!url) {
            return Promise.resolve(null);
        }
        if (cachedAssets[url]) {
            return Promise.resolve(cachedAssets[url]);
        }

        return new Promise((resolve) => {
            const image = new Image();
            image.onload = () => {
                cachedAssets[url] = image;
                resolve(image);
            };
            image.onerror = () => resolve(null);
            image.src = url;
        });
    }

    function clearGlbModel() {
        if (!glbScene || !glbModelRoot) {
            return;
        }
        glbScene.remove(glbModelRoot);
        glbModelRoot = null;
        renderGlbScene();
    }

    function renderGlbScene() {
        if (glbRenderer && glbScene && glbCamera) {
            glbRenderer.render(glbScene, glbCamera);
        }
    }

    function createGlbRuntimeModel(sourceScene) {
        const runtimeGroup = new THREE.Group();
        const model = sourceScene.clone(true);
        model.traverse((child) => {
            if (child.isMesh) {
                child.castShadow = false;
                child.receiveShadow = false;
                if (child.material) {
                    const material = Array.isArray(child.material)
                        ? child.material.map((entry) => entry.clone())
                        : child.material.clone();
                    child.material = material;
                }
            }
        });
        runtimeGroup.add(model);
        return runtimeGroup;
    }

    function loadGlbAsset(url) {
        if (!url || !glbLoader || !glbRuntimeEnabled) {
            return Promise.resolve(null);
        }

        if (cachedGlbAssets[url]) {
            return Promise.resolve(createGlbRuntimeModel(cachedGlbAssets[url]));
        }

        return new Promise((resolve) => {
            glbLoader.load(
                url,
                (gltf) => {
                    cachedGlbAssets[url] = gltf.scene;
                    resolve(createGlbRuntimeModel(gltf.scene));
                },
                undefined,
                () => resolve(null)
            );
        });
    }

    async function syncActiveGlbModel() {
        const frame = getActiveFrame();
        if (!frame || !frame.glb_asset) {
            clearGlbModel();
            return null;
        }

        const loadedModel = await loadGlbAsset(frame.glb_asset);
        clearGlbModel();

        if (!loadedModel || !glbScene) {
            return null;
        }

        loadedModel.visible = false;
        glbModelRoot = loadedModel;
        glbScene.add(glbModelRoot);
        renderGlbScene();
        return glbModelRoot;
    }

    function updateGlbModelPose(leftEyeOuter, rightEyeOuter, irisLeft, irisRight, irisMidpoint, turnRatio, activeFrame) {
        if (!glbModelRoot) {
            return false;
        }

        const eyeDistance = Math.hypot(rightEyeOuter.x - leftEyeOuter.x, rightEyeOuter.y - leftEyeOuter.y);
        const irisDistance = Math.max(1, Math.hypot(irisRight.x - irisLeft.x, irisRight.y - irisLeft.y));
        const angle = Math.atan2(rightEyeOuter.y - leftEyeOuter.y, rightEyeOuter.x - leftEyeOuter.x);
        const baseScale = Number(activeFrame.scale || 1);
        const userScale = Number(manualScale.value || 1);
        const offsetX = Number(activeFrame.position_x || 0) + Number(manualOffsetX.value || 0);
        const offsetY = Number(activeFrame.position_y || 0) + Number(manualOffsetY.value || 0);
        const centerX = irisMidpoint.x + offsetX;
        const centerY = irisMidpoint.y + offsetY;
        const normalizedScale = Math.max(0.01, Math.max(irisDistance * 2.7, eyeDistance * 1.8) / 310) * baseScale * userScale;

        glbModelRoot.visible = true;
        glbModelRoot.position.set(centerX - (canvas.width / 2), (canvas.height / 2) - centerY, 0);
        glbModelRoot.rotation.set(0.08, -turnRatio * 1.8, -angle);
        glbModelRoot.scale.setScalar(normalizedScale * 24);
        renderGlbScene();
        return true;
    }

    function initGlbScene() {
        if (!window.THREE || !glbCanvas || typeof THREE.WebGLRenderer !== 'function') {
            glbRuntimeEnabled = false;
            return false;
        }

        const LoaderCtor = window.GLTFLoader || THREE.GLTFLoader;
        if (typeof LoaderCtor !== 'function') {
            glbRuntimeEnabled = false;
            return false;
        }

        try {
            glbRenderer = new THREE.WebGLRenderer({
                canvas: glbCanvas,
                alpha: true,
                antialias: true,
                preserveDrawingBuffer: true,
            });
            glbRenderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

            glbScene = new THREE.Scene();
            glbCamera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.1, 5000);
            glbCamera.position.set(0, 0, 1200);
            glbCamera.lookAt(0, 0, 0);

            const ambientLight = new THREE.AmbientLight(0xffffff, 1.55);
            const keyLight = new THREE.DirectionalLight(0xffffff, 1.1);
            keyLight.position.set(180, 140, 320);
            const fillLight = new THREE.DirectionalLight(0xdbeafe, 0.55);
            fillLight.position.set(-200, -120, 260);

            glbLights = [ambientLight, keyLight, fillLight];
            glbLights.forEach((light) => glbScene.add(light));

            glbLoader = new LoaderCtor();
            glbRuntimeEnabled = true;
            resizeCanvas();
            renderGlbScene();
            return true;
        } catch (error) {
            console.warn('GLB try-on disabled because 3D initialization failed.', error);
            glbRuntimeEnabled = false;
            glbLoader = null;
            glbRenderer = null;
            glbScene = null;
            glbCamera = null;
            glbModelRoot = null;
            return false;
        }
    }

    async function warmActiveFrameAssets() {
        const frame = getActiveFrame();
        if (!frame) {
            return;
        }

        await Promise.all([
            preloadImage(frame.front_asset),
            preloadImage(frame.left_asset),
            preloadImage(frame.right_asset),
        ]);
        await syncActiveGlbModel();
    }

    function populateFramePicker() {
        framePicker.innerHTML = '';
        tryOnFrames.forEach((frame, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = `${frame.name}${frame.brand ? ' - ' + frame.brand : ''}`;
            framePicker.appendChild(option);
        });
        framePicker.value = String(activeFrameIndex);
        syncActiveFramePanel();
    }

    async function selectFrame(index) {
        activeFrameIndex = Math.max(0, Math.min(index, tryOnFrames.length - 1));
        framePicker.value = String(activeFrameIndex);
        syncActiveFramePanel();
        document.querySelectorAll('.tryon-frame-card').forEach((button) => {
            button.classList.toggle('btn-primary', Number(button.dataset.frameIndex) === activeFrameIndex);
            button.classList.toggle('btn-outline-secondary', Number(button.dataset.frameIndex) !== activeFrameIndex);
        });
        await warmActiveFrameAssets();
    }

    function normalizeFrameAngle(angle) {
        if (angle > Math.PI / 2) {
            return angle - Math.PI;
        }
        if (angle < -Math.PI / 2) {
            return angle + Math.PI;
        }
        return angle;
    }

    function drawFrameAsset(image, leftEyeOuter, rightEyeOuter, irisLeft, irisRight, irisMidpoint, upperLidMidpoint, lowerLidMidpoint, templeLeft, templeRight, activeFrame, assetMode) {
        if (!image) {
            return;
        }

        const eyeDistance = Math.hypot(rightEyeOuter.x - leftEyeOuter.x, rightEyeOuter.y - leftEyeOuter.y);
        const irisDistance = Math.max(1, Math.hypot(irisRight.x - irisLeft.x, irisRight.y - irisLeft.y));
        const eyeHeight = Math.max(1, Math.abs(lowerLidMidpoint.y - upperLidMidpoint.y));
        const faceWidth = Math.max(
            eyeDistance * 1.9,
            Math.hypot(templeRight.x - templeLeft.x, templeRight.y - templeLeft.y)
        );
        const rawAngle = Math.atan2(rightEyeOuter.y - leftEyeOuter.y, rightEyeOuter.x - leftEyeOuter.x);
        const angle = normalizeFrameAngle(rawAngle);
        const baseScale = Number(activeFrame.scale || 1);
        const userScale = Number(manualScale.value || 1);
        const offsetX = Number(activeFrame.position_x || 0) + Number(manualOffsetX.value || 0);
        const offsetY = Number(activeFrame.position_y || 0) + Number(manualOffsetY.value || 0);

        let width = Math.max(irisDistance * 2.7, eyeDistance * 2.05) * baseScale * userScale;
        let rotation = angle;
        let centerX = irisMidpoint.x + offsetX;
        let centerY = irisMidpoint.y + (eyeHeight * 0.18) + offsetY;

        if (assetMode === 'left side' || assetMode === 'right side') {
            const sideDirection = assetMode === 'left side' ? -1 : 1;
            width = faceWidth * 1.12 * baseScale * userScale;
            rotation = angle * 0.55;
            centerX += sideDirection * (faceWidth * 0.18);
            centerY += eyeHeight * 0.1;
        }

        const height = width * (image.height / image.width);

        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.rotate(rotation);
        ctx.drawImage(image, -width / 2, -height / 2, width, height);
        ctx.restore();
    }

    async function onResults(results) {
        resizeCanvas();
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!results.image) {
            return;
        }

        if (currentFacingMode === 'user') {
            ctx.save();
            ctx.scale(-1, 1);
            ctx.drawImage(results.image, -canvas.width, 0, canvas.width, canvas.height);
            ctx.restore();
        } else {
            ctx.drawImage(results.image, 0, 0, canvas.width, canvas.height);
        }

        if (!results.multiFaceLandmarks || !results.multiFaceLandmarks.length) {
            if (glbModelRoot) {
                glbModelRoot.visible = false;
                renderGlbScene();
            }
            trackingStatus.textContent = 'Face not detected yet. Center your face in the frame.';
            return;
        }

        const landmarks = results.multiFaceLandmarks[0];
        const leftEyeOuter = getPoint(landmarks[33]);
        const rightEyeOuter = getPoint(landmarks[263]);
        const noseBridge = getPoint(landmarks[168]);
        const noseTip = getPoint(landmarks[1]);
        const irisLeft = getAveragePoint(landmarks, [468, 469, 470, 471, 472]);
        const irisRight = getAveragePoint(landmarks, [473, 474, 475, 476, 477]);
        const irisMidpoint = {
            x: (irisLeft.x + irisRight.x) / 2,
            y: (irisLeft.y + irisRight.y) / 2,
        };
        const upperLidMidpoint = {
            x: (getPoint(landmarks[159]).x + getPoint(landmarks[386]).x) / 2,
            y: (getPoint(landmarks[159]).y + getPoint(landmarks[386]).y) / 2,
        };
        const lowerLidMidpoint = {
            x: (getPoint(landmarks[145]).x + getPoint(landmarks[374]).x) / 2,
            y: (getPoint(landmarks[145]).y + getPoint(landmarks[374]).y) / 2,
        };
        const templeLeft = getPoint(landmarks[127]);
        const templeRight = getPoint(landmarks[356]);
        const midpointX = (leftEyeOuter.x + rightEyeOuter.x) / 2;
        const eyeDistance = Math.max(1, Math.abs(rightEyeOuter.x - leftEyeOuter.x));
        const turnRatio = (noseTip.x - midpointX) / eyeDistance;
        const activeFrame = getActiveFrame();
        const usingGlb = Boolean(activeFrame.glb_asset && glbModelRoot);

        if (usingGlb && updateGlbModelPose(leftEyeOuter, rightEyeOuter, irisLeft, irisRight, irisMidpoint, turnRatio, activeFrame)) {
            trackingStatus.textContent = 'Tracking live face. Using 3D GLB frame model.';
            return;
        }

        if (glbModelRoot) {
            glbModelRoot.visible = false;
            renderGlbScene();
        }

        let selectedAssetUrl = activeFrame.front_asset;
        let assetMode = 'front';
        const prefersLeftAsset = currentFacingMode === 'user' ? turnRatio > 0.08 : turnRatio < -0.08;
        const prefersRightAsset = currentFacingMode === 'user' ? turnRatio < -0.08 : turnRatio > 0.08;

        if (prefersRightAsset && activeFrame.right_asset) {
            selectedAssetUrl = activeFrame.right_asset;
            assetMode = 'right side';
        } else if (prefersLeftAsset && activeFrame.left_asset) {
            selectedAssetUrl = activeFrame.left_asset;
            assetMode = 'left side';
        }

        const activeImage = await preloadImage(selectedAssetUrl);
        drawFrameAsset(activeImage, leftEyeOuter, rightEyeOuter, irisLeft, irisRight, irisMidpoint, upperLidMidpoint, lowerLidMidpoint, templeLeft, templeRight, activeFrame, assetMode);
        trackingStatus.textContent = `Tracking live face. Using ${assetMode} PNG frame asset.`;
    }

    async function initFaceMesh() {
        faceMesh = new FaceMesh({
            locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`,
        });

        faceMesh.setOptions({
            maxNumFaces: 1,
            refineLandmarks: true,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5,
        });

        faceMesh.onResults((results) => {
            onResults(results).catch((error) => {
                console.error('Try-on render error:', error);
            });
        });
    }

    async function loadCameras() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            stream.getTracks().forEach((track) => track.stop());
        } catch (error) {
            console.error('Camera permission warmup failed:', error);
        }

        const devices = await navigator.mediaDevices.enumerateDevices();
        const cameras = devices.filter((device) => device.kind === 'videoinput');
        cameraPicker.innerHTML = '';

        if (!cameras.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No camera detected';
            cameraPicker.appendChild(option);
            return;
        }

        cameras.forEach((device, index) => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Camera ${index + 1}`;
            cameraPicker.appendChild(option);
        });
    }

    async function startCamera(deviceId = '') {
        if (cameraController) {
            cameraController.stop();
            cameraController = null;
        }

        loadingState.style.display = 'block';
        trackingStatus.textContent = 'Starting camera...';

        const cameraConfig = {
            onFrame: async () => {
                if (faceMesh) {
                    await faceMesh.send({ image: video });
                }
            },
            width: 1280,
            height: 720,
        };

        if (deviceId) {
            cameraConfig.videoConstraints = {
                deviceId: { exact: deviceId },
            };
        } else {
            cameraConfig.facingMode = currentFacingMode;
        }

        cameraController = new Camera(video, cameraConfig);

        try {
            await cameraController.start();
            video.style.display = 'none';
            loadingState.style.display = 'none';
            trackingStatus.textContent = 'Camera ready. Look straight into the lens.';
        } catch (error) {
            console.error('Unable to start try-on camera:', error);
            loadingState.innerHTML = '<div class="text-center text-white px-4"><i class="fas fa-camera-slash fs-1 mb-3"></i><h5>Unable to access camera</h5><p class="mb-0 text-white-50">Please allow camera access and refresh the page.</p></div>';
            trackingStatus.textContent = 'Camera access failed.';
        }
    }

    function captureTryOn() {
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = `bealet-tryon-${Date.now()}.png`;
        link.click();
    }

    document.addEventListener('DOMContentLoaded', async () => {
        resizeCanvas();
        populateFramePicker();
        initGlbScene();

        try {
            const requestedFrameIndex = requestedFrameId
                ? tryOnFrames.findIndex((frame) => Number(frame.id) === Number(requestedFrameId))
                : -1;
            await selectFrame(requestedFrameIndex >= 0 ? requestedFrameIndex : 0);
        } catch (error) {
            console.warn('Frame asset preload failed. Continuing with camera startup.', error);
        }

        try {
            await initFaceMesh();
            await loadCameras();
            await startCamera();
        } catch (error) {
            console.error('Try-on camera bootstrap failed:', error);
            loadingState.innerHTML = '<div class="text-center text-white px-4"><i class="fas fa-camera-slash fs-1 mb-3"></i><h5>Unable to start virtual try-on</h5><p class="mb-0 text-white-50">Please refresh the page and allow camera access.</p></div>';
            trackingStatus.textContent = 'Try-on startup failed.';
        }

        framePicker.addEventListener('change', async (event) => {
            await selectFrame(Number(event.target.value || 0));
        });

        [manualOffsetX, manualOffsetY, manualScale].forEach((input) => {
            input.addEventListener('input', syncFineTuneLabels);
            input.addEventListener('change', syncFineTuneLabels);
        });

        cameraPicker.addEventListener('change', async (event) => {
            await startCamera(event.target.value || '');
        });

        flipCameraBtn.addEventListener('click', async () => {
            currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
            cameraPicker.value = '';
            await startCamera();
        });

        captureTryOnBtn.addEventListener('click', captureTryOn);
        resetTryOnTuningBtn.addEventListener('click', resetFineTuneControls);
        buyActiveFrameBtn.addEventListener('click', async () => {
            const activeFrame = getActiveFrame();
            await addToCart(Number(activeFrame.id), 1, 'checkout');
        });

        document.querySelectorAll('.tryon-frame-card').forEach((button) => {
            button.addEventListener('click', async () => {
                await selectFrame(Number(button.dataset.frameIndex || 0));
            });
        });

        tryOnStage.addEventListener('wheel', (event) => {
            if (event.target.closest('button, input, select, a')) {
                return;
            }
            event.preventDefault();
            const currentScale = Number(manualScale.value || 1);
            const nextScale = currentScale + (event.deltaY < 0 ? 0.04 : -0.04);
            applyFineTuneValue(manualScale, nextScale);
        }, { passive: false });

        tryOnStage.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button, input, select, a, label')) {
                return;
            }
            tryOnStage.setPointerCapture(event.pointerId);
            activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            updateGestureState();
        });

        tryOnStage.addEventListener('pointermove', (event) => {
            if (!activePointers.has(event.pointerId)) {
                return;
            }
            event.preventDefault();
            handleStagePointerMove(event);
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
            tryOnStage.addEventListener(eventName, handleStagePointerEnd);
        });

        syncFineTuneLabels();
        window.addEventListener('resize', resizeCanvas);
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>

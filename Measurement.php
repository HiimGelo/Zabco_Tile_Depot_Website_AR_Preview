<?php
require_once 'db_connect.php'; // Use PDO connection

$productId = isset($_GET['id']) ? $_GET['id'] : null;
$productImg = '';
$productSize = '';

if ($productId) {
    $tables = ['productsmedian', 'productssophisticated', 'productsluxurious'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT Image, Size FROM $table WHERE ProductID = ?");
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        if ($row) {
            if (!empty($row['Image'])) {
                $base64 = base64_encode($row['Image']);
                $productImg = 'data:image/jpeg;base64,' . $base64;
            }
            $productSize = $row['Size'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>AR Measurement Tool</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <style>
    body { margin: 0; overflow: hidden; font-family: sans-serif; background-color: #000; }

    /* The Container - This must be fixed/absolute to cover the screen */
    #ar-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        /* Always display:flex so it stays in the layout tree — Chrome's dom-overlay
           requires the root to be in the layout tree at session-request time.
           visibility:hidden hides it visually until the AR session starts. */
        display: flex;
        visibility: hidden;
        pointer-events: none; /* Let touches pass through to AR reticle */
        z-index: 10001 !important; 
    }

    /* ── Scanning indicator — visible immediately on session start ── */
    #scanning-indicator {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
        pointer-events: none;
    }
    #scanning-indicator .scan-ring {
        width: 120px; height: 120px;
        border-radius: 50%;
        border: 3px solid rgba(237,141,27,0.25);
        border-top-color: #ed8d1b;
        animation: spin 1.2s linear infinite;
    }
    #scanning-indicator .scan-text {
        color: rgba(255,255,255,0.85);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-align: center;
        text-shadow: 0 1px 4px rgba(0,0,0,0.8);
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .ui-container {
        padding-bottom: 40px;
        margin-top: 25%;
        display: flex;
        flex-direction: column;
        gap: 15px;
        align-items: center;
        width: 100%;
        pointer-events: none;
    }

    .ui-row {
        display: flex;
        gap: 20px;
        pointer-events: auto; /* Re-enable touch for buttons */
    }

    .ar-btn {
        background: rgba(20, 20, 20, 0.8);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 14px 22px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        backdrop-filter: blur(4px);
        cursor: pointer;
    }

    .primary {
        background: #ed8d1b;
        border-color: #ed8d1b;
    }

    #info-toast {
        position: absolute;
        top: 50px;
        background: rgba(0,0,0,0.5);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
    }

    /* ── Complete Measurement Modal ── */
    #complete-modal {
        display: none;
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.90);
        z-index: 30;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        padding: 28px 24px;
        pointer-events: auto;
        overflow-y: auto;
    }

    #complete-modal h3 {
        color: #ed8d1b;
        margin: 0 0 4px;
        font-size: 1.15rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-align: center;
    }

    #complete-modal p {
        color: rgba(255,255,255,0.65);
        font-size: 0.85rem;
        margin: 0;
        text-align: center;
    }

    #screenshot-preview {
        width: 85%;
        max-width: 340px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.06);
        display: none;
    }

    .complete-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 85%;
        max-width: 300px;
    }

    .complete-actions .ar-btn {
        width: 100%;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
</style>
</head>
<body>
    <div id="ar-overlay">
        <!-- Scanning ring — visible immediately when camera starts, before surface found -->
        <div id="scanning-indicator">
            <div class="scan-ring"></div>
            <div class="scan-text">Move your phone slowly<br>to scan the floor</div>
        </div>

        <div id="info-toast">Aim at the floor to find a surface</div>
        
        <div class="ui-container">
            <div id="confirm-row" class="ui-row" style="display: none;">
                <button class="ar-btn primary" id="btnConfirm">Confirm Measurement</button>
            </div>

            <div class="ui-row">
                <button class="ar-btn" id="btnUndo">↺</button>
                <button class="ar-btn primary" id="btnPlace">+</button>
                <button class="ar-btn" id="btnClear">x</button>
            </div>
        </div>

        <!-- ── Complete Measurement Modal (rendered inside AR overlay) ── -->
        <div id="complete-modal">
            <h3>Measurement Complete</h3>
            <p id="measurement-summary"></p>
            <img id="screenshot-preview" alt="AR Screenshot" />
            <p id="screenshot-hint" style="font-size:0.78rem;color:rgba(255,255,255,0.4);margin-top:-8px;display:none;">Screenshot not available on this device.</p>
            <div class="complete-actions">
                <button class="ar-btn" id="btnDownloadScreenshot">
                    📷 Download Screenshot
                </button>
                <button class="ar-btn primary" id="btnSendToEstimator">
                    Send to Estimator →
                </button>
                <button class="ar-btn" id="btnCancelComplete" style="opacity:0.7;">
                    ← Back to Measurement
                </button>
            </div>
        </div>
    </div>

    <script type="module">
        import * as THREE from 'https://unpkg.com/three@0.160.1/build/three.module.js';
        import { ARButton } from 'https://unpkg.com/three@0.160.1/examples/jsm/webxr/ARButton.js';

        const productImg = <?php echo json_encode($productImg); ?>;
        const productSize = <?php echo json_encode($productSize); ?>;

        function getQueryParam(name) {
            const params = new URLSearchParams(window.location.search);
            return params.get(name);
        }
        const productId = getQueryParam('id');

        let camera, scene, renderer, reticle;
        const placedPoints = [];
        const placedLines = [];
        const distanceLabels = [];
        let tileMesh = null;

        let hitTestSource = null;
        let hitTestSourceRequested = false;

        const pointGeometry = new THREE.SphereGeometry(0.01, 16, 16);
        const pointMaterial = new THREE.MeshBasicMaterial({ color: 0xed8d1b });

        const SNAP_DISTANCE = 0.03;

        init();
        animate();

        function init() {
            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(70, window.innerWidth / window.innerHeight, 0.01, 20);

            // preserveDrawingBuffer: true is required for screenshot capture
            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.xr.enabled = true;
            document.body.appendChild(renderer.domElement);

            // Setup AR Button with DOM Overlay for proper Android support
            const arOverlay = document.getElementById('ar-overlay');
            const arButton = ARButton.createButton(renderer, { 
                requiredFeatures: ['hit-test'],
                optionalFeatures: ['dom-overlay'],
                domOverlay: { root: arOverlay }
            });
            document.body.appendChild(arButton);

            renderer.xr.addEventListener('sessionstart', () => {
                // Make overlay visible — camera feed starts immediately on Android
                arOverlay.style.visibility = 'visible';
            });
            renderer.xr.addEventListener('sessionend', () => {
                arOverlay.style.visibility = 'hidden';
                // Reset hit-test state so re-entering AR works correctly
                hitTestSource = null;
                hitTestSourceRequested = false;
                clearAllMeasurements();
                closeCompleteModal();
                // Restore scanning indicator for next session
                const scanEl = document.getElementById('scanning-indicator');
                if (scanEl) scanEl.style.display = 'flex';
            });

            const light = new THREE.HemisphereLight(0xffffff, 0xbbbbff, 1);
            scene.add(light);

            const reticleGeometry = new THREE.RingGeometry(0.05, 0.06, 32).rotateX(-Math.PI / 2);
            const reticleMaterial = new THREE.MeshBasicMaterial({ color: 0xffffff });
            reticle = new THREE.Mesh(reticleGeometry, reticleMaterial);
            reticle.matrixAutoUpdate = false;
            reticle.visible = false;
            
            const dotGeometry = new THREE.SphereGeometry(0.005, 16, 16);
            const dotMaterial = new THREE.MeshBasicMaterial({ color: 0xed8d1b });
            const centerDot = new THREE.Mesh(dotGeometry, dotMaterial);
            reticle.add(centerDot);
            scene.add(reticle);

            window.addEventListener('resize', onWindowResize);

            // Wire up DOM buttons
            document.getElementById('btnPlace').addEventListener('click', placePoint);
            document.getElementById('btnUndo').addEventListener('click', undoLastMeasurement);
            document.getElementById('btnClear').addEventListener('click', clearAllMeasurements);

            // Confirm now shows the completion modal
            document.getElementById('btnConfirm').addEventListener('click', showCompleteModal);

            // Complete modal buttons
            document.getElementById('btnDownloadScreenshot').addEventListener('click', downloadScreenshot);
            document.getElementById('btnSendToEstimator').addEventListener('click', sendMeasurements);
            document.getElementById('btnCancelComplete').addEventListener('click', closeCompleteModal);
        }

        function onWindowResize() {
            renderer.setSize(window.innerWidth, window.innerHeight);
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
        }

        /* ── Complete Modal ── */
        function showCompleteModal() {
            if (placedPoints.length < 3) return;

            const { length, width } = calculateSizes();
            const lFixed = parseFloat(length.toFixed(2));
            const wFixed = parseFloat(width.toFixed(2));

            // Update summary text
            const summary = document.getElementById('measurement-summary');
            if (summary) summary.textContent = `Length: ${lFixed} m  ·  Width: ${wFixed} m`;

            // Attempt screenshot capture
            const preview   = document.getElementById('screenshot-preview');
            const dlBtn     = document.getElementById('btnDownloadScreenshot');
            const noSsHint  = document.getElementById('screenshot-hint');
            let screenshotDataUrl = null;

            try {
                screenshotDataUrl = renderer.domElement.toDataURL('image/png');
                // A blank/tiny data URL is ~100 chars; a real image is much larger
                if (!screenshotDataUrl || screenshotDataUrl.length < 1000) {
                    screenshotDataUrl = null;
                }
            } catch (e) {
                screenshotDataUrl = null;
            }

            if (screenshotDataUrl) {
                preview.src = screenshotDataUrl;
                preview.style.display = 'block';
                dlBtn.dataset.screenshotUrl = screenshotDataUrl;
                dlBtn.style.display = 'flex';
                if (noSsHint) noSsHint.style.display = 'none';
            } else {
                preview.style.display = 'none';
                dlBtn.style.display = 'none';
                if (noSsHint) noSsHint.style.display = 'block';
            }

            document.getElementById('complete-modal').style.display = 'flex';
        }

        function closeCompleteModal() {
            document.getElementById('complete-modal').style.display = 'none';
        }

        /* ── Screenshot Download ── */
        /* Priority order:
           1. Web Share API with file  → iOS 15+ saves directly to Camera Roll
           2. Blob-URL <a download>    → Android / desktop
           3. window.open(_blank)      → older iOS Safari (user taps hold → Save)  */
        async function downloadScreenshot() {
            const url = document.getElementById('btnDownloadScreenshot').dataset.screenshotUrl;
            if (!url) return;

            // Build a Blob from the data-URL (works for all paths below)
            const parts    = url.split(',');
            const mime     = parts[0].split(':')[1].split(';')[0];
            const byteStr  = atob(parts[1]);
            const ab       = new ArrayBuffer(byteStr.length);
            const ia       = new Uint8Array(ab);
            for (let i = 0; i < byteStr.length; i++) ia[i] = byteStr.charCodeAt(i);
            const blob     = new Blob([ab], { type: mime });
            const fileName = 'ar-measurement-' + Date.now() + '.png';

            // ── Path 1: Web Share API (iOS 15+, requires user gesture ✓) ──
            if (navigator.share && navigator.canShare) {
                try {
                    const file = new File([blob], fileName, { type: mime });
                    if (navigator.canShare({ files: [file] })) {
                        await navigator.share({
                            files: [file],
                            title: 'AR Measurement Screenshot'
                        });
                        return; // Share sheet handled it — we're done
                    }
                } catch (shareErr) {
                    // AbortError = user tapped Cancel on the share sheet — respect that
                    if (shareErr && shareErr.name === 'AbortError') return;
                    // Any other error: fall through to Blob URL path
                }
            }

            // ── Path 2: Blob URL + <a download> (Android / desktop) ──
            try {
                const blobUrl = URL.createObjectURL(blob);
                const a       = document.createElement('a');
                a.href        = blobUrl;
                a.download    = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(() => URL.revokeObjectURL(blobUrl), 3000);
                return;
            } catch (dlErr) {
                // Fall through to final fallback
            }

            // ── Path 3: Open in new tab — iOS Safari (user: hold → Save to Photos) ──
            try { window.open(url, '_blank'); } catch (e) {}
        }

        function createLabelSprite(text) {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            const fontSize = 48;
            const paddingX = 24;
            const paddingY = 16;

            context.font = `bold ${fontSize}px Arial, sans-serif`;
            const textMetrics = context.measureText(text);
            
            canvas.width = textMetrics.width + paddingX * 2;
            canvas.height = fontSize + paddingY * 2;

            context.fillStyle = '#00000099';
            context.beginPath();
            if (context.roundRect) {
                context.roundRect(0, 0, canvas.width, canvas.height, 65);
            } else {
                context.rect(0, 0, canvas.width, canvas.height);
            }
            context.fill();

            context.strokeStyle = '#ffffffcc';
            context.lineWidth = 3;
            context.stroke();

            context.font = `bold ${fontSize}px Arial, sans-serif`;
            context.fillStyle = 'white';
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(text, canvas.width / 2, (canvas.height / 2) + 4);

            const texture = new THREE.CanvasTexture(canvas);
            texture.minFilter = THREE.LinearFilter;
            texture.needsUpdate = true;

            const material = new THREE.SpriteMaterial({
                map: texture,
                depthTest: false,
                depthWrite: false,
                transparent: true
            });

            const sprite = new THREE.Sprite(material);
            const aspectRatio = canvas.width / canvas.height;
            const spriteHeight = 0.08;
            sprite.scale.set(spriteHeight * aspectRatio, spriteHeight, 1);

            sprite.onBeforeRender = function(renderer, scene, camera) {
                sprite.quaternion.copy(camera.quaternion);
            };

            return sprite;
        }

        function updateTilePreview() {
            if (tileMesh) {
                scene.remove(tileMesh);
                tileMesh.geometry.dispose();
                tileMesh.material.map?.dispose();
                tileMesh.material.dispose();
                tileMesh = null;
            }
            
            const confirmRow = document.getElementById('confirm-row');
            if (placedPoints.length < 3) {
                confirmRow.style.display = 'none';
                return;
            }

            confirmRow.style.display = 'flex';

            const p0 = placedPoints[0].position.clone();
            const p1 = placedPoints[1].position.clone();
            const p2 = placedPoints[2].position.clone();

            const v1 = new THREE.Vector3().subVectors(p1, p0);
            const v2 = new THREE.Vector3().subVectors(p2, p0);
            const normal = new THREE.Vector3().crossVectors(v1, v2).normalize();

            const xAxis = v1.clone().normalize();
            const zAxis = normal.clone().normalize();
            const yAxis = new THREE.Vector3().crossVectors(zAxis, xAxis).normalize();

            const basis = new THREE.Matrix4().makeBasis(xAxis, yAxis, zAxis);
            const inverseBasis = new THREE.Matrix4().copy(basis).invert();

            const localPoints = placedPoints.map(p => p.position.clone().sub(p0).applyMatrix4(inverseBasis));

            const indices = [];
            for (let i = 1; i < localPoints.length - 1; i++) {
                indices.push(0, i, i + 1);
            }

            const geometry = new THREE.BufferGeometry();
            const positions = [];
            const uvs = [];

            localPoints.forEach(v => {
                positions.push(v.x, v.y, v.z);
                uvs.push(v.x, v.y);
            });

            geometry.setIndex(indices);
            geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
            geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
            geometry.computeVertexNormals();

            const loader = new THREE.TextureLoader();
            const texture = loader.load(productImg || 'noImage.png');
            texture.wrapS = texture.wrapT = THREE.RepeatWrapping;

            let sizeX, sizeY;
            if (productSize && /[xX]/.test(productSize)) {
                const parts = productSize.split(/[xX]/).map(Number);
                if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                    sizeX = parts[0];
                    sizeY = parts[1];
                }
            }
            if (typeof sizeX === 'number' && typeof sizeY === 'number') {
                const bbox = new THREE.Box3().setFromPoints(localPoints);
                const size = new THREE.Vector3();
                bbox.getSize(size);
                const repeatX = (size.x * 100) / sizeX;
                const repeatY = (size.y * 100) / sizeY;
                texture.repeat.set(repeatX > 1 ? repeatX : 1, repeatY > 1 ? repeatY : 1);
            }

            const material = new THREE.MeshBasicMaterial({
                map: texture,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.9
            });

            tileMesh = new THREE.Mesh(geometry, material);
            tileMesh.setRotationFromMatrix(basis);
            tileMesh.position.copy(p0).addScaledVector(normal, 0.001);

            scene.add(tileMesh);
        }

        function placePoint() {
            if (reticle.visible) {
                const point = new THREE.Mesh(pointGeometry, pointMaterial);
                point.position.setFromMatrixPosition(reticle.matrix);
                scene.add(point);
                placedPoints.push(point);

                if (placedPoints.length >= 2) {
                    const p1 = placedPoints[placedPoints.length - 2].position;
                    const p2 = placedPoints[placedPoints.length - 1].position;

                    const lineGeo = new THREE.BufferGeometry().setFromPoints([p1, p2]);
                    const lineMat = new THREE.LineBasicMaterial({ color: 0xffffff, linewidth: 4 });
                    const line = new THREE.Line(lineGeo, lineMat);
                    scene.add(line);
                    placedLines.push(line);

                    const distance = p1.distanceTo(p2);
                    const labelText = `${distance.toFixed(2)} m`;
                    const label = createLabelSprite(labelText);

                    const midpoint = new THREE.Vector3().addVectors(p1, p2).multiplyScalar(0.5);
                    label.position.copy(midpoint).add(new THREE.Vector3(0, 0.05, 0));

                    scene.add(label);
                    distanceLabels.push(label);
                }
                updateTilePreview();
            } else {
                document.getElementById('info-toast').innerText = "Look around to find a surface first.";
                setTimeout(() => {
                    document.getElementById('info-toast').innerText = "Aim at the floor to find a surface";
                }, 2000);
            }
        }

        function undoLastMeasurement() {
            if (distanceLabels.length > 0) {
                const lastLabel = distanceLabels.pop();
                scene.remove(lastLabel);
                lastLabel.material.map.dispose();
                lastLabel.material.dispose();
            }
            if (placedLines.length > 0) {
                const lastLine = placedLines.pop();
                scene.remove(lastLine);
                lastLine.geometry.dispose();
                lastLine.material.dispose();
            }
            if (placedPoints.length > 0) {
                const lastPoint = placedPoints.pop();
                scene.remove(lastPoint);
                lastPoint.geometry.dispose();
                lastPoint.material.dispose();
            }
            updateTilePreview();
        }

        function clearAllMeasurements() {
            distanceLabels.forEach(label => {
                scene.remove(label);
                label.material.map.dispose();
                label.material.dispose();
            });
            distanceLabels.length = 0;

            placedLines.forEach(line => {
                scene.remove(line);
                line.geometry.dispose();
                line.material.dispose();
            });
            placedLines.length = 0;

            placedPoints.forEach(point => {
                scene.remove(point);
                point.geometry.dispose();
                point.material.dispose();
            });
            placedPoints.length = 0;

            if (tileMesh) {
                scene.remove(tileMesh);
                tileMesh.geometry.dispose();
                tileMesh.material.map?.dispose();
                tileMesh.material.dispose();
                tileMesh = null;
            }
            document.getElementById('confirm-row').style.display = 'none';
        }

        function calculateSizes() {
            if (placedPoints.length < 2) return { length: 0, width: 0 };
            const edges = [];
            for (let i = 0; i < placedPoints.length; i++) {
                const a = placedPoints[i].position;
                const b = placedPoints[(i + 1) % placedPoints.length].position;
                edges.push(a.distanceTo(b));
            }
            const unique = [];
            const tol = 0.01;
            edges.forEach(e => {
                if (!unique.some(u => Math.abs(u - e) < tol)) unique.push(e);
            });
            unique.sort((a, b) => b - a);
            return {
                length: unique[0] || 0,
                width: unique[1] || 0
            };
        }

        function sendMeasurements() {
            if (placedPoints.length < 3) return;

            const { length, width } = calculateSizes();
            const lengthFixed = parseFloat(length.toFixed(2));
            const widthFixed  = parseFloat(width.toFixed(2));

            const params  = new URLSearchParams(location.search);
            const session = params.get('session');
            const product = params.get('product') || productId || null;

            // ── Desktop popup: send via postMessage then close ──
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'ar-measurement',
                        length: lengthFixed,
                        width: widthFixed,
                        productId: product ? Number(product) : null,
                        session: session || null
                    }, '*');
                    try { window.close(); } catch(e) {}
                    return;
                }
            } catch (err) {}

            // ── Build return URL (used by both paths below) ──
            const returnUrl = product
                ? `ProductDetails.php?id=${encodeURIComponent(product)}&length=${encodeURIComponent(lengthFixed)}&width=${encodeURIComponent(widthFixed)}`
                : null;

            // ── No session: direct URL redirect ──
            if (!session) {
                if (returnUrl) window.location.href = returnUrl;
                return;
            }

            // ── Session present: POST to ar-submit, then redirect (mobile) ──
            const endpoint = location.origin + '/ar-submit.php';
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session:   session,
                    length:    lengthFixed,
                    width:     widthFixed,
                    productId: product ? Number(product) : null
                })
            })
            .then(r => r.json().catch(() => { throw new Error('Invalid JSON response'); }))
            .then(res => {
                // Attempt to close if opened as a popup
                try { window.close(); } catch(e) {}
                // Redirect back (runs on mobile where window.close() is blocked)
                setTimeout(() => {
                    if (returnUrl) window.location.href = returnUrl;
                }, 300);
            })
            .catch(err => {
                // Even on network error, redirect with URL params as fallback
                if (returnUrl) window.location.href = returnUrl;
            });
        }

        function animate() {
            renderer.setAnimationLoop((timestamp, frame) => {
                if (frame) {
                    const referenceSpace = renderer.xr.getReferenceSpace();
                    const session = renderer.xr.getSession();

                    if (!hitTestSourceRequested) {
                        session.requestReferenceSpace('viewer').then((refSpace) => {
                            session.requestHitTestSource({ space: refSpace }).then((source) => {
                                hitTestSource = source;
                            });
                        });
                        hitTestSourceRequested = true;
                    }

                    if (hitTestSource) {
                        const hitTestResults = frame.getHitTestResults(hitTestSource);
                        if (hitTestResults.length > 0) {
                            const hit = hitTestResults[0];
                            const pose = hit.getPose(referenceSpace);

                            reticle.visible = true;
                            reticle.matrix.fromArray(pose.transform.matrix);
                            document.getElementById('info-toast').style.display = 'none';
                            // Hide scanning indicator once we have a surface
                            const scanEl = document.getElementById('scanning-indicator');
                            if (scanEl && scanEl.style.display !== 'none') scanEl.style.display = 'none';

                            const reticlePos = new THREE.Vector3().setFromMatrixPosition(reticle.matrix);
                            let closestPoint = null;
                            let minDistance = Infinity;

                            placedPoints.forEach((point) => {
                                const dist = reticlePos.distanceTo(point.position);
                                if (dist < SNAP_DISTANCE && dist < minDistance) {
                                    closestPoint = point;
                                    minDistance = dist;
                                }
                            });

                            if (closestPoint) {
                                reticle.position.copy(closestPoint.position);
                                reticle.matrix.identity();
                                reticle.matrix.setPosition(closestPoint.position);
                            }
                        } else {
                            reticle.visible = false;
                            if (placedPoints.length === 0) {
                                document.getElementById('info-toast').style.display = 'block';
                            }
                        }
                    }
                }
                renderer.render(scene, camera);
            });
        }
    </script>
</body>
</html>
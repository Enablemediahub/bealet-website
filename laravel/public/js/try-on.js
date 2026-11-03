(() => {
    'use strict';

    const frames = window.BEALET_TRYON_FRAMES || [];
    if (!frames.length) return;

    const video = document.getElementById('tryon-video');
    const canvas = document.getElementById('tryon-canvas');
    const context = canvas.getContext('2d');
    const status = document.getElementById('tryon-status');
    const startButton = document.getElementById('tryon-start');
    const flipButton = document.getElementById('tryon-flip');
    const captureButton = document.getElementById('tryon-capture');
    const cameraPicker = document.getElementById('tryon-camera');
    const framePicker = document.getElementById('tryon-frame');
    const zoomSlider = document.getElementById('tryon-zoom');
    const zoomValue = document.getElementById('tryon-zoom-value');
    const modelViewer = document.getElementById('tryon-model');
    let activeFrameIndex = Number(window.BEALET_TRYON_SELECTED) || 0;
    let facingMode = 'user';
    let stream = null;
    let faceMesh = null;
    let animationId = 0;
    let processing = false;
    let cameraDevices = [];
    let isDrawing = false;
    let activeZoom = 1;
    let pinchStartDistance = 0;
    let pinchStartZoom = 1;
    let manualOffsetX = 0;
    let manualOffsetY = 0;
    let overlayReady = false;
    let draggingPointerId = null;
    let dragStartPointer = null;
    let dragStartOffset = null;
    const activePointers = new Map();
    const imageCache = new Map();
    const stage = document.getElementById('tryon-stage');

    const setStatus = (message) => {
        if (status.textContent !== message) status.textContent = message;
    };

    function loadImage(source) {
        if (!source) return Promise.resolve(null);
        if (imageCache.has(source)) return imageCache.get(source);
        const promise = new Promise((resolve) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => resolve(null);
            image.crossOrigin = 'anonymous';
            image.src = source;
        });
        imageCache.set(source, promise);
        return promise;
    }

    function setFrame(index) {
        activeFrameIndex = Math.max(0, Math.min(Number(index), frames.length - 1));
        const frame = frames[activeFrameIndex];
        manualOffsetX = 0;
        manualOffsetY = 0;
        overlayReady = false;
        document.getElementById('tryon-product-image').src = frame.image;
        document.getElementById('tryon-product-image').alt = frame.name;
        document.getElementById('tryon-product-name').textContent = frame.name;
        document.getElementById('tryon-product-brand').textContent = frame.brand || '';
        document.getElementById('tryon-product-link').href = `${window.BASE_URL}/shop/${encodeURIComponent(frame.slug || frame.id)}`;
        if (frame.model && !frame.front) {
            modelViewer.src = frame.model;
            modelViewer.style.display = 'block';
        } else {
            modelViewer.removeAttribute('src');
            modelViewer.style.display = 'none';
        }
        loadImage(frame.front);
        loadImage(frame.left);
        loadImage(frame.right);
        if (stream) setStatus(`Selected ${frame.name}. Center your face in the camera.`);
    }

    function setZoom(value) {
        const minimum = Number(zoomSlider.min);
        const maximum = Number(zoomSlider.max);
        const step = Number(zoomSlider.step);
        const bounded = Math.min(maximum, Math.max(minimum, Number(value)));
        activeZoom = minimum + Math.round((bounded - minimum) / step) * step;
        activeZoom = Number(activeZoom.toFixed(2));
        zoomSlider.value = String(activeZoom);
        zoomValue.value = `${Math.round(activeZoom * 100)}%`;
        zoomValue.textContent = zoomValue.value;
    }

    function faceAngle(leftEye, rightEye) {
        let angle = Math.atan2(rightEye.y - leftEye.y, rightEye.x - leftEye.x);
        if (angle > Math.PI / 2) angle -= Math.PI;
        if (angle < -Math.PI / 2) angle += Math.PI;
        return angle;
    }

    function point(landmarks, index) {
        const landmark = landmarks[index];
        const x = landmark.x * canvas.width;
        return { x: facingMode === 'user' ? canvas.width - x : x, y: landmark.y * canvas.height };
    }

    async function renderResults(results) {
        if (!results.image || !video.videoWidth) return;
        const width = video.videoWidth;
        const height = video.videoHeight;
        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }
        context.clearRect(0, 0, width, height);
        if (facingMode === 'user') {
            context.save();
            context.translate(width, 0);
            context.scale(-1, 1);
            context.drawImage(results.image, 0, 0, width, height);
            context.restore();
        } else {
            context.drawImage(results.image, 0, 0, width, height);
        }

        const landmarks = results.multiFaceLandmarks?.[0];
        if (!landmarks) {
            overlayReady = false;
            modelViewer.style.display = 'none';
            setStatus('No face detected. Face the camera and move into good lighting.');
            return;
        }
        const leftEye = point(landmarks, 33);
        const rightEye = point(landmarks, 263);
        const nose = point(landmarks, 1);
        const eyeDistance = Math.hypot(rightEye.x - leftEye.x, rightEye.y - leftEye.y);
        const centerX = (leftEye.x + rightEye.x) / 2;
        const centerY = (leftEye.y + rightEye.y) / 2;
        const angle = faceAngle(leftEye, rightEye);
        const turn = (nose.x - centerX) / Math.max(1, eyeDistance);
        const frame = frames[activeFrameIndex];
        const useLeft = facingMode === 'user' ? turn > 0.08 : turn < -0.08;
        const useRight = facingMode === 'user' ? turn < -0.08 : turn > 0.08;
        const asset = (useLeft && frame.left) ? frame.left : ((useRight && frame.right) ? frame.right : frame.front);
        const image = await loadImage(asset);

        if (image && !isDrawing) {
            overlayReady = true;
            isDrawing = true;
            const scale = Number(frame.scale || 1);
            const drawWidth = eyeDistance * 2.05 * scale * activeZoom;
            const drawHeight = drawWidth * image.height / image.width;
            const x = centerX + Number(frame.position_x || 0) + manualOffsetX * width;
            const y = centerY + Number(frame.position_y || 0) + manualOffsetY * height + drawHeight * 0.12;
            context.save();
            context.translate(x, y);
            context.rotate(angle);
            context.drawImage(image, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
            context.restore();
            isDrawing = false;
            modelViewer.style.display = 'none';
            setStatus(`Tracking your face · ${frame.name}`);
        } else if (frame.model && !frame.front) {
            overlayReady = true;
            const bounds = stage.getBoundingClientRect();
            modelViewer.style.left = `${(centerX / width + manualOffsetX) * bounds.width}px`;
            modelViewer.style.top = `${(centerY / height + manualOffsetY) * bounds.height}px`;
            modelViewer.style.width = `${(eyeDistance * 2.4 * activeZoom / width) * bounds.width}px`;
            modelViewer.style.height = `${(eyeDistance * 1.3 * activeZoom / height) * bounds.height}px`;
            modelViewer.style.transform = `translate(-50%, -40%) rotate(${angle}rad)`;
            modelViewer.style.display = 'block';
            setStatus(`Tracking your face · ${frame.name} 3D model`);
        } else {
            overlayReady = false;
            setStatus(`Face detected, but the try-on image for ${frame.name} could not be loaded.`);
        }
    }

    async function startCamera(deviceId = '') {
        stopCamera();
        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus('Camera access is not available in this browser. Use a current browser over HTTPS or localhost.');
            return;
        }
        if (typeof window.FaceMesh !== 'function') {
            setStatus('Face-tracking resources did not load. Check your connection and reload the page.');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
            });
            video.srcObject = stream;
            await video.play();
            faceMesh = new FaceMesh({ locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh@0.4.1633559619/${file}` });
            faceMesh.setOptions({ maxNumFaces: 1, refineLandmarks: true, minDetectionConfidence: 0.55, minTrackingConfidence: 0.55 });
            faceMesh.onResults((result) => { renderResults(result).catch(() => setStatus('The try-on preview could not be rendered. Restart the camera and try again.')); });
            cameraDevices = (await navigator.mediaDevices.enumerateDevices()).filter((device) => device.kind === 'videoinput');
            cameraPicker.replaceChildren(...cameraDevices.map((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.textContent = device.label || `Camera ${index + 1}`;
                return option;
            }));
            cameraPicker.disabled = cameraDevices.length < 2;
            flipButton.disabled = cameraDevices.length < 2;
            captureButton.disabled = false;
            startButton.textContent = 'Stop camera';
            setStatus('Camera ready. Center your face in the preview.');
            const frame = async () => {
                if (!stream?.active || processing || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                    animationId = requestAnimationFrame(frame);
                    return;
                }
                processing = true;
                try { await faceMesh.send({ image: video }); }
                finally { processing = false; }
                animationId = requestAnimationFrame(frame);
            };
            animationId = requestAnimationFrame(frame);
        } catch (error) {
            stopCamera();
            const message = error?.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access in your browser settings and try again.'
                : error?.name === 'NotFoundError'
                    ? 'No camera was found on this device.'
                    : error?.name === 'NotReadableError'
                        ? 'The camera is busy in another application. Close that app and try again.'
                        : 'The camera could not be started. Check browser permissions and try again.';
            setStatus(message);
        }
    }

    function stopCamera() {
        cancelAnimationFrame(animationId);
        animationId = 0;
        overlayReady = false;
        activePointers.clear();
        draggingPointerId = null;
        dragStartPointer = null;
        dragStartOffset = null;
        stage.classList.remove('is-dragging');
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        faceMesh?.close();
        faceMesh = null;
        processing = false;
        startButton.textContent = 'Enable camera';
        flipButton.disabled = true;
        cameraPicker.disabled = true;
        captureButton.disabled = true;
    }

    framePicker.addEventListener('change', () => setFrame(framePicker.value));
    startButton.addEventListener('click', () => {
        if (stream?.active) {
            stopCamera();
            context.clearRect(0, 0, canvas.width, canvas.height);
            modelViewer.style.display = 'none';
            setStatus('Camera stopped and preview cleared.');
            return;
        }
        startCamera(cameraPicker.value);
    });
    flipButton.addEventListener('click', () => {
        facingMode = facingMode === 'user' ? 'environment' : 'user';
        startCamera();
    });
    cameraPicker.addEventListener('change', () => cameraPicker.value && startCamera(cameraPicker.value));
    zoomSlider.addEventListener('input', () => setZoom(zoomSlider.value));
    stage.addEventListener('wheel', (event) => {
        event.preventDefault();
        setZoom(activeZoom * (event.deltaY < 0 ? 1.08 : 1 / 1.08));
    }, { passive: false });
    stage.addEventListener('pointerdown', (event) => {
        activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        stage.setPointerCapture(event.pointerId);
        if (activePointers.size === 1 && overlayReady) {
            draggingPointerId = event.pointerId;
            dragStartPointer = { x: event.clientX, y: event.clientY };
            dragStartOffset = { x: manualOffsetX, y: manualOffsetY };
            stage.classList.add('is-dragging');
        }
        if (activePointers.size === 2) {
            draggingPointerId = null;
            dragStartPointer = null;
            dragStartOffset = null;
            stage.classList.remove('is-dragging');
            const [first, second] = Array.from(activePointers.values());
            pinchStartDistance = Math.hypot(second.x - first.x, second.y - first.y);
            pinchStartZoom = activeZoom;
        }
    });
    stage.addEventListener('pointermove', (event) => {
        if (!activePointers.has(event.pointerId)) return;
        activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (activePointers.size >= 2 && pinchStartDistance) {
            const [first, second] = Array.from(activePointers.values());
            const distance = Math.hypot(second.x - first.x, second.y - first.y);
            setZoom(pinchStartZoom * distance / pinchStartDistance);
            return;
        }
        if (draggingPointerId === event.pointerId && dragStartPointer && dragStartOffset) {
            const bounds = canvas.getBoundingClientRect();
            const scale = Math.max(bounds.width / canvas.width, bounds.height / canvas.height);
            if (!scale) return;
            manualOffsetX = dragStartOffset.x + (event.clientX - dragStartPointer.x) / (scale * canvas.width);
            manualOffsetY = dragStartOffset.y + (event.clientY - dragStartPointer.y) / (scale * canvas.height);
        }
    });
    const finishPointer = (event) => {
        activePointers.delete(event.pointerId);
        if (draggingPointerId === event.pointerId) {
            draggingPointerId = null;
            dragStartPointer = null;
            dragStartOffset = null;
            stage.classList.remove('is-dragging');
        }
        if (activePointers.size < 2) pinchStartDistance = 0;
    };
    stage.addEventListener('pointerup', finishPointer);
    stage.addEventListener('pointercancel', finishPointer);
    captureButton.addEventListener('click', () => {
        if (!canvas.width || !canvas.height) return;
        try {
            if (modelViewer.style.display !== 'none') {
                const modelCanvas = modelViewer.shadowRoot?.querySelector('canvas');
                const stageRect = document.getElementById('tryon-stage').getBoundingClientRect();
                const modelRect = modelViewer.getBoundingClientRect();
                if (modelCanvas && modelRect.width && modelRect.height) {
                    context.drawImage(
                        modelCanvas,
                        (modelRect.left - stageRect.left) * canvas.width / stageRect.width,
                        (modelRect.top - stageRect.top) * canvas.height / stageRect.height,
                        modelRect.width * canvas.width / stageRect.width,
                        modelRect.height * canvas.height / stageRect.height
                    );
                }
            }
            canvas.toBlob((blob) => {
                if (!blob) {
                    setStatus('The preview could not be downloaded. Try again after the camera is tracking your face.');
                    return;
                }
                const downloadUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'bealet-virtual-try-on.png';
                link.click();
                URL.revokeObjectURL(downloadUrl);
            }, 'image/png');
        } catch {
            setStatus('This frame image does not allow browser preview downloads. You can still use the live try-on.');
        }
    });
    window.addEventListener('pagehide', stopCamera);
    setZoom(zoomSlider.value);
    setFrame(activeFrameIndex);
})();

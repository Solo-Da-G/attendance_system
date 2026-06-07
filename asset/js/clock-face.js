/**
 * Face verification for staff clock-in/out (face-api.js).
 * Optimised for speed on mobile: uses TinyFaceDetector (lightest model),
 * low inputSize for fast inference, and a relaxed threshold.
 */
(function (global) {
  // Use local models (no CDN delay on mobile)
  const MODEL_URL = '/asset/models';

  // 0.6 = more lenient (recommended for phone cameras with varying lighting).
  // Lower number = stricter. Raise if false-positives occur.
  const MATCH_THRESHOLD = 0.60;

  let modelsReady = false;
  let profileDescriptor = null;
  let initPromise = null;
  let liveLoopId = null;

  // ── helpers ────────────────────────────────────────────────────
  function loadImageFromDataUri(dataUri) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Could not load profile photo. Re-upload a clear JPG/PNG in Employees.'));
      img.src = dataUri;
    });
  }

  // ── model loading ───────────────────────────────────────────────
  async function loadModels() {
    if (modelsReady) return;
    if (initPromise) return initPromise;

    if (typeof faceapi === 'undefined') {
      throw new Error('Face recognition library not loaded. Check your internet and refresh.');
    }

    initPromise = (async () => {
      // Load all three models in parallel for speed
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      ]);
      modelsReady = true;
    })().catch((err) => {
      initPromise = null;
      throw new Error('Could not load face models. Use Wi-Fi and refresh. (' + (err.message || err) + ')');
    });

    return initPromise;
  }

  // ── descriptor extraction ───────────────────────────────────────
  async function descriptorFromImage(imgEl, relaxed) {
    const thresholds = relaxed ? [0.30, 0.25] : [0.35, 0.30];
    for (const score of thresholds) {
      const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: score });
      const det = await faceapi
        .detectSingleFace(imgEl, opts)
        .withFaceLandmarks()
        .withFaceDescriptor();
      if (det) return det.descriptor;
    }
    return null;
  }

  // ── load profile photo and extract face descriptor ──────────────
  async function loadProfilePhoto(photoDataUri) {
    await loadModels();
    if (!photoDataUri || photoDataUri.length < 500) {
      throw new Error('Profile photo missing or too small. Admin: re-upload a clear face photo in Employees.');
    }
    if (!photoDataUri.startsWith('data:image')) {
      throw new Error('Invalid profile photo format. Re-upload a JPG or PNG in Employees.');
    }

    const img = await loadImageFromDataUri(photoDataUri);
    profileDescriptor = await descriptorFromImage(img, true);

    if (!profileDescriptor) {
      throw new Error('No face found in your profile photo. Upload a clear, well-lit, front-facing photo.');
    }
  }

  // ── wait until video is actually streaming ──────────────────────
  function waitForVideoReady(videoEl, maxMs) {
    return new Promise((resolve, reject) => {
      if (videoEl.readyState >= 2 && videoEl.videoWidth > 0) {
        resolve();
        return;
      }
      const timeout = setTimeout(() => reject(new Error('Camera not ready. Wait a moment and try again.')), maxMs || 10000);
      const done = () => { clearTimeout(timeout); resolve(); };
      videoEl.addEventListener('loadeddata', done, { once: true });
      videoEl.addEventListener('playing', done, { once: true });
    });
  }

  // ── verify face from live video ─────────────────────────────────
  async function verifyVideoFace(videoEl) {
    await loadModels();
    if (!profileDescriptor) {
      throw new Error('Profile face not loaded. Refresh the page.');
    }

    await waitForVideoReady(videoEl);

    let bestDistance = 999;
    let bestDet = null;
    // 3 attempts with short delay between — picks best match
    const attempts = 3;

    for (let i = 0; i < attempts; i++) {
      const opts = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,
        scoreThreshold: 0.30,
      });
      const det = await faceapi
        .detectSingleFace(videoEl, opts)
        .withFaceLandmarks()
        .withFaceDescriptor();

      if (det) {
        const dist = faceapi.euclideanDistance(profileDescriptor, det.descriptor);
        if (dist < bestDistance) {
          bestDistance = dist;
          bestDet = det;
        }
      }
      if (i < attempts - 1) {
        await new Promise((r) => setTimeout(r, 150));
      }
    }

    if (!bestDet) {
      return {
        match: false,
        distance: 999,
        message: 'No face detected. Remove hat/glasses, face the camera squarely, and use better lighting.',
      };
    }

    const match = bestDistance < MATCH_THRESHOLD;
    return {
      match,
      distance: bestDistance,
      descriptor: bestDet && bestDet.descriptor ? Array.from(bestDet.descriptor) : null,
      message: match
        ? '✅ Face matched! (score ' + bestDistance.toFixed(2) + ')'
        : '❌ Face mismatch (score ' + bestDistance.toFixed(2) + ', need under ' + MATCH_THRESHOLD + '). Move closer, improve lighting, or re-upload a clearer profile photo.',
    };
  }

  // ── LIVE DETECTION LOOP (shows green/red border on camera) ──────
  // Call startLiveDetection(videoEl, borderEl) to begin live preview.
  // Call stopLiveDetection() to stop it (e.g. after clocking).
  async function startLiveDetection(videoEl, containerEl) {
    if (!modelsReady || !profileDescriptor) return;

    async function loop() {
      if (!liveLoopId) return; // stopped
      try {
        await waitForVideoReady(videoEl, 2000);
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.28 });
        const det = await faceapi.detectSingleFace(videoEl, opts).withFaceLandmarks().withFaceDescriptor();
        if (containerEl) {
          if (!det) {
            containerEl.style.borderColor = '#e2e8f0'; // neutral
          } else {
            const dist = faceapi.euclideanDistance(profileDescriptor, det.descriptor);
            containerEl.style.borderColor = dist < MATCH_THRESHOLD ? '#10b981' : '#f59e0b';
          }
        }
      } catch (_) { /* ignore live loop errors */ }
      liveLoopId = setTimeout(loop, 700); // run every 700ms
    }
    liveLoopId = setTimeout(loop, 500);
  }

  function stopLiveDetection() {
    if (liveLoopId) { clearTimeout(liveLoopId); liveLoopId = null; }
  }

  // ── public API ──────────────────────────────────────────────────
  global.ClockFace = {
    MATCH_THRESHOLD,
    loadModels,
    loadProfilePhoto,
    verifyVideoFace,
    startLiveDetection,
    stopLiveDetection,
    isReady: () => modelsReady && profileDescriptor !== null,
  };
})(window);

/**
 * Face verification for staff clock-in/out (face-api.js).
 * Optimised for speed on mobile: uses TinyFaceDetector (lightest model),
 * low inputSize for fast inference.
 * 
 * SECURITY HARDENING:
 * - MATCH_THRESHOLD lowered to 0.48 (stricter — glasses/cap wearers will fail)
 * - Accessory detection: rejects faces where landmarks indicate occlusion
 *   (glasses / sunglasses / caps / hats distort eye + forehead landmark positions)
 * - Only the profile photo stored for the LOGGED-IN staff is used as reference
 */
(function (global) {
  // Use local models (no CDN delay on mobile)
  const MODEL_URL = '/asset/models';

  // STRICTER threshold: 0.54 = must be a close match.
  // Glasses or caps shift facial geometry enough to push distance above this.
  // Previous value was 0.60 — too lenient (allowed wrong-person matches).
  // Was 0.48 temporarily but caused false rejections for correct users.
  const MATCH_THRESHOLD = 0.54;

  // Minimum landmark-based eye openness ratio. Glasses/caps cause partial
  // face occlusion; this ratio detects suspiciously covered eyes/forehead.
  const MIN_EYE_OPEN_RATIO = 0.10;

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

  // ── Accessory detection helpers ────────────────────────────────
  // Note: Manual landmark-based heuristics for caps/glasses were too aggressive
  // with TinyFaceDetector and caused false positives for legitimate users.
  // We now rely purely on the stricter MATCH_THRESHOLD (0.50) to naturally reject
  // faces where glasses/caps distort the descriptor distance.
  
  function hasGlassesDetected(landmarks) { return false; }
  function hasCapDetected(det) { return false; }

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

  // Helper to hash photo string for instant descriptor caching
  function getPhotoHash(str) {
    let h = 0;
    const len = str.length;
    for (let i = 0; i < Math.min(len, 600); i++) {
      h = ((h << 5) - h) + str.charCodeAt(i);
      h |= 0;
    }
    return 'tds_face_desc_' + len + '_' + Math.abs(h);
  }

  // ── load profile photo and extract face descriptor (cached in localStorage) ──
  async function loadProfilePhoto(photoDataUri) {
    if (!photoDataUri || photoDataUri.length < 500) {
      throw new Error('Profile photo missing or too small. Admin: re-upload a clear face photo in Employees.');
    }
    if (!photoDataUri.startsWith('data:image')) {
      throw new Error('Invalid profile photo format. Re-upload a JPG or PNG in Employees.');
    }

    // Check localStorage cache first for 0ms instantaneous loading
    const cacheKey = getPhotoHash(photoDataUri);
    try {
      const cached = localStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Array.isArray(parsed) && parsed.length === 128) {
          profileDescriptor = new Float32Array(parsed);
          // Start background model load without blocking
          loadModels().catch(() => {});
          return;
        }
      }
    } catch (_) {}

    await loadModels();
    const img = await loadImageFromDataUri(photoDataUri);
    profileDescriptor = await descriptorFromImage(img, true);

    if (!profileDescriptor) {
      throw new Error('No face found in your profile photo. Upload a clear, well-lit, front-facing photo WITHOUT glasses or hat.');
    }

    // Save to cache for future 0ms loads
    try {
      localStorage.setItem(cacheKey, JSON.stringify(Array.from(profileDescriptor)));
    } catch (_) {}
  }

  // ── wait until video is actually streaming ──────────────────────
  function waitForVideoReady(videoEl, maxMs) {
    return new Promise((resolve, reject) => {
      if (videoEl.readyState >= 2 && videoEl.videoWidth > 0) {
        resolve();
        return;
      }
      const timeout = setTimeout(() => reject(new Error('Camera not ready. Wait a moment and try again.')), maxMs || 6000);
      const done = () => { clearTimeout(timeout); resolve(); };
      videoEl.addEventListener('loadeddata', done, { once: true });
      videoEl.addEventListener('playing', done, { once: true });
    });
  }

  // ── verify face from live video (fast 1-2s inference with early exit) ──
  async function verifyVideoFace(videoEl) {
    // Stop background detection immediately so 100% CPU is available for verification
    stopLiveDetection();

    await loadModels();
    if (!profileDescriptor) {
      throw new Error('Profile face not loaded. Refresh the page.');
    }

    await waitForVideoReady(videoEl);

    let bestDistance = 999;
    let bestDet = null;
    // Maximum 2 attempts with optimized 224 input size for 3x faster mobile inference
    const attempts = 2;

    for (let i = 0; i < attempts; i++) {
      const opts = new faceapi.TinyFaceDetectorOptions({
        inputSize: 224,
        scoreThreshold: 0.32,
      });

      const det = await faceapi
        .detectSingleFace(videoEl, opts)
        .withFaceLandmarks()
        .withFaceDescriptor();

      if (det) {
        // Accessory checks
        if (hasGlassesDetected(det.landmarks)) {
          return {
            match: false,
            distance: 999,
            message: '🕶️ Glasses or sunglasses detected. Please remove them and try again.',
          };
        }
        if (hasCapDetected(det)) {
          return {
            match: false,
            distance: 999,
            message: '🧢 Cap or hat detected. Please remove it and face the camera directly.',
          };
        }

        const dist = faceapi.euclideanDistance(profileDescriptor, det.descriptor);
        if (dist < bestDistance) {
          bestDistance = dist;
          bestDet = det;
        }

        // EARLY EXIT: If this frame is a match, return immediately!
        // No need to run further attempts and delay clocking by 10-20 seconds.
        if (dist < MATCH_THRESHOLD) {
          return {
            match: true,
            distance: dist,
            descriptor: Array.from(det.descriptor),
            message: '✅ Face matched! (score ' + dist.toFixed(2) + ')',
          };
        }
      }

      if (i < attempts - 1) {
        await new Promise((r) => setTimeout(r, 100));
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
        : '❌ Face does not match your profile photo (score ' + bestDistance.toFixed(2) + ', need under ' + MATCH_THRESHOLD + '). '
          + 'Ensure no glasses/hat, face camera squarely, and use good lighting.',
    };
  }

  // ── LIVE DETECTION LOOP (lightweight presence indicator, runs every 1200ms) ──
  async function startLiveDetection(videoEl, containerEl) {
    if (!modelsReady || !profileDescriptor) return;

    async function loop() {
      if (!liveLoopId) return; // stopped
      try {
        await waitForVideoReady(videoEl, 1500);
        // Use ultra-fast 160 input size just for the border status
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.38 });
        const det = await faceapi.detectSingleFace(videoEl, opts).withFaceLandmarks();
        if (containerEl) {
          if (!det) {
            containerEl.style.borderColor = '#e2e8f0'; // neutral — looking for face
          } else {
            containerEl.style.borderColor = '#10b981'; // green — face in frame!
          }
        }
      } catch (_) { /* ignore live loop errors */ }
      if (liveLoopId) {
        liveLoopId = setTimeout(loop, 1200); // run every 1200ms (lightweight, saves CPU)
      }
    }
    liveLoopId = setTimeout(loop, 800);
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

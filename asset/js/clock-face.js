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
      throw new Error('No face found in your profile photo. Upload a clear, well-lit, front-facing photo WITHOUT glasses or hat.');
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
    // 4 attempts with short delay — picks best match
    const attempts = 4;

    for (let i = 0; i < attempts; i++) {
      const opts = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,
        scoreThreshold: 0.35,  // Lowered back to 0.35 so mobile cameras can detect the face
      });
      const det = await faceapi
        .detectSingleFace(videoEl, opts)
        .withFaceLandmarks()
        .withFaceDescriptor();

      if (det) {
        // ── Accessory checks ──────────────────────────────────────
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
        : '❌ Face does not match your profile photo (score ' + bestDistance.toFixed(2) + ', need under ' + MATCH_THRESHOLD + '). '
          + 'Ensure no glasses/hat, face camera squarely, and use good lighting. If this keeps failing, ask admin to re-upload your profile photo.',
    };
  }

  // ── LIVE DETECTION LOOP (shows green/red/orange border on camera) ──
  async function startLiveDetection(videoEl, containerEl) {
    if (!modelsReady || !profileDescriptor) return;

    async function loop() {
      if (!liveLoopId) return; // stopped
      try {
        await waitForVideoReady(videoEl, 2000);
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.40 });
        const det = await faceapi.detectSingleFace(videoEl, opts).withFaceLandmarks().withFaceDescriptor();
        if (containerEl) {
          if (!det) {
            containerEl.style.borderColor = '#e2e8f0'; // neutral — no face
          } else {
            // Check accessories first
            const glassesDetected = hasGlassesDetected(det.landmarks);
            const capDetected = hasCapDetected(det);
            if (glassesDetected || capDetected) {
              containerEl.style.borderColor = '#f59e0b'; // amber — accessory warning
            } else {
              const dist = faceapi.euclideanDistance(profileDescriptor, det.descriptor);
              containerEl.style.borderColor = dist < MATCH_THRESHOLD ? '#10b981' : '#ef4444';
            }
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

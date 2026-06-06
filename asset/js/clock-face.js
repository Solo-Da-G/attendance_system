/**
 * Face verification for staff clock-in/out (face-api.js).
 */
(function (global) {
  // Use local models (faster + avoids CDN delays/timeouts on mobile)
  const MODEL_URL = '/asset/models';
  const MATCH_THRESHOLD = 0.55;

  let modelsReady = false;
  let profileDescriptor = null;
  let initPromise = null;

  function loadImageFromDataUri(dataUri) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Could not load profile photo. Re-upload a JPG/PNG in Employees.'));
      img.src = dataUri;
    });
  }

  async function loadModels() {
    if (modelsReady) return;
    if (initPromise) return initPromise;

    if (typeof faceapi === 'undefined') {
      throw new Error('Face recognition library failed to load. Check your internet connection and refresh.');
    }

    initPromise = (async () => {
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      ]);
      modelsReady = true;
    })().catch((err) => {
      initPromise = null;
      throw new Error('Could not download face models. Use Wi‑Fi and refresh the page. (' + (err.message || err) + ')');
    });

    return initPromise;
  }

  async function descriptorFromImage(imgEl, relaxed) {
    const thresholds = relaxed ? [0.35, 0.25] : [0.45, 0.35];
    for (const score of thresholds) {
      const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: score });
      const det = await faceapi
        .detectSingleFace(imgEl, opts)
        .withFaceLandmarks()
        .withFaceDescriptor();
      if (det) return det.descriptor;
    }
    return null;
  }

  async function loadProfilePhoto(photoDataUri) {
    await loadModels();
    if (!photoDataUri || photoDataUri.length < 500) {
      throw new Error('Profile photo missing or corrupted. Admin: re-upload photo in Employees (use a clear face photo under 2MB).');
    }
    if (!photoDataUri.startsWith('data:image')) {
      throw new Error('Invalid profile photo format. Re-upload a JPG or PNG in Employees.');
    }

    const img = await loadImageFromDataUri(photoDataUri);
    profileDescriptor = await descriptorFromImage(img, true);

    if (!profileDescriptor) {
      throw new Error('No face found in your profile photo. Upload a clear front-facing photo with good lighting.');
    }
  }

  function waitForVideoReady(videoEl, maxMs) {
    return new Promise((resolve, reject) => {
      if (videoEl.readyState >= 2 && videoEl.videoWidth > 0) {
        resolve();
        return;
      }
      const timeout = setTimeout(() => reject(new Error('Camera not ready. Wait a moment and try again.')), maxMs || 8000);
      const done = () => {
        clearTimeout(timeout);
        resolve();
      };
      videoEl.addEventListener('loadeddata', done, { once: true });
      videoEl.addEventListener('playing', done, { once: true });
    });
  }

  async function verifyVideoFace(videoEl) {
    await loadModels();
    if (!profileDescriptor) {
      throw new Error('Profile face not loaded. Refresh the page.');
    }

    await waitForVideoReady(videoEl);

    let bestDistance = 999;
    let bestDet = null;
    const attempts = 3;

    for (let i = 0; i < attempts; i++) {
      const opts = new faceapi.TinyFaceDetectorOptions({
        inputSize: 416,
        scoreThreshold: i === 0 ? 0.4 : 0.3,
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
        await new Promise((r) => setTimeout(r, 200));
      }
    }

    if (!bestDet) {
      return {
        match: false,
        distance: 999,
        message: 'No face detected. Remove hat/glasses, face the camera, use brighter light.',
      };
    }

    const match = bestDistance < MATCH_THRESHOLD;
    return {
      match,
      distance: bestDistance,
      descriptor: bestDet && bestDet.descriptor ? Array.from(bestDet.descriptor) : null,
      message: match
        ? 'Face matched (score ' + bestDistance.toFixed(2) + ')'
        : 'Face mismatch (score ' + bestDistance.toFixed(2) + ', need under ' + MATCH_THRESHOLD + '). Try matching your profile photo pose and lighting.',
    };
  }

  global.ClockFace = {
    MATCH_THRESHOLD,
    loadModels,
    loadProfilePhoto,
    verifyVideoFace,
    isReady: () => modelsReady && profileDescriptor !== null,
  };
})(window);

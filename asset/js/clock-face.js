/**
 * Face verification for staff clock-in/out (face-api.js).
 */
(function (global) {
  const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model/';
  const MATCH_THRESHOLD = 0.6;

  let modelsReady = false;
  let profileDescriptor = null;
  let initPromise = null;

  async function loadModels() {
    if (modelsReady) return;
    if (initPromise) return initPromise;

    initPromise = (async () => {
      await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
      await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
      await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
      modelsReady = true;
    })();

    return initPromise;
  }

  async function descriptorFromImage(imgEl) {
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
    const det = await faceapi
      .detectSingleFace(imgEl, opts)
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!det) return null;
    return det.descriptor;
  }

  async function loadProfilePhoto(photoDataUri) {
    await loadModels();
    if (!photoDataUri) {
      throw new Error('No profile photo on file. Ask your admin to upload your employee photo.');
    }

    const img = await faceapi.fetchImage(photoDataUri);
    profileDescriptor = await descriptorFromImage(img);

    if (!profileDescriptor) {
      throw new Error('Could not detect a face in your profile photo. Ask admin to upload a clear front-facing photo.');
    }
  }

  async function verifyVideoFace(videoEl) {
    await loadModels();
    if (!profileDescriptor) {
      throw new Error('Profile face not loaded yet. Please wait a moment.');
    }

    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.45 });
    const det = await faceapi
      .detectSingleFace(videoEl, opts)
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!det) {
      return { match: false, distance: 999, message: 'No face detected. Center your face in the camera.' };
    }

    const distance = faceapi.euclideanDistance(profileDescriptor, det.descriptor);
    const match = distance < MATCH_THRESHOLD;

    return {
      match,
      distance,
      message: match
        ? 'Face matched'
        : 'Face does not match your profile. Try better lighting and look straight at the camera.',
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

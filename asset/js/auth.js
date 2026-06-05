/**
 * AUTHENTICATION MODULE
 * Handles Face Verification and Geofencing for the Login process.
 */

class AttendanceAuth {
    constructor() {
        this.modelsLoaded = false;
        this.videoStream = null;
        this.profilePhotoUrl = null;
        this.currentCoords = null;
        this.video = document.getElementById('faceVideo');
        this.guide = document.getElementById('faceGuide');
        this.statusEl = document.getElementById('faceStatus');
        this.scanLine = document.getElementById('scanLine');
    }

    async initGeolocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject("Geolocation not supported.");
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.currentCoords = pos.coords;
                    resolve(pos.coords);
                },
                (err) => reject("Please enable location access.")
            );
        });
    }

    async loadFaceModels(modelUrl = 'asset/models') {
        if (this.modelsLoaded) return;
        
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl)
            ]);
            this.modelsLoaded = true;
        } catch (err) {
            console.error("Model loading failed:", err);
            throw new Error("Failed to load AI models.");
        }
    }

    async startCamera() {
        try {
            this.videoStream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480, facingMode: 'user' }
            });
            this.video.srcObject = this.videoStream;
            return new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    resolve();
                };
            });
        } catch (err) {
            throw new Error("Camera access denied.");
        }
    }

    stopCamera() {
        if (this.videoStream) {
            this.videoStream.getTracks().forEach(track => track.stop());
            this.videoStream = null;
        }
        if (this.video) this.video.srcObject = null;
    }

    setStatus(type, message) {
        if (!this.statusEl) return;
        this.statusEl.className = 'face-status ' + type;
        this.statusEl.innerHTML = message;
    }

    async verifyFace(profileUrl) {
        try {
            // Detect from webcam
            const webcamDetection = await faceapi
                .detectSingleFace(this.video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!webcamDetection) {
                throw new Error("No face detected. Please position yourself clearly.");
            }

            // Load profile photo
            const profileImg = await faceapi.fetchImage(profileUrl);
            const profileDetection = await faceapi
                .detectSingleFace(profileImg, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.3 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!profileDetection) {
                throw new Error("Face not detected in profile photo. Contact admin.");
            }

            // Compare
            const distance = faceapi.euclideanDistance(webcamDetection.descriptor, profileDetection.descriptor);
            const isMatch = distance < 0.5;
            const confidence = Math.max(0, Math.min(100, Math.round((1 - distance) * 100)));

            return { isMatch, confidence };
        } catch (err) {
            throw err;
        }
    }
}

// Global instance helper
const AuthManager = new AttendanceAuth();

/**
 * TDS WEBAUTHN THUMBPRINT & BIOMETRIC AUTHENTICATION CLIENT
 * Supports Apple iOS (Face ID & Touch ID), Android Fingerprints, Windows Hello, and Mac Touch ID
 */

const TDSBiometric = (function () {
    // Helper: Base64URL string to ArrayBuffer
    function base64UrlToBuffer(base64Url) {
        if (!base64Url) return new ArrayBuffer(0);
        let base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        while (base64.length % 4) {
            base64 += '=';
        }
        const binary = window.atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    // Helper: ArrayBuffer to Base64URL string
    function bufferToBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    // Hardware support cache (0ms subsequent checks)
    let _supportedCache = null;
    let _supportPromise = null;

    // GPS Cache (0ms coordinate acquisition if fresh within 2 mins)
    let _lastCoords = null;
    let _coordsTime = 0;
    const GPS_CACHE_TTL = 120000;

    // Prefetched Challenge Options Cache for Authentication & Clocking
    let _prefetchedAuth = null;
    let _prefetchedAuthTime = 0;
    let _prefetchingAuthPromise = null;

    // Prefetched Challenge Options Cache for Registration (Preserves iOS Safari user activation)
    let _prefetchedReg = null;
    let _prefetchedRegTime = 0;
    let _prefetchingRegPromise = null;

    // Check if device supports platform biometrics (0ms if cached)
    async function isSupported() {
        if (_supportedCache !== null) return _supportedCache;
        if (!window.PublicKeyCredential || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
            _supportedCache = false;
            return false;
        }
        if (_supportPromise) return _supportPromise;

        _supportPromise = (async () => {
            try {
                _supportedCache = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                return _supportedCache;
            } catch (e) {
                _supportedCache = false;
                return false;
            } finally {
                _supportPromise = null;
            }
        })();

        return _supportPromise;
    }

    // Pre-warm GPS in background so it never stalls clocking
    function warmupGps() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                _lastCoords = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude
                };
                _coordsTime = Date.now();
            },
            () => {},
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 }
        );
    }

    // Fast-path coordinate resolution
    function getCoordinates() {
        // If fresh coordinates are in RAM, resolve instantly (0ms)
        if (_lastCoords && (Date.now() - _coordsTime < GPS_CACHE_TTL)) {
            setTimeout(warmupGps, 100);
            return Promise.resolve(_lastCoords);
        }

        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve(_lastCoords || { latitude: null, longitude: null });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    _lastCoords = {
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude
                    };
                    _coordsTime = Date.now();
                    resolve(_lastCoords);
                },
                () => {
                    resolve(_lastCoords || { latitude: null, longitude: null });
                },
                { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
            );
        });
    }

    // Pre-fetch authentication challenge & options in background
    async function prefetchAuth() {
        if (_prefetchingAuthPromise) return _prefetchingAuthPromise;

        _prefetchingAuthPromise = (async () => {
            try {
                const resp = await fetch('/api/biometric_options.php?action=auth_options', { cache: 'no-store' });
                const data = await resp.json();
                if (data && data.status === 'success') {
                    _prefetchedAuth = data;
                    _prefetchedAuthTime = Date.now();
                }
            } catch (_) {}
            _prefetchingAuthPromise = null;
        })();

        return _prefetchingAuthPromise;
    }

    // Get Auth Options (Instant if prefetched, otherwise fallback to network)
    async function getAuthOptions() {
        if (_prefetchedAuth && (Date.now() - _prefetchedAuthTime < 180000)) {
            const opt = _prefetchedAuth;
            _prefetchedAuth = null;
            setTimeout(prefetchAuth, 600);
            return opt;
        }

        if (_prefetchingAuthPromise) {
            await _prefetchingAuthPromise;
            if (_prefetchedAuth && (Date.now() - _prefetchedAuthTime < 180000)) {
                const opt = _prefetchedAuth;
                _prefetchedAuth = null;
                setTimeout(prefetchAuth, 600);
                return opt;
            }
        }

        const optResp = await fetch('/api/biometric_options.php?action=auth_options', { cache: 'no-store' });
        return await optResp.json();
    }

    // Pre-fetch registration challenge in background to keep iOS Safari user gesture active
    async function prefetchRegister() {
        if (_prefetchingRegPromise) return _prefetchingRegPromise;

        _prefetchingRegPromise = (async () => {
            try {
                const resp = await fetch('/api/biometric_options.php?action=register_options', { cache: 'no-store' });
                const data = await resp.json();
                if (data && data.status === 'success') {
                    _prefetchedReg = data;
                    _prefetchedRegTime = Date.now();
                }
            } catch (_) {}
            _prefetchingRegPromise = null;
        })();

        return _prefetchingRegPromise;
    }

    // Get Registration Options (Instant if prefetched, preserving iOS transient user activation)
    async function getRegisterOptions() {
        if (_prefetchedReg && (Date.now() - _prefetchedRegTime < 180000)) {
            const opt = _prefetchedReg;
            _prefetchedReg = null;
            setTimeout(prefetchRegister, 600);
            return opt;
        }

        if (_prefetchingRegPromise) {
            await _prefetchingRegPromise;
            if (_prefetchedReg && (Date.now() - _prefetchedRegTime < 180000)) {
                const opt = _prefetchedReg;
                _prefetchedReg = null;
                setTimeout(prefetchRegister, 600);
                return opt;
            }
        }

        const optResp = await fetch('/api/biometric_options.php?action=register_options', { cache: 'no-store' });
        return await optResp.json();
    }

    // Check enrollment status
    async function getStatus() {
        try {
            const resp = await fetch('/api/biometric_status.php', { cache: 'no-store' });
            return await resp.json();
        } catch (e) {
            return { status: 'error', enrolled: false, devices: [] };
        }
    }

    // Register a new Biometric (iOS Face ID/Touch ID, Android Thumbprint, Windows Hello)
    async function register(deviceName = '') {
        const supported = await isSupported();
        if (!supported) {
            throw new Error("Biometric scanning (Touch ID, Face ID, or Fingerprint) is not supported or not enabled on this device/browser.");
        }

        // 1. Fetch challenge & options from server (pre-fetched in RAM for 0ms delay to keep Safari gesture alive)
        const options = await getRegisterOptions();

        if (!options || options.status !== 'success') {
            throw new Error((options && options.message) || 'Failed to initialize biometric registration.');
        }

        // 2. Format options for WebAuthn API
        const createOptions = {
            challenge: base64UrlToBuffer(options.challenge),
            rp: options.rp,
            user: {
                id: base64UrlToBuffer(options.user.id),
                name: options.user.name,
                displayName: options.user.displayName
            },
            pubKeyCredParams: options.pubKeyCredParams,
            authenticatorSelection: options.authenticatorSelection,
            timeout: options.timeout || 60000,
            attestation: options.attestation || 'none'
        };

        // 3. Trigger native browser Biometric prompt (Apple Face ID/Touch ID, Android thumbprint, Windows Hello)
        let credential;
        try {
            credential = await navigator.credentials.create({ publicKey: createOptions });
        } catch (err) {
            if (err.name === 'NotAllowedError') {
                const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
                if (isIOS) {
                    throw new Error("Biometric registration was not allowed by this iPhone.\n\nPlease verify these iPhone settings:\n1. Face ID or Touch ID & Passcode is turned ON in iPhone Settings.\n2. iCloud Keychain is turned ON in Settings > [Your Name] > iCloud > Passwords & Keychain.\n3. Safari is not in Private Browsing mode.");
                } else {
                    throw new Error("Biometric scan was cancelled or not allowed by your device.");
                }
            }
            throw err;
        }

        if (!credential) {
            throw new Error("Biometric scan cancelled or failed.");
        }

        // 4. Send credential to server
        const regPayload = {
            credential_id: credential.id,
            raw_id: bufferToBase64Url(credential.rawId),
            type: credential.type,
            device_name: deviceName,
            response: {
                clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                attestationObject: bufferToBase64Url(credential.response.attestationObject)
            }
        };

        const saveResp = await fetch('/api/biometric_register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(regPayload)
        });

        const saveResult = await saveResp.json();
        if (saveResult.status !== 'success') {
            throw new Error(saveResult.message || 'Failed to complete registration on server.');
        }

        // Preload next authentication options immediately
        setTimeout(prefetchAuth, 200);

        return saveResult;
    }

    // Lightning-Fast Verify thumbprint / Face ID & punch attendance
    async function clock() {
        // 1. Launch background GPS acquisition concurrently (0ms wait)
        const coordsPromise = getCoordinates();

        // 2. Check platform support (0ms if cached)
        const supported = await isSupported();
        if (!supported) {
            throw new Error("Biometric authentication is not supported on this device/browser.");
        }

        // 3. Grab authentication challenge (0ms if prefetched!)
        const options = await getAuthOptions();

        if (!options || options.status !== 'success') {
            throw new Error((options && options.message) || 'Failed to start biometric authentication.');
        }

        if (!options.allowCredentials || options.allowCredentials.length === 0) {
            throw new Error("No biometric registered for this account. Please click 'Register This Device' first.");
        }

        // 4. Format allowed credentials
        const allowCreds = options.allowCredentials.map(c => {
            const item = {
                type: 'public-key',
                id: base64UrlToBuffer(c.id)
            };
            if (c.transports && Array.isArray(c.transports)) {
                item.transports = c.transports;
            }
            return item;
        });

        const getOptions = {
            challenge: base64UrlToBuffer(options.challenge),
            rpId: options.rpId,
            allowCredentials: allowCreds,
            userVerification: options.userVerification || 'preferred',
            timeout: options.timeout || 60000
        };

        // 5. TRIGGER NATIVE FINGERPRINT / FACE ID SENSOR IMMEDIATELY (<15ms)
        let assertion;
        try {
            assertion = await navigator.credentials.get({ publicKey: getOptions });
        } catch (err) {
            if (err.name === 'NotAllowedError') {
                const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
                if (isIOS) {
                    throw new Error("Biometric verification was not allowed. If using an iPhone, ensure Face ID / Touch ID is permitted in Safari or your Home Screen app.");
                } else {
                    throw new Error("Biometric verification was cancelled or timed out.");
                }
            }
            throw err;
        }

        if (!assertion) {
            throw new Error("Biometric verification cancelled.");
        }

        // 6. Await GPS (which already finished in background while user touched sensor!)
        const coords = await coordsPromise;

        // 7. Post assertion & coordinates to server for clocking
        const clockPayload = {
            credential_id: assertion.id,
            latitude: coords ? coords.latitude : null,
            longitude: coords ? coords.longitude : null,
            response: {
                clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
                authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                signature: bufferToBase64Url(assertion.response.signature)
            }
        };

        const clockResp = await fetch('/api/biometric_clock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(clockPayload)
        });

        const clockResult = await clockResp.json();
        if (clockResult.status !== 'success') {
            throw new Error(clockResult.message || 'Clocking rejected by server.');
        }

        return clockResult;
    }

    // Auto pre-warm support, GPS, and challenges as soon as script runs
    if (typeof window !== 'undefined') {
        setTimeout(() => {
            isSupported();
            warmupGps();
            prefetchAuth();
            prefetchRegister();
        }, 100);
    }

    return {
        isSupported,
        getStatus,
        register,
        clock,
        prefetchAuth,
        prefetchRegister,
        warmupGps,
        base64UrlToBuffer,
        bufferToBase64Url
    };
})();
window.TDSBiometric = TDSBiometric;

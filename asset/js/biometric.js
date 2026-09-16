/**
 * TDS WEBAUTHN THUMBPRINT & BIOMETRIC AUTHENTICATION CLIENT
 * Supports Android Fingerprint Sensors, Windows Hello, and Mac Touch ID
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

    // Check if device supports platform biometrics
    async function isSupported() {
        if (window.PublicKeyCredential &&
            PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
            try {
                return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    // Check enrollment status
    async function getStatus() {
        try {
            const resp = await fetch('/api/biometric_status.php');
            return await resp.json();
        } catch (e) {
            return { status: 'error', enrolled: false, devices: [] };
        }
    }

    // Acquire GPS Coordinates
    function getCoordinates() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve({ latitude: null, longitude: null });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    resolve({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude
                    });
                },
                () => {
                    resolve({ latitude: null, longitude: null });
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 10000 }
            );
        });
    }

    // Register a new Thumbprint / Passkey
    async function register(deviceName = '') {
        const supported = await isSupported();
        if (!supported) {
            throw new Error("Your browser or device does not support hardware thumbprint scanning. Please ensure biometric hardware (or PIN) is enabled.");
        }

        // 1. Fetch challenge & options from server
        const optResp = await fetch('/api/biometric_options.php?action=register_options');
        const options = await optResp.json();

        if (options.status !== 'success') {
            throw new Error(options.message || 'Failed to initialize biometric registration.');
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

        // 3. Trigger native browser Biometric prompt (Android thumbprint / Windows Hello)
        const credential = await navigator.credentials.create({ publicKey: createOptions });

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

        return saveResult;
    }

    // Verify thumbprint & punch attendance
    async function clock() {
        const supported = await isSupported();
        if (!supported) {
            throw new Error("Biometric authentication is not supported on this device/browser.");
        }

        // 1. Get authentication challenge & allowed credentials
        const optResp = await fetch('/api/biometric_options.php?action=auth_options');
        const options = await optResp.json();

        if (options.status !== 'success') {
            throw new Error(options.message || 'Failed to start biometric authentication.');
        }

        if (!options.allowCredentials || options.allowCredentials.length === 0) {
            throw new Error("No thumbprint registered for this account. Please click 'Register Thumbprint' first.");
        }

        // 2. Fetch current GPS position in parallel
        const coordsPromise = getCoordinates();

        // 3. Format allowed credentials
        const allowCreds = options.allowCredentials.map(c => ({
            type: 'public-key',
            id: base64UrlToBuffer(c.id),
            transports: c.transports
        }));

        const getOptions = {
            challenge: base64UrlToBuffer(options.challenge),
            rpId: options.rpId,
            allowCredentials: allowCreds,
            userVerification: options.userVerification || 'required',
            timeout: options.timeout || 60000
        };

        // 4. Trigger native fingerprint prompt
        const assertion = await navigator.credentials.get({ publicKey: getOptions });

        if (!assertion) {
            throw new Error("Biometric verification cancelled.");
        }

        const coords = await coordsPromise;

        // 5. Send assertion & coordinates to server for clocking
        const clockPayload = {
            credential_id: assertion.id,
            latitude: coords.latitude,
            longitude: coords.longitude,
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

    return {
        isSupported,
        getStatus,
        register,
        clock
    };
})();
window.TDSBiometric = TDSBiometric;

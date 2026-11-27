<!-- Dedicated Native Install Controller & Fallback Modals (iOS Safari, Firefox, Edge, Android APK) -->

<!-- 1. Dedicated iOS Safari Bottom Sheet (Exactly as it was) -->
<div id="iosInstallModal" class="pwa-install-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:999999; align-items:flex-end; justify-content:center; backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); padding:16px; box-sizing:border-box;">
    <div style="background:#0f172a; color:#ffffff; width:100%; max-width:440px; border-radius:24px; padding:24px 20px; border:1px solid rgba(255,255,255,0.15); box-shadow:0 24px 48px rgba(0,0,0,0.6); text-align:center; font-family:'Plus Jakarta Sans', sans-serif;">
        <div style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #10439f, #2563eb); margin:0 auto 12px; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(16,67,159,0.4);">
            <svg viewBox="0 0 24 24" width="28" height="28" stroke="#fff" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px; color:#ffffff;">Install on iPhone / iPad</h3>
        <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:18px;">Safari installs apps to your home screen in two quick taps:</p>
        <div style="background:rgba(255,255,255,0.06); border-radius:14px; padding:14px 16px; text-align:left; font-size:13px; color:#e2e8f0; line-height:1.6; margin-bottom:18px; border:1px solid rgba(255,255,255,0.08);">
            <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px;">
                <span style="background:#10439f; color:#fff; min-width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; margin-top:1px;">1</span>
                <span>Tap the <strong>Share</strong> button <svg style="vertical-align:middle; display:inline; margin:0 2px;" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.3"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> at the bottom of Safari.</span>
            </div>
            <div style="display:flex; align-items:flex-start; gap:12px;">
                <span style="background:#10439f; color:#fff; min-width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; margin-top:1px;">2</span>
                <span>Scroll down and tap <strong>'Add to Home Screen'</strong> (➕), then tap <strong>'Add'</strong>.</span>
            </div>
        </div>
        <button type="button" onclick="closeInstallModals()" style="width:100%; padding:13px; border-radius:12px; background:linear-gradient(135deg, #10439f, #2563eb); color:#fff; font-weight:700; border:none; cursor:pointer; font-size:14px; box-shadow:0 4px 14px rgba(16,67,159,0.4);">Got It</button>
    </div>
</div>

<!-- 2. Dedicated Mozilla Firefox Modal -->
<div id="firefoxInstallModal" class="pwa-install-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); padding:16px; box-sizing:border-box;">
    <div style="background:#0f172a; color:#ffffff; width:100%; max-width:420px; border-radius:24px; padding:24px 20px; border:1px solid rgba(255,255,255,0.15); box-shadow:0 24px 48px rgba(0,0,0,0.6); text-align:center; font-family:'Plus Jakarta Sans', sans-serif;">
        <div style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #ea580c, #f97316); margin:0 auto 12px; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(234,88,12,0.4); font-size:24px;">
            🦊
        </div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px; color:#ffffff;">Install on Mozilla Firefox</h3>
        <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:18px;">Add TDS Attendance to your device directly from Firefox:</p>
        <div style="background:rgba(255,255,255,0.06); border-radius:14px; padding:14px 16px; text-align:left; font-size:13px; color:#e2e8f0; line-height:1.6; margin-bottom:18px; border:1px solid rgba(255,255,255,0.08);">
            <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px;">
                <span style="background:#ea580c; color:#fff; min-width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; margin-top:1px;">1</span>
                <span>Tap the <strong>three dots (⋮)</strong> or menu icon in Firefox.</span>
            </div>
            <div style="display:flex; align-items:flex-start; gap:12px;">
                <span style="background:#ea580c; color:#fff; min-width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; margin-top:1px;">2</span>
                <span>Tap <strong>'Install'</strong> (or <strong>'Add to Home screen'</strong>).</span>
            </div>
        </div>
        <button type="button" onclick="closeInstallModals()" style="width:100%; padding:13px; border-radius:12px; background:linear-gradient(135deg, #ea580c, #f97316); color:#fff; font-weight:700; border:none; cursor:pointer; font-size:14px;">Got It</button>
    </div>
</div>

<!-- 3. Dedicated Microsoft Edge Modal -->
<div id="edgeInstallModal" class="pwa-install-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); padding:16px; box-sizing:border-box;">
    <div style="background:#0f172a; color:#ffffff; width:100%; max-width:420px; border-radius:24px; padding:24px 20px; border:1px solid rgba(255,255,255,0.15); box-shadow:0 24px 48px rgba(0,0,0,0.6); text-align:center; font-family:'Plus Jakarta Sans', sans-serif;">
        <div style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #0284c7, #0ea5e9); margin:0 auto 12px; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(2,132,199,0.4); font-size:24px;">
            🌊
        </div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px; color:#ffffff;">Install on Microsoft Edge</h3>
        <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:18px;">Install TDS Attendance as an application:</p>
        <div style="background:rgba(255,255,255,0.06); border-radius:14px; padding:14px 16px; text-align:left; font-size:13px; color:#e2e8f0; line-height:1.6; margin-bottom:18px; border:1px solid rgba(255,255,255,0.08);">
            <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px;">
                <span style="background:#0284c7; color:#fff; min-width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; margin-top:1px;">1</span>
                <span>Look at the right of the address bar for the <strong>App available (⊕)</strong> icon.</span>
            </div>
            <div style="display:flex; align-items:flex-start; gap:12px;">
                <span style="background:#0284c7; color:#fff; min-width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; margin-top:1px;">2</span>
                <span>Or click <strong>⋯ (Settings &amp; More)</strong> &rarr; <strong>Apps</strong> &rarr; <strong>'Install this site as an app'</strong>.</span>
            </div>
        </div>
        <button type="button" onclick="closeInstallModals()" style="width:100%; padding:13px; border-radius:12px; background:linear-gradient(135deg, #0284c7, #0ea5e9); color:#fff; font-weight:700; border:none; cursor:pointer; font-size:14px;">Got It</button>
    </div>
</div>

<!-- 4. Dedicated Android Fallback Modal (POCO, Infinix, Direct APK Download) -->
<div id="androidInstallModal" class="pwa-install-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); padding:16px; box-sizing:border-box;">
    <div style="background:#0f172a; color:#ffffff; width:100%; max-width:440px; border-radius:24px; padding:24px 20px; border:1px solid rgba(255,255,255,0.15); box-shadow:0 24px 48px rgba(0,0,0,0.6); text-align:center; font-family:'Plus Jakarta Sans', sans-serif; position:relative;">
        <button type="button" onclick="closeInstallModals()" style="position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.08); border:none; width:32px; height:32px; border-radius:50%; color:#94a3b8; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
        <div style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #10439f, #2563eb); margin:0 auto 12px; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(16,67,159,0.4);">
            <svg viewBox="0 0 24 24" width="28" height="28" stroke="#fff" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px; color:#ffffff;">Install on Android</h3>
        <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:16px;">Fastest options for your Android device:</p>

        <!-- Direct APK Download Button -->
        <a href="/api/download_app.php" download="TDS_Attendance.apk" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:13px 14px; background:linear-gradient(135deg, #10439f, #2563eb); color:#ffffff; text-decoration:none; border-radius:12px; font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(37,99,235,0.4); box-sizing:border-box; margin-bottom:12px;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>⚡ Download Android App (.apk)</span>
        </a>

        <!-- Chrome Shortcut Steps -->
        <div style="background:rgba(255,255,255,0.06); border-radius:14px; padding:12px 14px; text-align:left; font-size:12.5px; color:#e2e8f0; line-height:1.55; margin-bottom:12px; border:1px solid rgba(255,255,255,0.08);">
            <div style="font-weight:700; color:#38bdf8; margin-bottom:6px;">Or via Chrome 3-dot Menu:</div>
            1. Tap <strong>three dots (⋮)</strong> at top right of Chrome.<br>
            2. <strong>Scroll DOWN</strong> past <em>'Translate'</em>.<br>
            3. Tap <strong>'Install app'</strong> or <strong>'Add to Home screen'</strong>.
        </div>

        <!-- POCO Setting Note -->
        <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:12px; padding:10px 12px; font-size:11.5px; color:#fde68a; line-height:1.45; text-align:left; margin-bottom:14px;">
            <strong style="color:#fbbf24;">POCO/Xiaomi:</strong> Turn off 'Lock Home screen layout' in Home screen settings if shortcut does not appear.
        </div>

        <button type="button" onclick="closeInstallModals()" style="width:100%; padding:11px; border-radius:12px; background:rgba(255,255,255,0.1); color:#fff; font-weight:700; border:1px solid rgba(255,255,255,0.15); cursor:pointer; font-size:13px;">Close</button>
    </div>
</div>

<!-- 5. Dedicated Desktop Fallback Modal (Chrome/Edge Address Bar) -->
<div id="desktopInstallModal" class="pwa-install-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); padding:16px; box-sizing:border-box;">
    <div style="background:#0f172a; color:#ffffff; width:100%; max-width:420px; border-radius:24px; padding:24px 20px; border:1px solid rgba(255,255,255,0.15); box-shadow:0 24px 48px rgba(0,0,0,0.6); text-align:center; font-family:'Plus Jakarta Sans', sans-serif;">
        <div style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #10439f, #2563eb); margin:0 auto 12px; display:flex; align-items:center; justify-content:center; font-size:24px;">
            💻
        </div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px; color:#ffffff;">Install on Computer</h3>
        <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:18px;">Install TDS Attendance directly from your browser:</p>
        <div style="background:rgba(255,255,255,0.06); border-radius:14px; padding:14px 16px; text-align:left; font-size:13px; color:#e2e8f0; line-height:1.6; margin-bottom:18px; border:1px solid rgba(255,255,255,0.08);">
            1. Look at the browser address bar at the top-right.<br>
            2. Click the <strong>Install</strong> icon (computer with down arrow or ⊕).<br>
            3. Click <strong>'Install'</strong> to create the desktop app.
        </div>
        <button type="button" onclick="closeInstallModals()" style="width:100%; padding:13px; border-radius:12px; background:linear-gradient(135deg, #10439f, #2563eb); color:#fff; font-weight:700; border:none; cursor:pointer; font-size:14px;">Got It</button>
    </div>
</div>

<script>
window.closeInstallModals = function() {
    var modals = document.querySelectorAll('.pwa-install-modal');
    modals.forEach(function(m) { m.style.display = 'none'; });
};
window.closeIosInstallModal = window.closeInstallModals;
window.closeSmartInstallModal = window.closeInstallModals;

window.triggerTDSInstall = async function(e) {
    if (e && e.preventDefault) e.preventDefault();

    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) {
        alert("The TDS Attendance App is already installed and running on this device!");
        return;
    }

    // 1. IMMEDIATE 1-TAP NATIVE PROMPT (Android Chrome, Android Edge, Samsung Internet, Windows/Mac Chrome & Edge)
    var promptEvt = window.TDS_InstallPrompt || window.deferredInstallPrompt;
    if (promptEvt) {
        try {
            promptEvt.prompt();
            var choice = await promptEvt.userChoice;
            if (choice && choice.outcome === 'accepted') {
                window.TDS_InstallPrompt = null;
                window.deferredInstallPrompt = null;
                var btns = [
                    document.getElementById('desktopInstallAppBtn'),
                    document.getElementById('mobileHeaderInstallBtn'),
                    document.getElementById('mobileDrawerInstallBtn')
                ];
                btns.forEach(function(b) { if (b) b.style.display = 'none'; });
                var badge = document.getElementById('desktopAppInstalledBadge');
                if (badge) badge.style.display = 'block';
            }
            return;
        } catch (err) {
            console.error("Native install prompt execution:", err);
        }
    }

    // 2. IPHONE / IPAD (iOS Safari) — The original Safari bottom sheet
    var isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    if (isIos) {
        var iosModal = document.getElementById('iosInstallModal');
        if (iosModal) {
            iosModal.style.display = 'flex';
            return;
        }
    }

    // 3. MOZILLA FIREFOX (Android & Desktop)
    var isFirefox = /firefox/i.test(navigator.userAgent);
    if (isFirefox) {
        var ffModal = document.getElementById('firefoxInstallModal');
        if (ffModal) {
            ffModal.style.display = 'flex';
            return;
        }
    }

    // 4. MICROSOFT EDGE (Fallback if prompt was dismissed)
    var isEdge = /edg/i.test(navigator.userAgent);
    if (isEdge) {
        var edgeModal = document.getElementById('edgeInstallModal');
        if (edgeModal) {
            edgeModal.style.display = 'flex';
            return;
        }
    }

    // 5. ANDROID FALLBACK (POCO / Infinix / or where native prompt wasn't fired)
    var isAndroid = /android/i.test(navigator.userAgent);
    if (isAndroid) {
        var androidModal = document.getElementById('androidInstallModal');
        if (androidModal) {
            androidModal.style.display = 'flex';
            return;
        }
    }

    // 6. DESKTOP GENERAL FALLBACK (Chrome on PC/Mac)
    var deskModal = document.getElementById('desktopInstallModal');
    if (deskModal) {
        deskModal.style.display = 'flex';
        return;
    }
};

window.openSmartInstallModal = window.triggerTDSInstall;
</script>
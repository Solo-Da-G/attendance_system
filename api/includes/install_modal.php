<!-- Unified Smart App Install Modal (Android APK, Chrome PWA, iOS Safari, Desktop) -->
<div id="smartAppInstallModal" class="smart-install-modal" style="display:none; position:fixed; inset:0; background:rgba(6,13,31,0.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="smart-install-card" style="background:#0f172a; color:#f8fafc; border-radius:24px; padding:24px 20px; max-width:440px; width:100%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 60px -15px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.12); position:relative; text-align:center; font-family:'Plus Jakarta Sans', sans-serif; box-sizing:border-box;">
        <!-- Close Button -->
        <button type="button" onclick="closeSmartInstallModal()" style="position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.08); border:none; width:34px; height:34px; border-radius:50%; color:#94a3b8; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='#94a3b8';">&times;</button>
        
        <!-- Header Brand Icon -->
        <div style="width:60px; height:60px; background:#ffffff; border-radius:18px; margin:0 auto 12px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(16,67,159,0.35); padding:6px; box-sizing:border-box;">
            <img src="/asset/img/tds_logo.png" alt="TDS Logo" style="width:100%; height:100%; object-fit:contain;">
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#ffffff; margin:0 0 4px; letter-spacing:-0.3px;">Install TDS Attendance App</h3>
        <p style="font-size:12.5px; color:#94a3b8; margin:0 0 16px; line-height:1.4;">Fast 1-tap access on your mobile device</p>

        <!-- Segmented Platform Tabs -->
        <div style="display:flex; background:rgba(255,255,255,0.06); padding:4px; border-radius:14px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08); gap:4px;">
            <button type="button" id="tabBtnAndroid" onclick="switchInstallTab('android')" style="flex:1; padding:9px 6px; border:none; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer; transition:all 0.2s; background:#10439f; color:#ffffff; display:flex; align-items:center; justify-content:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.551 0 .9993.4482.9993.9993.0001.5511-.4482.9997-.9993.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993 0 .5511-.4482.9997-.9993.9997m11.4045-6.02l1.996-3.4572c.1123-.1944.0455-.4435-.149-.5557-.1944-.1123-.4435-.0456-.5557.1489l-2.023 3.504c-1.4883-.6803-3.1504-1.0664-4.9308-1.0664s-3.4425.3861-4.9308 1.0664l-2.023-3.504c-.1122-.1945-.3613-.2612-.5557-.1489-.1945.1122-.2613.3613-.149.5557l1.996 3.4572C4.1687 10.4283 2.5 13.0645 2.5 16.1436h19c0-3.0791-1.6687-5.7153-4.6185-6.8222"/></svg>
                Android
            </button>
            <button type="button" id="tabBtnIos" onclick="switchInstallTab('ios')" style="flex:1; padding:9px 6px; border:none; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer; transition:all 0.2s; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.42c.67-.82 1.13-1.96.99-3.11-1 .04-2.14.65-2.82 1.45-.6.69-1.12 1.83-.98 2.96 1.12.09 2.19-.55 2.81-1.3"/></svg>
                iPhone
            </button>
            <button type="button" id="tabBtnDesktop" onclick="switchInstallTab('desktop')" style="flex:1; padding:9px 6px; border:none; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer; transition:all 0.2s; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                PC / Laptop
            </button>
        </div>

        <!-- Android Pane -->
        <div id="paneAndroid" class="install-tab-pane" style="display:block; text-align:left;">
            <!-- Primary Option: Direct APK Download -->
            <div style="background:linear-gradient(135deg, rgba(16,67,159,0.35) 0%, rgba(37,99,235,0.2) 100%); border:1.5px solid #2563eb; border-radius:16px; padding:15px; margin-bottom:12px; position:relative; overflow:hidden;">
                <div style="position:absolute; top:10px; right:12px; background:#10b981; color:#fff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:0.5px;">Recommended</div>
                <div style="font-size:13.5px; font-weight:800; color:#ffffff; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                    ⚡ Direct Android App (.apk)
                </div>
                <p style="font-size:12px; color:#cbd5e1; margin:0 0 12px; line-height:1.45;">
                    100% guaranteed on all Android phones (POCO, Infinix, Tecno, Xiaomi, Samsung). Installs native app with zero Chrome menu issues.
                </p>
                <a href="/api/download_app.php" download="TDS_Attendance.apk" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:11px 14px; background:linear-gradient(135deg, #10439f, #2563eb); color:#ffffff; text-decoration:none; border-radius:11px; font-weight:800; font-size:13px; box-shadow:0 4px 14px rgba(37,99,235,0.4); box-sizing:border-box;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Android App (.apk)
                </a>
            </div>

            <!-- Option 2: Browser PWA / Add to Home screen -->
            <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:13px; margin-bottom:12px;">
                <div style="font-size:12.5px; font-weight:700; color:#e2e8f0; margin-bottom:10px;">
                    Or Add to Home screen via Chrome:
                </div>
                <div style="font-size:12px; color:#cbd5e1; line-height:1.6;">
                    <div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:8px;">
                        <span style="background:#1e293b; color:#38bdf8; min-width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; border:1px solid #334155;">1</span>
                        <span>Tap the <strong>three dots (⋮)</strong> in the top-right corner of Chrome.</span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:8px;">
                        <span style="background:#1e293b; color:#38bdf8; min-width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; border:1px solid #334155;">2</span>
                        <span><strong>Scroll DOWN</strong> past <em>'Translate'</em> (swipe up on the menu).</span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="background:#1e293b; color:#38bdf8; min-width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; border:1px solid #334155;">3</span>
                        <span>Tap <strong>'Install app'</strong> or <strong>'Add to Home screen'</strong>.</span>
                    </div>
                </div>
            </div>

            <!-- POCO & Infinix Special Troubleshooting Note -->
            <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:12px; padding:11px 13px; font-size:11.5px; color:#fde68a; line-height:1.5;">
                <strong style="color:#fbbf24;">⚠️ POCO / Xiaomi / Infinix Users:</strong><br>
                If the icon doesn't appear on your screen: Long-press your phone Home screen &rarr; <strong>Settings</strong> &rarr; Turn OFF <strong>'Lock Home screen layout'</strong>. Under Phone Settings &rarr; Apps &rarr; Chrome, allow <strong>'Home screen shortcuts'</strong>.
            </div>
        </div>

        <!-- iOS Pane -->
        <div id="paneIos" class="install-tab-pane" style="display:none; text-align:left;">
            <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:16px; font-size:12.5px; color:#e2e8f0; line-height:1.65;">
                <div style="font-size:13.5px; font-weight:800; color:#ffffff; margin-bottom:12px;">
                    Install in Safari (2 quick taps):
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:12px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">1</span>
                    <span>Tap the <strong>Share</strong> button <svg style="vertical-align:middle; display:inline; margin:0 2px;" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.3"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> at the bottom of Safari.</span>
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:12px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">2</span>
                    <span>Scroll down and tap <strong>'Add to Home Screen'</strong> (➕).</span>
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">3</span>
                    <span>Tap <strong>'Add'</strong> in the top-right corner.</span>
                </div>
            </div>
        </div>

        <!-- Desktop Pane -->
        <div id="paneDesktop" class="install-tab-pane" style="display:none; text-align:left;">
            <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:16px; font-size:12.5px; color:#e2e8f0; line-height:1.65;">
                <div style="font-size:13.5px; font-weight:800; color:#ffffff; margin-bottom:12px;">
                    Install on Chrome or Edge:
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:12px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">1</span>
                    <span>Look at the browser address bar at the top-right.</span>
                </div>
                <div style="display:flex; align-items:flex-start; gap:10px;">
                    <span style="background:#10439f; color:#fff; min-width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">2</span>
                    <span>Click the <strong>Install</strong> icon (computer with down arrow or ⊕) and confirm <strong>'Install'</strong>.</span>
                </div>
            </div>
        </div>

        <button type="button" onclick="closeSmartInstallModal()" style="width:100%; margin-top:16px; padding:12px; border-radius:12px; background:rgba(255,255,255,0.1); color:#ffffff; font-weight:700; border:1px solid rgba(255,255,255,0.15); cursor:pointer; font-size:13px; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">Close</button>
    </div>
</div>

<script>
window.openSmartInstallModal = function(preferTab) {
    var modal = document.getElementById('smartAppInstallModal');
    if (!modal) return;
    var isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    var isAndroid = /android/i.test(navigator.userAgent);
    var targetTab = preferTab || (isIos ? 'ios' : (isAndroid ? 'android' : 'desktop'));
    if (typeof window.switchInstallTab === 'function') {
        window.switchInstallTab(targetTab);
    }
    modal.style.display = 'flex';
};

window.closeSmartInstallModal = function() {
    var modal = document.getElementById('smartAppInstallModal');
    if (modal) modal.style.display = 'none';
};
window.closeIosInstallModal = window.closeSmartInstallModal;

window.switchInstallTab = function(tabName) {
    ['android', 'ios', 'desktop'].forEach(function(t) {
        var pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
        var btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        if (pane) pane.style.display = (t === tabName) ? 'block' : 'none';
        if (btn) {
            if (t === tabName) {
                btn.style.background = '#10439f';
                btn.style.color = '#ffffff';
                btn.style.boxShadow = '0 2px 8px rgba(16,67,159,0.4)';
            } else {
                btn.style.background = 'transparent';
                btn.style.color = '#94a3b8';
                btn.style.boxShadow = 'none';
            }
        }
    });
};
</script>
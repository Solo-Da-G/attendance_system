/**
 * Auto sign-out after period of inactivity (dashboard).
 * Set to 3 minutes (180 seconds). Shows warning modal at 170 seconds.
 */
(function () {
  const IDLE_MS = 180 * 1000;
  const WARNING_MS = 170 * 1000;
  const LOGOUT_URL = 'logout.php';

  let lastActivity = Date.now();
  let paused = false;
  let warningShown = false;
  let countdownInterval = null;

  function logout() {
    window.location.href = LOGOUT_URL + '?reason=idle';
  }

  function showWarning() {
    if (warningShown) return;
    warningShown = true;
    
    // Create and show modal
    const modal = document.createElement('div');
    modal.id = 'idleWarningModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.8);backdrop-filter:blur(8px);z-index:999999;display:flex;align-items:center;justify-content:center;';
    
    modal.innerHTML = `
      <div style="background:white;padding:30px;border-radius:24px;text-align:center;max-width:400px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);transform:scale(0.9);animation:popIn 0.3s forwards;">
        <div style="font-size:48px;margin-bottom:15px;">⏳</div>
        <h2 style="margin:0 0 10px;font-size:24px;color:#0f172a;font-weight:800;">Are you still there?</h2>
        <p style="color:#64748b;margin:0 0 20px;font-size:15px;line-height:1.5;">You have been idle for a while. For your security, you will be automatically logged out in <strong id="idleCountdown" style="color:#ef4444;font-size:18px;">10</strong> seconds.</p>
        <button id="stayLoggedInBtn" style="background:var(--primary);color:white;border:none;padding:12px 30px;border-radius:12px;font-weight:700;font-size:16px;cursor:pointer;width:100%;transition:all 0.2s;">Stay Logged In</button>
      </div>
      <style>@keyframes popIn { to { transform:scale(1); } }</style>
    `;
    
    document.body.appendChild(modal);
    
    let timeLeft = 10;
    const countSpan = document.getElementById('idleCountdown');
    
    countdownInterval = setInterval(() => {
      timeLeft--;
      if (countSpan) countSpan.textContent = Math.max(0, timeLeft);
      if (timeLeft <= 0) {
        clearInterval(countdownInterval);
        logout();
      }
    }, 1000);

    document.getElementById('stayLoggedInBtn').addEventListener('click', () => {
      document.body.removeChild(modal);
      warningShown = false;
      clearInterval(countdownInterval);
      lastActivity = Date.now();
    });
  }

  function onActivity(e) {
    if (!paused && !warningShown) {
      lastActivity = Date.now();
    }
  }

  window.pauseIdleLogout = function () {
    paused = true;
  };

  window.resumeIdleLogout = function () {
    paused = false;
    lastActivity = Date.now();
  };

  ['mousedown', 'keydown', 'touchstart', 'scroll', 'click', 'mousemove'].forEach(function (evt) {
    document.addEventListener(evt, onActivity, { passive: true });
  });

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !paused) {
      const elapsed = Date.now() - lastActivity;
      if (elapsed >= IDLE_MS) logout();
      else if (elapsed >= WARNING_MS) showWarning();
    }
  });

  setInterval(function () {
    if (!paused) {
      const elapsed = Date.now() - lastActivity;
      if (elapsed >= IDLE_MS) {
        logout();
      } else if (elapsed >= WARNING_MS && !warningShown) {
        showWarning();
      }
    }
  }, 1000);
})();

/**
 * Auto sign-out after period of inactivity (dashboard).
 */
(function () {
  const IDLE_MS = 60 * 1000;
  const LOGOUT_URL = 'logout.php';

  let lastActivity = Date.now();
  let paused = false;

  function logout() {
    window.location.href = LOGOUT_URL + '?reason=idle';
  }

  function onActivity() {
    if (!paused) lastActivity = Date.now();
  }

  window.pauseIdleLogout = function () {
    paused = true;
  };

  window.resumeIdleLogout = function () {
    paused = false;
    lastActivity = Date.now();
  };

  ['mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (evt) {
    document.addEventListener(evt, onActivity, { passive: true });
  });

  document.addEventListener('mousemove', onActivity, { passive: true });

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !paused && Date.now() - lastActivity >= IDLE_MS) {
      logout();
    }
  });

  setInterval(function () {
    if (!paused && Date.now() - lastActivity >= IDLE_MS) {
      logout();
    }
  }, 1000);
})();

/**
 * Auto sign-out on idle is disabled.
 * The application remains logged in indefinitely until the user manually logs out.
 */
(function () {
  // Safe no-op stubs so any existing calls do not error
  window.pauseIdleLogout = function () {};
  window.resumeIdleLogout = function () {};
})();

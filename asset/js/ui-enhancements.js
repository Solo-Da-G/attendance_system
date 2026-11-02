/**
 * Small UI animations/enhancements (non-blocking).
 * - Reveals key cards/tables on scroll
 * - Subtle tilt on login card
 */
(function () {
  function ready(fn) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  ready(function () {
    // Auto-mark common UI blocks as reveal targets
    var autoRevealSelectors = [
      ".dashboard-header",
      ".dashboard-widgets",
      ".widget-card",
      ".clocking-card",
      ".recent-table",
      ".password-hint",
      ".add-btn",
      "table",
      ".table-card",
    ];

    autoRevealSelectors.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        if (!el.classList.contains("reveal")) el.classList.add("reveal");
      });
    });

    // Reveal on scroll (IntersectionObserver)
    var els = Array.prototype.slice.call(document.querySelectorAll(".reveal"));
    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add("is-visible");
              io.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.12 }
      );
      els.forEach(function (el) {
        io.observe(el);
      });
    } else {
      // Fallback: show all
      els.forEach(function (el) {
        el.classList.add("is-visible");
      });
    }

    // Subtle tilt effect for login card only
    var loginCard = document.querySelector(".login-container");
    if (loginCard) {
      var raf = 0;
      var rect = null;

      function onMove(e) {
        if (raf) return;
        raf = window.requestAnimationFrame(function () {
          raf = 0;
          rect = rect || loginCard.getBoundingClientRect();
          var x = (e.clientX - rect.left) / rect.width - 0.5;
          var y = (e.clientY - rect.top) / rect.height - 0.5;
          var rx = (-y * 5).toFixed(2);
          var ry = (x * 6).toFixed(2);
          loginCard.style.transform = "translateY(-2px) rotateX(" + rx + "deg) rotateY(" + ry + "deg)";
        });
      }

      function onLeave() {
        rect = null;
        loginCard.style.transform = "";
      }

      loginCard.addEventListener("mousemove", onMove);
      loginCard.addEventListener("mouseleave", onLeave);
    }
  });

  // Global helper for password show/hide toggling
  window.togglePassVisibility = function (inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    var svg = btn ? btn.querySelector('svg') : null;
    if (svg) {
      if (isPass) {
        svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        btn.style.color = '#2563eb';
        btn.setAttribute('aria-label', 'Hide password');
        btn.setAttribute('title', 'Hide password');
      } else {
        svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        btn.style.color = '#94a3b8';
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('title', 'Show password');
      }
    }
  };
})();


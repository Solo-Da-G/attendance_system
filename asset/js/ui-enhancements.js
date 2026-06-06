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
})();


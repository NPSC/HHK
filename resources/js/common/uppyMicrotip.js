import "microtip/microtip.css";

var started = false;

/**
 * Several Uppy plugins (ImageEditor, StatusBar) set aria-label and
 * data-microtip-position on their buttons but never role="tooltip" - which
 * every microtip.css rule requires to match. This adds it so locale strings
 * actually render as hover tooltips. Safe to call from multiple Uppy
 * instances; the observer is only started once per page.
 */
export function enableUppyTooltips() {
  if (started) {
    return;
  }
  started = true;

  new MutationObserver(function () {
    document.querySelectorAll("[data-microtip-position]:not([role])").forEach(function (el) {
      el.setAttribute("role", "tooltip");
    });
  }).observe(document.body, { childList: true, subtree: true });
}

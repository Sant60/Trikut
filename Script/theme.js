document.addEventListener("DOMContentLoaded", () => {
  const root = document.documentElement;
  const pageBody = document.body;
  const themeToggles = Array.from(document.querySelectorAll("[data-theme-toggle]"));

  function getStorageKey(toggle) {
    return toggle?.dataset.themeStorage || "trikut-theme";
  }

  function getStoredTheme(storageKey) {
    try {
      const savedTheme = window.localStorage.getItem(storageKey);
      return savedTheme === "dark" ? "dark" : "light";
    } catch (e) {
      return "light";
    }
  }

  function applyTheme(theme) {
    root.setAttribute("data-theme", theme);
    pageBody?.setAttribute("data-theme", theme);
    pageBody?.classList.toggle("theme-dark", theme === "dark");
  }

  function syncToggle(toggle, theme) {
    const nextTheme = theme === "dark" ? "light" : "dark";
    const isDark = theme === "dark";
    const activeLabel = isDark ? "Dark" : "Light";
    let label = toggle.querySelector(".theme-toggle-label");
    if (!label) {
      label = document.createElement("span");
      label.className = "theme-toggle-label";
      toggle.appendChild(label);
    }
    label.textContent = activeLabel;
    toggle.setAttribute("aria-pressed", String(isDark));
    toggle.setAttribute("aria-label", "Switch to " + nextTheme + " mode");
    toggle.setAttribute("title", "Switch to " + nextTheme + " mode");
  }

  if (themeToggles.length === 0) {
    return;
  }

  const activeStorageKey = getStorageKey(themeToggles[0]);
  applyTheme(getStoredTheme(activeStorageKey));
  themeToggles.forEach((toggle) => syncToggle(toggle, getStoredTheme(getStorageKey(toggle))));

  themeToggles.forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const storageKey = getStorageKey(toggle);
      const currentTheme = getStoredTheme(storageKey);
      const nextTheme = currentTheme === "dark" ? "light" : "dark";

      try {
        window.localStorage.setItem(storageKey, nextTheme);
      } catch (e) {}

      applyTheme(nextTheme);
      themeToggles.forEach((button) => syncToggle(button, nextTheme));
    });
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const forms = Array.from(document.querySelectorAll("[data-validate-form]"));

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }

    return String(value).replace(/[^a-zA-Z0-9\-_]/g, "\\$&");
  }

  function getFieldErrorClass(input) {
    return input.closest(".admin-field") ? "field-error" : "error-msg";
  }

  function getExistingError(input) {
    const errorClass = getFieldErrorClass(input);
    return input.parentElement?.querySelector("." + errorClass) || null;
  }

  function getExistingSuccess(input) {
    return input.parentElement?.querySelector(".success-msg") || null;
  }

  function clearFieldError(input) {
    input.classList.remove("input-error");
    input.classList.remove("input-valid");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");
    const existing = getExistingError(input);
    if (existing) {
      existing.remove();
    }
    const success = getExistingSuccess(input);
    if (success) {
      success.remove();
    }
  }

  function showFieldError(input, message) {
    clearFieldError(input);
    input.classList.add("input-error");
    input.setAttribute("aria-invalid", "true");

    const error = document.createElement("div");
    error.className = getFieldErrorClass(input);
    error.id = (input.id || input.name || "field") + "-error";
    error.setAttribute("role", "alert");
    error.textContent = message;
    input.parentElement?.appendChild(error);
    input.setAttribute("aria-describedby", error.id);
  }

  function showFieldSuccess(input, message) {
    clearFieldError(input);
    input.classList.add("input-valid");
    const success = document.createElement("div");
    success.className = "success-msg";
    success.textContent = message;
    input.parentElement?.appendChild(success);
  }

  function normalizePhoneValue(value) {
    let normalized = String(value || "").replace(/[^\d+]/g, "");

    if (normalized.startsWith("+91")) {
      normalized = normalized.slice(3);
    } else if (normalized.startsWith("91") && normalized.length > 10) {
      normalized = normalized.slice(2);
    }

    normalized = normalized.replace(/\D/g, "");
    return normalized.slice(0, 10);
  }

  function isFakePhone(value) {
    return (
      value === "9999999999" ||
      value === "1234567890" ||
      value === "0000000000" ||
      /^(\d)\1{9}$/.test(value)
    );
  }

  function sanitizeValue(input) {
    if (input.dataset.rule === "phone") {
      input.value = normalizePhoneValue(input.value);
    }

    if (input.dataset.rule === "name") {
      input.value = input.value.replace(/[^\p{L}\s'.-]+/gu, "");
    }

    if (input.dataset.rule === "price") {
      let value = input.value.replace(/[^\d.]+/g, "");
      const firstDot = value.indexOf(".");
      if (firstDot !== -1) {
        value =
          value.slice(0, firstDot + 1) +
          value
            .slice(firstDot + 1)
            .replace(/\./g, "")
            .slice(0, 2);
      }
      input.value = value;
    }
  }

  function validateField(input) {
    if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement)) {
      return true;
    }

    clearFieldError(input);
    const value = input.value.trim();

    if (input.disabled || input.type === "hidden") {
      return true;
    }

    if (input.required && value === "") {
      showFieldError(input, "This field is required.");
      return false;
    }

    if (input.tagName === "SELECT" && input.required && value === "") {
      showFieldError(input, "Please select an option.");
      return false;
    }

    if (input.type === "email" && value !== "") {
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
      if (!emailPattern.test(value)) {
        showFieldError(input, "Enter a valid email address.");
        return false;
      }
    }

    if (input.dataset.rule === "phone" && value !== "") {
      const normalizedPhone = normalizePhoneValue(value);
      input.value = normalizedPhone;

      if (!/^[6-9]\d{9}$/.test(normalizedPhone)) {
        showFieldError(input, "Enter a valid 10-digit phone number.");
        return false;
      }

      if (isFakePhone(normalizedPhone)) {
        showFieldError(input, "Enter a real mobile number.");
        return false;
      }

      showFieldSuccess(input, "Mobile number looks valid.");
    }

    if (input.dataset.rule === "name" && value !== "") {
      if (!/^[\p{L}][\p{L}\s'.-]{1,99}$/u.test(value)) {
        showFieldError(input, "Use at least 2 valid characters.");
        return false;
      }
    }

    if (input.dataset.rule === "username" && value !== "") {
      if (!/^[A-Za-z0-9_.-]{3,100}$/.test(value)) {
        showFieldError(input, "Use 3 to 100 letters, numbers, dots, underscores, or hyphens.");
        return false;
      }
    }

    if (input.dataset.rule === "password" && value !== "") {
      if (value.length < 8) {
        showFieldError(input, "Use at least 8 characters.");
        return false;
      }

      if (!/[A-Z]/.test(value) || !/[a-z]/.test(value) || !/\d/.test(value)) {
        showFieldError(input, "Include uppercase, lowercase, and a number.");
        return false;
      }
    }

    if (input.dataset.rule === "price" && value !== "") {
      if (!/^\d+(?:\.\d{1,2})?$/.test(value)) {
        showFieldError(input, "Enter a valid amount.");
        return false;
      }
    }

    if (input.type === "url" && value !== "") {
      try {
        const url = new URL(value, window.location.origin);
        if (!/^https?:$/i.test(url.protocol) && !value.startsWith("/")) {
          throw new Error("invalid");
        }
      } catch (error) {
        showFieldError(input, "Enter a valid URL or local path.");
        return false;
      }
    }

    if (input.type === "datetime-local" && value !== "") {
      const selected = new Date(value);
      if (Number.isNaN(selected.getTime())) {
        showFieldError(input, "Select a valid date and time.");
        return false;
      }

      if (selected.getTime() < Date.now() - 60000) {
        showFieldError(input, "Please choose a future date and time.");
        return false;
      }
    }

    if (input.type === "number" && value !== "") {
      const numericValue = Number(value);
      const min = input.min !== "" ? Number(input.min) : null;
      const max = input.max !== "" ? Number(input.max) : null;

      if (Number.isNaN(numericValue)) {
        showFieldError(input, "Enter a valid number.");
        return false;
      }

      if (min !== null && numericValue < min) {
        showFieldError(input, "Value is below the allowed minimum.");
        return false;
      }

      if (max !== null && numericValue > max) {
        showFieldError(input, "Value is above the allowed maximum.");
        return false;
      }
    }

    if (input instanceof HTMLInputElement && input.type === "file" && input.files && input.files.length > 0) {
      const maxFileSize = Number(input.dataset.maxFileSize || 5242880);
      for (const file of Array.from(input.files)) {
        if (file.size > maxFileSize) {
          showFieldError(input, "Each file must be 5 MB or smaller.");
          return false;
        }
      }
    }

    if (input.dataset.match && value !== "") {
      const target = document.getElementById(input.dataset.match) || input.form?.querySelector(`[name="${input.dataset.match}"]`);
      if (target && value !== target.value) {
        showFieldError(input, "This value must match.");
        return false;
      }
    }

    return true;
  }

  function validateCompositeRequirements(form) {
    const pairs = Array.from(form.querySelectorAll("[data-require-one]"));
    const checkedGroups = new Set();

    for (const field of pairs) {
      const group = field.getAttribute("data-require-one");
      if (!group || checkedGroups.has(group)) {
        continue;
      }

      checkedGroups.add(group);
      const groupFields = Array.from(
        form.querySelectorAll("[data-require-one=\"" + escapeSelector(group) + "\"]")
      );

      const hasValue = groupFields.some((item) => {
        if (item instanceof HTMLInputElement && item.type === "file") {
          return item.files && item.files.length > 0;
        }
        return item.value.trim() !== "";
      });

      if (!hasValue) {
        const first = groupFields[0];
        showFieldError(first, "Provide at least one option in this section.");
        return false;
      }
    }

    return true;
  }

  function syncSubmitState(form) {
    const submitButtons = Array.from(
      form.querySelectorAll('button[type="submit"], input[type="submit"]')
    );
    const fields = Array.from(
      form.querySelectorAll("input, textarea, select")
    ).filter((field) => field.type !== "hidden");

    const valid =
      fields.every((field) => validateField(field)) &&
      validateCompositeRequirements(form);

    submitButtons.forEach((button) => {
      button.disabled = !valid;
      button.setAttribute("aria-disabled", String(!valid));
    });
  }

  forms.forEach((form) => {
    const fields = Array.from(form.querySelectorAll("input, textarea, select"));

    fields.forEach((field) => {
      if (field.type === "hidden") {
        return;
      }

      field.addEventListener("input", () => {
        sanitizeValue(field);
        validateField(field);
        syncSubmitState(form);
      });

      field.addEventListener("blur", () => {
        validateField(field);
        syncSubmitState(form);
      });
    });

    form.addEventListener("submit", (event) => {
      let firstInvalid = null;

      for (const field of fields) {
        if (field.type === "hidden") {
          continue;
        }

        sanitizeValue(field);
        const valid = validateField(field);
        if (!valid && !firstInvalid) {
          firstInvalid = field;
        }
      }

      const validComposite = validateCompositeRequirements(form);
      if (!validComposite && !firstInvalid) {
        firstInvalid = form.querySelector(".input-error");
      }

      if (firstInvalid || !validComposite) {
        event.preventDefault();
        firstInvalid?.focus();
      }
    });

    syncSubmitState(form);
  });
});

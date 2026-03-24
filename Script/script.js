document.addEventListener("DOMContentLoaded", () => {
  const cart = [];
  const cartItemsEl = document.getElementById("cartItems");
  const cartTotalEl = document.getElementById("cartTotal");
  const orderBtn = document.getElementById("orderNowBtn");
  const modal = document.getElementById("imgModal");
  const modalImg = document.getElementById("modalImg");
  const modalClose = document.querySelector(".modal-close");

  const bookingForm = document.getElementById("bookingForm");
  const bookingDate = document.getElementById("bookDate");
  const customerName = document.getElementById("custName");
  const customerPhone = document.getElementById("custPhone");
  const deliveryType = document.getElementById("deliveryType");
  const navToggle = document.querySelector(".nav-toggle");
  const mainNav = document.getElementById("primaryNav");
  const navBackdrop = document.querySelector(".nav-backdrop");
  const mobileNavQuery = window.matchMedia("(max-width: 1024px)");
  const bookingInvoiceUrl = document.body.dataset.bookingInvoice || "";
  const csrfToken = document.body.dataset.csrfToken || "";

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

  function setNavOpen(isOpen) {
    if (!navToggle || !mainNav) return;
    navToggle.setAttribute("aria-expanded", String(isOpen));
    mainNav.classList.toggle("is-open", isOpen);
    document.body.classList.toggle("nav-open", isOpen && mobileNavQuery.matches);
  }

  function syncNavState(forceClosed = false) {
    if (!navToggle || !mainNav) return;

    if (!mobileNavQuery.matches) {
      setNavOpen(false);
      return;
    }

    if (forceClosed) {
      setNavOpen(false);
    }
  }

  if (navToggle && mainNav) {
    navToggle.addEventListener("click", () => {
      const isOpen = navToggle.getAttribute("aria-expanded") === "true";
      setNavOpen(!isOpen);
    });

    if (typeof mobileNavQuery.addEventListener === "function") {
      mobileNavQuery.addEventListener("change", () => syncNavState());
    } else if (typeof mobileNavQuery.addListener === "function") {
      mobileNavQuery.addListener(() => syncNavState());
    }

    syncNavState();
  }

  if (bookingDate) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    bookingDate.min = now.toISOString().slice(0, 16);
  }

  if (bookingInvoiceUrl) {
    window.open(bookingInvoiceUrl, "_blank", "noopener");
    if (window.history && typeof window.history.replaceState === "function") {
      const cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete("booking_invoice");
      window.history.replaceState({}, "", cleanUrl.toString());
    }
  }

  function formatCurrency(value) {
    return "\u20b9" + Number(value).toFixed(2);
  }

  function clearError(input) {
    if (!input) return;
    input.classList.remove("input-error");
    input.classList.remove("input-valid");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");
    const errorEl = input.parentElement?.querySelector(".error-msg");
    if (errorEl) errorEl.remove();
    const successEl = input.parentElement?.querySelector(".success-msg");
    if (successEl) successEl.remove();
  }

  function showError(input, message) {
    if (!input) return;
    clearError(input);
    input.classList.add("input-error");
    input.setAttribute("aria-invalid", "true");
    const errorEl = document.createElement("div");
    errorEl.className = "error-msg";
    errorEl.id =
      (input.id || input.name || "field") +
      "-error";
    errorEl.setAttribute("role", "alert");
    errorEl.textContent = message;
    input.parentElement?.appendChild(errorEl);
    input.setAttribute("aria-describedby", errorEl.id);
  }

  function showSuccess(input, message) {
    if (!input) return;
    clearError(input);
    input.classList.add("input-valid");
    const successEl = document.createElement("div");
    successEl.className = "success-msg";
    successEl.textContent = message;
    input.parentElement?.appendChild(successEl);
  }

  function updateOrderButtonState() {
    if (!orderBtn || !customerPhone) return;
    const phoneValue = normalizePhoneValue(customerPhone.value);
    const phoneValid = /^[6-9]\d{9}$/.test(phoneValue) && !isFakePhone(phoneValue);
    orderBtn.disabled = !phoneValid;
    orderBtn.setAttribute("aria-disabled", String(!phoneValid));
  }

  function validateInput(input) {
    if (!input) return true;

    clearError(input);
    const value = input.value.trim();

    if (input.hasAttribute("required") && value === "") {
      showError(input, "This field is required.");
      return false;
    }

    if (input.dataset.rule === "name" && value !== "") {
      if (!/^[\p{L} ]{2,}$/u.test(value)) {
        showError(input, "Enter at least 2 letters using a valid name.");
        return false;
      }
    }

    if (input.dataset.rule === "phone" && value !== "") {
      const normalizedPhone = normalizePhoneValue(value);
      input.value = normalizedPhone;

      if (!/^[6-9]\d{9}$/.test(normalizedPhone)) {
        showError(input, "Enter a valid 10-digit Indian mobile number.");
        updateOrderButtonState();
        return false;
      }

      if (isFakePhone(normalizedPhone)) {
        showError(input, "Enter a real mobile number.");
        updateOrderButtonState();
        return false;
      }

      showSuccess(input, "Mobile number looks valid.");
    }

    if (input.type === "number" && value !== "") {
      const num = Number(value);
      const min = Number(input.min || 0);
      if (Number.isNaN(num) || num < min) {
        showError(input, `Value must be at least ${min}.`);
        return false;
      }
    }

    if (input.type === "datetime-local" && value !== "") {
      const selected = new Date(value);
      if (Number.isNaN(selected.getTime())) {
        showError(input, "Select a valid date and time.");
        return false;
      }

      if (selected.getTime() < Date.now() - 60000) {
        showError(input, "Please choose a future date and time.");
        return false;
      }
    }

    return true;
  }

  function renderCart() {
    if (!cartItemsEl || !cartTotalEl) return;

    if (cart.length === 0) {
      cartItemsEl.innerHTML = '<p class="muted">No items added.</p>';
      cartTotalEl.textContent = formatCurrency(0);
      return;
    }

    let total = 0;
    cartItemsEl.innerHTML = cart
      .map((item, index) => {
        const lineTotal = item.price * item.qty;
        total += lineTotal;

        return `
          <div class="cart-row">
            <div class="cart-copy">
              <strong>${escapeHtml(item.name)}</strong>
              <small>${formatCurrency(item.price)} x ${item.qty}</small>
            </div>
            <div class="cart-actions">
              <button class="qty-btn" type="button" data-index="${index}" data-delta="-1">-</button>
              <span>${item.qty}</span>
              <button class="qty-btn" type="button" data-index="${index}" data-delta="1">+</button>
              <button class="remove-btn" type="button" data-index="${index}">Remove</button>
            </div>
          </div>
        `;
      })
      .join("");

    cartTotalEl.textContent = formatCurrency(total);
  }

  function addToCart(item) {
    const existing = cart.find((entry) => entry.id === item.id);
    if (existing) {
      existing.qty += 1;
    } else {
      cart.push({ ...item, qty: 1 });
    }

    renderCart();
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[char];
    });
  }

  document.querySelectorAll(".add-to-cart").forEach((button) => {
    button.addEventListener("click", () => {
      const itemEl = button.closest(".menu-item");
      if (!itemEl) return;

      addToCart({
        id: itemEl.dataset.id || "",
        name: itemEl.dataset.name || "Item",
        price: Number(itemEl.dataset.price || 0),
      });
    });
  });

  if (cartItemsEl) {
    cartItemsEl.addEventListener("click", (event) => {
      const qtyButton = event.target.closest(".qty-btn");
      const removeButton = event.target.closest(".remove-btn");

      if (qtyButton) {
        const index = Number(qtyButton.dataset.index);
        const delta = Number(qtyButton.dataset.delta);
        if (!cart[index]) return;

        cart[index].qty += delta;
        if (cart[index].qty <= 0) {
          cart.splice(index, 1);
        }

        renderCart();
        return;
      }

      if (removeButton) {
        const index = Number(removeButton.dataset.index);
        if (!Number.isNaN(index)) {
          cart.splice(index, 1);
          renderCart();
        }
      }
    });
  }

  document.querySelectorAll(".menu-thumb, .gallery-thumb").forEach((img) => {
    img.addEventListener("click", () => {
      if (!modal || !modalImg) return;
      modal.setAttribute("aria-hidden", "false");
      modalImg.src = img.src;
      modalImg.alt = img.alt || "Preview";
    });
  });

  function closeModal() {
    if (!modal || !modalImg) return;
    modal.setAttribute("aria-hidden", "true");
    modalImg.removeAttribute("src");
    modalImg.alt = "";
  }

  if (modalClose) {
    modalClose.addEventListener("click", closeModal);
  }

  if (modal) {
    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });
  }

  [customerName, customerPhone, bookingDate, deliveryType].forEach((input) => {
    if (!input) return;
    input.addEventListener("input", () => {
      validateInput(input);
      updateOrderButtonState();
    });
    input.addEventListener("blur", () => {
      validateInput(input);
      updateOrderButtonState();
    });
  });

  if (bookingForm) {
    bookingForm.addEventListener("submit", (event) => {
      const inputs = Array.from(
        bookingForm.querySelectorAll("input, textarea, select")
      );
      const valid = inputs.every((input) => validateInput(input));

      if (!valid) {
        event.preventDefault();
        bookingForm.querySelector(".input-error")?.focus();
      }
    });
  }

  if (orderBtn) {
    orderBtn.addEventListener("click", async () => {
      const validName = validateInput(customerName);
      const validPhone = validateInput(customerPhone);
      const validDeliveryType = validateInput(deliveryType);

      if (!validName || !validPhone || !validDeliveryType) {
        return;
      }

      if (cart.length === 0) {
        window.alert("Your cart is empty.");
        return;
      }

      orderBtn.disabled = true;
      orderBtn.textContent = "Placing Order...";

      try {
        const response = await fetch("place_order.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": csrfToken,
          },
          body: JSON.stringify({
            name: customerName.value.trim(),
            phone: customerPhone.value.trim(),
            delivery_type: deliveryType.value,
            cart,
          }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
          throw new Error(data.message || "Order failed.");
        }

        cart.length = 0;
        renderCart();
        customerName.value = "";
        customerPhone.value = "";
        if (deliveryType) {
          deliveryType.value = "";
        }
        if (data.invoice_url) {
          window.open(data.invoice_url, "_blank", "noopener");
        }
        window.alert(
          data.message ||
            `Order #${data.order_id} placed successfully. Your receipt is ready to view.`
        );
      } catch (error) {
        window.alert(error.message || "Server error.");
      } finally {
        orderBtn.disabled = false;
        orderBtn.textContent = "Order Now";
      }
    });
  }

  renderCart();
  updateOrderButtonState();
});

// ...existing code... (cleaned and fixed)
document.addEventListener("DOMContentLoaded", () => {
  /* ===== GALLERY CAROUSEL + MODAL ===== */
  const galTrack = document.querySelector(".g-track");
  const galImages = Array.from(document.querySelectorAll(".g-track img"));
  const galPrevBtn = document.querySelector(".g-nav.prev");
  const galNextBtn = document.querySelector(".g-nav.next");
  let galIndex = 0;
  let galInterval;

  function galUpdate() {
    if (galTrack) galTrack.style.transform = `translateX(-${galIndex * 100}%)`;
  }

  if (galPrevBtn && galNextBtn && galImages.length) {
    galPrevBtn.addEventListener("click", () => {
      galIndex = galIndex > 0 ? galIndex - 1 : galImages.length - 1;
      galUpdate();
      resetGalAuto();
    });
    galNextBtn.addEventListener("click", () => {
      galIndex = (galIndex + 1) % galImages.length;
      galUpdate();
      resetGalAuto();
    });

    function startGalAuto() {
      galInterval = setInterval(() => {
        galIndex = (galIndex + 1) % galImages.length;
        galUpdate();
      }, 3000);
    }
    function resetGalAuto() {
      clearInterval(galInterval);
      startGalAuto();
    }
    startGalAuto();
  }

  // Modal
  const galModal = document.getElementById("imgModal");
  const galModalImg = document.getElementById("modalImg");
  const galModalClose = document.querySelector(".modal-close");
  const galModalPrev = document.querySelector(".modal-prev");
  const galModalNext = document.querySelector(".modal-next");

  galImages.forEach((img, i) => {
    img.addEventListener("click", () => {
      galIndex = i;
      if (galModal && galModalImg) {
        galModal.style.display = "flex";
        galModalImg.src = img.src;
      }
    });
  });

  function updateModalImg() {
    if (galModalImg && galImages[galIndex])
      galModalImg.src = galImages[galIndex].src;
  }

  if (galModalPrev)
    galModalPrev.addEventListener("click", () => {
      galIndex = galIndex > 0 ? galIndex - 1 : galImages.length - 1;
      updateModalImg();
    });
  if (galModalNext)
    galModalNext.addEventListener("click", () => {
      galIndex = (galIndex + 1) % galImages.length;
      updateModalImg();
    });
  if (galModalClose)
    galModalClose.addEventListener("click", () => {
      if (galModal) galModal.style.display = "none";
    });
  if (galModal)
    galModal.addEventListener("click", (e) => {
      if (e.target === galModal) galModal.style.display = "none";
    });

  /* ===== MENU MARQUEE ===== */
  const menuBox = document.getElementById("menuMarquee");
  const menuTrack = document.querySelector(".mm-track");
  const menuBtnLeft = document.querySelector(".mm-btn.left");
  const menuBtnRight = document.querySelector(".mm-btn.right");

  if (menuTrack && menuBox) {
    // duplicate children to enable seamless loop
    const items = Array.from(menuTrack.children);
    items.forEach((it) => menuTrack.appendChild(it.cloneNode(true)));

    let menuSpeed = 0.6;
    let menuPos = 0;
    let menuPaused = false;

    function menuStep() {
      if (!menuPaused) {
        menuPos -= menuSpeed;
        if (Math.abs(menuPos) >= menuTrack.scrollWidth / 2) menuPos = 0;
        menuTrack.style.transform = `translateX(${menuPos}px)`;
      }
      requestAnimationFrame(menuStep);
    }
    menuStep();

    menuBox.addEventListener("mouseenter", () => (menuPaused = true));
    menuBox.addEventListener("mouseleave", () => (menuPaused = false));

    if (menuBtnRight)
      menuBtnRight.addEventListener("click", () => {
        menuSpeed = Math.min(3, menuSpeed + 0.2);
      });
    if (menuBtnLeft)
      menuBtnLeft.addEventListener("click", () => {
        menuSpeed = Math.max(0.2, menuSpeed - 0.2);
      });
  }

  /* ===== CART MODULE ===== */
  let cart = [];

  function renderCart() {
    const itemsBox = document.getElementById("cartItems");
    const totalBox = document.getElementById("cartTotal");
    if (!itemsBox || !totalBox) return;

    if (cart.length === 0) {
      itemsBox.innerHTML = "<p class='muted'>No items added</p>";
      totalBox.innerText = "₹0";
      return;
    }

    let grandTotal = 0;
    itemsBox.innerHTML = cart
      .map((item, idx) => {
        const lineTotal = item.price * item.qty;
        grandTotal += lineTotal;
        return `
        <div class="cart-row">
          <div>
            <strong>${item.name}</strong><br>
            <small>₹${item.price} × ${item.qty} = ₹${lineTotal}</small>
          </div>
          <div class="cart-actions">
            <button class="qty-btn" onclick="changeQty(${idx}, -1)">−</button>
            <strong>${item.qty}</strong>
            <button class="qty-btn" onclick="changeQty(${idx}, 1)">+</button>
            <button class="remove-btn" onclick="removeItem(${idx})">×</button>
          </div>
        </div>
      `;
      })
      .join("");
    totalBox.innerText = "₹" + grandTotal;
  }

  // exposed for inline onclick handlers
  window.changeQty = (index, delta) => {
    if (!cart[index]) return;
    cart[index].qty += delta;
    if (cart[index].qty <= 0) cart.splice(index, 1);
    renderCart();
  };

  window.removeItem = (index) => {
    cart.splice(index, 1);
    renderCart();
  };

  // add from menu buttons
  document.querySelectorAll(".add-to-cart").forEach((btn) => {
    btn.addEventListener("click", () => {
      const item = {
        id: btn.dataset.id,
        name: btn.dataset.name,
        price: Number(btn.dataset.price || 0),
        img: btn.dataset.img,
        qty: 1,
      };
      const found = cart.find((c) => c.id === item.id);
      if (found) found.qty += 1;
      else cart.push(item);
      renderCart();
    });
  });

  /* ===== FORM VALIDATION (applies to all forms and standalone inputs) ===== */
  function showError(input, message) {
    if (!input) return;
    clearError(input);
    input.classList.add("input-error");
    const msg = document.createElement("span");
    msg.className = "error-msg";
    msg.innerText = message;
    input.insertAdjacentElement("afterend", msg);
  }

  function clearError(input) {
    if (!input) return;
    input.classList.remove("input-error");
    const next = input.nextElementSibling;
    if (next && next.classList && next.classList.contains("error-msg"))
      next.remove();
  }

  function validateField(input) {
    if (!input) return true;
    clearError(input);
    const val = (input.value || "").trim();

    if (input.hasAttribute("required") && !val) {
      showError(input, "This field is required");
      return false;
    }

    if (input.type === "tel" && val) {
      const re = /^\d{10,15}$/;
      if (!re.test(val)) {
        showError(input, "Enter a valid phone number (10–15 digits)");
        return false;
      }
    }

    if (input.type === "datetime-local" && val) {
      const selected = new Date(val);
      const now = new Date();
      if (isNaN(selected.getTime())) {
        showError(input, "Invalid date/time");
        return false;
      }
      if (selected.getTime() < now.getTime() - 60000) {
        showError(input, "Select a future date and time");
        return false;
      }
    }

    if (input.type === "number" && val) {
      const num = Number(val);
      const min = input.hasAttribute("min")
        ? Number(input.getAttribute("min"))
        : null;
      if (min !== null && num < min) {
        showError(input, `Value must be at least ${min}`);
        return false;
      }
    }

    if (input.hasAttribute("pattern") && val) {
      const p = input.getAttribute("pattern");
      try {
        const re = new RegExp("^" + p + "$");
        if (!re.test(val)) {
          showError(input, "Invalid format");
          return false;
        }
      } catch (e) {
        // ignore invalid pattern
      }
    }

    return true;
  }

  // validate all forms on submit
  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", (e) => {
      let valid = true;
      const inputs = Array.from(
        form.querySelectorAll("input, textarea, select")
      );
      for (const inp of inputs) {
        if (!validateField(inp)) valid = false;
      }
      if (!valid) {
        e.preventDefault();
        const firstErr = form.querySelector(".input-error");
        if (firstErr) firstErr.focus();
      }
    });

    // live validation
    form.addEventListener("input", (ev) => {
      const target = ev.target;
      if (
        target &&
        (target.tagName === "INPUT" ||
          target.tagName === "TEXTAREA" ||
          target.tagName === "SELECT")
      ) {
        validateField(target);
      }
    });

    form.addEventListener(
      "blur",
      (ev) => {
        const target = ev.target;
        if (
          target &&
          (target.tagName === "INPUT" ||
            target.tagName === "TEXTAREA" ||
            target.tagName === "SELECT")
        ) {
          validateField(target);
        }
      },
      true
    );
  });

  // order now (validates standalone inputs before placing order)
  const orderBtn = document.getElementById("orderNowBtn");
  if (orderBtn) {
    orderBtn.addEventListener("click", (e) => {
      const nameInput = document.getElementById("custName");
      const phoneInput = document.getElementById("custPhone");
      const validName = validateField(nameInput);
      const validPhone = validateField(phoneInput);

      if (!validName || !validPhone) {
        e.preventDefault();
        alert("Please fix highlighted fields before ordering.");
        return;
      }

      if (cart.length === 0) {
        e.preventDefault();
        alert("Cart is empty");
        return;
      }

      const name = nameInput.value.trim();
      const phone = phoneInput.value.trim();

      fetch("place_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, phone, cart }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            window.open(data.whatsapp, "_blank");
            cart = [];
            renderCart();
            nameInput.value = "";
            phoneInput.value = "";
          } else alert("Order failed");
        })
        .catch(() => alert("Server error"));
    });
  }

  // initial render
  renderCart();
});

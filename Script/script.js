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

  // order now
  const orderBtn = document.getElementById("orderNowBtn");
  if (orderBtn) {
    orderBtn.addEventListener("click", () => {
      if (cart.length === 0) return alert("Cart is empty");
      const nameInput = document.getElementById("custName");
      const phoneInput = document.getElementById("custPhone");
      const name = nameInput.value.trim();
      const phone = phoneInput.value.trim();
      if (!name || !phone) return alert("Please enter name & phone");

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

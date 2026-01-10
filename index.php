
<?php
require "includes/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Trikut Restaurant & Cafe — Demo</title>
<link rel="stylesheet" href="./CSS/style.css">
 
</head>

<body>

<header>
  <div class="container topbar">
    <div class="brand">
      <div class="logo">
        <img src="https://images.unsplash.com/photo-1541542684-13b0fa6d1f64">
      </div>
      <div style="color:white">
        <b>Trikut Restaurant & Cafe</b><br>
        <small>Warm. Local. Homemade.</small>
      </div>
    </div>
    <nav>
      <a href="#menu">Menu</a>
      <a href="#gallery">Gallery</a>
      <a href="#book">Book</a>
      <a class="cta" href="#book">Reserve</a>
    </nav>
  </div>
</header>

<main class="container">

<section id="menu">
  <h2>Menu</h2>
 <div class="menu-marquee card" id="menuMarquee">
  <button class="mm-btn left">&#10094;</button>

  <div class="mm-window">
    <div class="mm-track">

      <div class="mm-item">
        <img src="https://images.unsplash.com/photo-1604908177522-97a9f6b9a2f6?q=80&w=600&auto=format&fit=crop">
        <h4>Paneer Wrap ₹160</h4>
        <button class="add-to-cart"
          data-id="1"
          data-name="Paneer Wrap"
          data-price="160"
          data-img="https://images.unsplash.com/photo-1604908177522-97a9f6b9a2f6?q=80&w=600&auto=format&fit=crop">
          Add
        </button>
      </div>

      <div class="mm-item">
        <img src="https://images.unsplash.com/photo-1544025162-d76694265947?q=80&w=600&auto=format&fit=crop">
        <h4>White Pasta ₹180</h4>
        <button class="add-to-cart"
          data-id="2"
          data-name="White Pasta"
          data-price="180"
          data-img="https://images.unsplash.com/photo-1544025162-d76694265947?q=80&w=600&auto=format&fit=crop">
          Add
        </button>
      </div>

      <div class="mm-item">
        <img src="https://images.unsplash.com/photo-1551183053-bf91a1d81141?q=80&w=600&auto=format&fit=crop">
        <h4>Veg Burger ₹120</h4>
        <button class="add-to-cart"
          data-id="3"
          data-name="Veg Burger"
          data-price="120"
          data-img="https://images.unsplash.com/photo-1551183053-bf91a1d81141?q=80&w=600&auto=format&fit=crop">
          Add
        </button>
      </div>

      <div class="mm-item">
        <img src="https://images.unsplash.com/photo-1497032628192-86f99bcd76bc?q=80&w=600&auto=format&fit=crop">
        <h4>Cappuccino ₹90</h4>
        <button class="add-to-cart"
          data-id="4"
          data-name="Cappuccino"
          data-price="90"
          data-img="https://images.unsplash.com/photo-1497032628192-86f99bcd76bc?q=80&w=600&auto=format&fit=crop">
          Add
        </button>
      </div>

    </div>
  </div>

  <button class="mm-btn right">&#10095;</button>
</div>


</section>

<section class="two-col">
  <div>
    <h2 id="gallery">Gallery</h2>
   <div class="gallery card" id="galleryCarousel">
  <button class="g-nav prev">&#10094;</button>

  <div class="g-track">
    <img src="https://images.unsplash.com/photo-1528605248644-14dd04022da1?q=80&w=900&auto=format&fit=crop" />
    <img src="https://images.unsplash.com/photo-1498654896293-37aacf113fd9?q=80&w=900&auto=format&fit=crop" />
    <img src="https://images.unsplash.com/photo-1526318472351-c75fcf070770?q=80&w=900&auto=format&fit=crop" />
    <img src="https://images.unsplash.com/photo-1552566626-52f8b828add9?q=80&w=900&auto=format&fit=crop" />
    <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=900&auto=format&fit=crop" />
    <img src="https://images.unsplash.com/photo-1481833761820-0509d3217039?q=80&w=900&auto=format&fit=crop" />
  </div>

  <button class="g-nav next">&#10095;</button>
</div>


    <h2>Location</h2>
    <iframe
      src="https://www.google.com/maps?q=26.9271147,80.8978473&output=embed"
      width="100%" height="220"></iframe>
  </div>

  <!-- ===== BOOKING FORM (ONLY STRUCTURE CHANGE) ===== -->
  <aside>
    <div class="card" id="book">
      <h3>Reserve a Table</h3>

      <form id="bookingForm" method="post" action="includes/save_booking.php" novalidate>
        <input id="bookName" name="name" placeholder="Name" required><br><br>
        <input id="bookPhone" type="tel" name="phone" placeholder="Phone" pattern="\d{10,15}" required><br><br>
        <input id="bookDate" type="datetime-local" name="date" required><br><br>
        <input id="bookSize" type="number" name="size" value="2" min="1"><br><br>
        <button type="submit">Reserve</button>
      </form>

    </div>
    <div class="card" id="cartBox">
  <h3>Your Cart</h3>

  <div id="cartItems">
    <p class="muted">No items added</p>
  </div>

  <div class="cart-total">
    <span>Total</span>
    <strong id="cartTotal">₹0</strong>
  </div>

  <input id="custName" placeholder="Your Name" required>
  <input id="custPhone" type="tel" placeholder="Mobile Number" pattern="\d{10,15}" required>

  <button id="orderNowBtn">Order Now</button>
</div>

  </aside>
</section>

</main>

<footer>
  © Trikut Restaurant & Cafe
</footer>
<div id="imgModal">
  <span class="modal-close">&times;</span>
  <span class="modal-nav modal-prev">&#10094;</span>
  <img id="modalImg" />
  <span class="modal-nav modal-next">&#10095;</span>
</div>

<!-- Fixed script path -->
<script src="./Script/script.js"></script>
</body>
</html>
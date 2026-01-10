<?php
session_start();
if(!isset($_SESSION['admin'])){ header("Location: login.php"); exit; }
?>
<h2>Admin Dashboard</h2>
<a href="menu.php">Menu</a> |
<a href="gallery.php">Gallery</a> |
<a href="bookings.php">Bookings</a> |
<a href="logout.php">Logout</a>
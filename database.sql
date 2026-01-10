CREATE DATABASE IF NOT EXISTS trikut_restaurant;
USE trikut_restaurant;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE,
  password VARCHAR(255)
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  description TEXT,
  price INT,
  image VARCHAR(255)
);

CREATE TABLE gallery (
  id INT AUTO_INCREMENT PRIMARY KEY,
  image VARCHAR(255)
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  phone VARCHAR(20),
  booking_time DATETIME,
  guests INT
);

INSERT INTO admins (username,password) VALUES (
 'trikut',
 '$2y$10$hQXz9rMZpFJQn2e8t8kKxO8PZ5VqXWZ4H5Jv9mPp8yFZ6Vx8z4Jx2'
);
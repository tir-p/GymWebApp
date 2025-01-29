// script.js

// Highlight the active page in the navbar
const currentLocation = location.href;
const menuItem = document.querySelectorAll('.nav-links a');
const menuLength = menuItem.length;
for (let i = 0; i < menuLength; i++) {
  if (menuItem[i].href === currentLocation) {
    menuItem[i].className = "active";
  }
}

// Dynamic booking list in Account page
document.addEventListener("DOMContentLoaded", function () {
  const bookingList = document.getElementById("booking-list");
  if (bookingList) {
    // This would ideally come from a database or API
    const bookings = [
      { class: "Yoga", date: "2024-10-10", time: "10:00 AM" },
      { class: "Cardio", date: "2024-10-15", time: "8:00 AM" }
    ];

    if (bookings.length > 0) {
      bookingList.innerHTML = bookings.map(booking =>
        `<li>${booking.class} on ${booking.date} at ${booking.time}</li>`
      ).join("");
    } else {
      bookingList.innerHTML = "<li>No upcoming bookings. Please make a reservation!</li>";
    }
  }
});

// Form Validation for Login Page
const loginForm = document.querySelector("form");
if (loginForm) {
  loginForm.addEventListener("submit", function (e) {
    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;

    if (username === "" || password === "") {
      e.preventDefault();
      alert("Both fields are required!");
    }
  });
}

// Booking form validation
const bookingForm = document.querySelector("#booking-form");
if (bookingForm) {
  bookingForm.addEventListener("submit", function (e) {
    const date = document.getElementById("date").value;
    const time = document.getElementById("time").value;
    
    const selectedDate = new Date(date);
    const now = new Date();

    if (selectedDate < now) {
      e.preventDefault();
      alert("Please select a future date.");
    }
  });
}

// Ensure all functions are correctly implemented and functional

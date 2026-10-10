# Millennium India Group - Event Ticketing & Gate Access System

A secure, lightweight, production-ready Event Ticket Booking Website and Organizer Admin Panel built with **Native PHP (8.1+)**, **MySQL (InnoDB, utf8mb4)**, **Vanilla JavaScript**, **HTML5**, and **CSS3**.

Priority Launch Event: **"Jashn-E-Garba 2026"** (16 October 2026 at Unique Garden, Patna).

---

## 🚀 Key Features

1. **Dual Payment Workflow**:
   - **Day-1 Instant Launch (Direct UPI)**: Dynamic UPI QR code (compatible with PhonePe, Google Pay, Paytm, BHIM) + 12-digit UTR/Ref receipt submission.
   - **Payment Gateway Ready**: Razorpay Checkout JS integration (Test/Live toggle in settings) with automated inventory decrement.
2. **Anti-Overselling & Race Condition Protection**:
   - Database transactions with `SELECT ... FOR UPDATE` row locks to prevent ticket quota overselling during concurrent checkouts.
3. **Cryptographically Secure Digital QR Tickets**:
   - High-resolution luxury boarding pass design with perforated stub layout and native `@media print` print-to-PDF styles.
   - QR payload encodes JSON: `{"uid": "MIG...", "token": "64_char_secure_hash"}`.
4. **Mobile Gate QR Entry Scanner (`admin/scanner.php`)**:
   - Uses device camera via `html5-qrcode`.
   - Web Audio synthesizer chimes for instant audio feedback.
   - Instant color-coded gate modal:
     - 🟢 **Valid Ticket (Green)**: Permits entry, displays total persons count, and updates check-in status.
     - 🟡 **Already Used (Amber)**: Prevents duplicate entry and displays previous check-in timestamp.
     - 🔴 **Invalid / Unpaid (Red)**: Flags pending UPI or forged QR tokens.
5. **Organizer Admin Portal (`admin/`)**:
   - Dashboard with 4 live metric cards: Total Bookings, Verified Revenue, Pending UPI Payments, Gate Check-ins.
   - 1-click **Verify UPI** approval button that verifies receipts and immediately unlocks the digital pass.
   - Searchable booking manager with UTR check and CSV export.
   - Dynamic Event CRUD & Ticket Tier quota manager.
   - Site & Payment settings management console.

---

## 🛠 Directory Structure

```text
millennium-ticket/
├── config/
│   ├── config.php             # Global app configuration & dynamic URL auto-detection
│   ├── db.php                 # PDO database singleton with UTF-8 mb4
│   └── functions.php          # CSRF guards, XSS sanitizer, session security & UID generator
│
├── assets/
│   ├── css/
│   │   ├── style.css          # Frontend mobile-first festive theme (Royal Magenta & Gold)
│   │   ├── admin.css          # Dark-luxe admin dashboard styling
│   │   └── ticket.css         # Luxury printable boarding pass & print media styles
│   ├── js/
│   │   ├── booking.js         # Quantity counters, live total calculation & validations
│   │   └── scanner.js         # Camera stream, audio chimes & scan check logic
│   └── uploads/               # Banners, posters, receipt uploads
│
├── includes/
│   ├── header.php             # Public navbar, meta tags & flash messaging
│   ├── footer.php             # Footer, organization info & trust assurances
│   └── admin_sidebar.php      # Admin console sidebar navigation
│
├── index.php                  # Homepage with Jashn-E-Garba 2026 showcase
├── events.php                 # Upcoming events directory
├── event-detail.php           # Event details, venue schedule & guidelines
├── booking-step1.php          # Ticket pass selection & server-side quota verification
├── booking-step2.php          # Primary attendee details form
├── payment.php                # Dual checkout: Direct UPI QR + Gateway JS trigger
├── confirmation.php           # Booking confirmation / pending approval state
├── ticket.php                 # Digital printable QR ticket pass with lookup
├── contact.php                # Contact desk & venue directions
├── terms.php                  # Terms & Conditions
├── privacy.php                # Privacy Policy
├── refund-policy.php          # Cancellation & Refund Policy
│
├── api/
│   ├── check-ticket.php       # QR Code validation endpoint for gate scanner
│   ├── verify-payment.php     # Gateway verification endpoint
│   └── export-csv.php         # Admin CSV export for bookings
│
└── admin/
    ├── login.php              # Secure login with brute-force rate limiting
    ├── logout.php             # Admin session logout
    ├── index.php              # Metrics dashboard & quick transaction approvals
    ├── events.php             # Event CRUD, publish/unpublish & banner upload
    ├── tickets.php            # Ticket categories, pricing & inventory quotas
    ├── bookings.php           # Manage bookings, inspect UTRs & CSV export
    ├── scanner.php            # Mobile turnstile QR camera scanner
    └── settings.php           # Configure UPI ID, Razorpay keys & site settings
```

---

## 🔑 Default Admin Credentials

- **Login URL**: `admin/login.php`
- **Username**: `admin`
- **Password**: `Admin@MIG2026!`

*(Password hash generated via `PASSWORD_BCRYPT`)*

---

## 📦 cPanel / Production Deployment Guide

1. **Upload Files**:
   - Upload the project files directly to your cPanel `public_html/` directory (or your domain root).
2. **Import Database**:
   - In cPanel, navigate to **MySQL Databases** and create a database (e.g. `millennium_tickets`).
   - Open **phpMyAdmin**, select the database, and import `database.sql`.
3. **Configure Database Credentials**:
   - Open `config/config.php` and set your database user, password, and database name:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'your_cpanel_db');
     define('DB_USER', 'your_cpanel_user');
     define('DB_PASS', 'your_cpanel_password');
     ```
4. **Configure UPI ID in Admin**:
   - Log into `admin/login.php`.
   - Go to **Settings & UPI** (`admin/settings.php`).
   - Enter your active business UPI VPA (e.g. `millenniumindia@upi`) and Merchant Name.
   - Now attendees can pay immediately via Google Pay, PhonePe, and Paytm!

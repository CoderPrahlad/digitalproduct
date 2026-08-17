# ⚡ DevStore — Fully Automatic Digital Product Store

**PHP + MySQL | Razorpay Auto-Payment | Auto Email Delivery | Animated Dark UI**

---

## ✅ What's Included

| Feature | Details |
|---------|---------|
| 🎨 Animated UI | Particle background, scroll reveals, countdown timers, glow effects |
| 💳 Razorpay | Automatic payment — UPI, Card, Net Banking, Wallets |
| 📧 Auto Email | License key + download link sent instantly after payment |
| 🔐 Secure Download | Token-based (no login needed from email), expiry + count limits |
| 🛡️ Manual UPI | Fallback for manual payments with admin approval |
| 📱 Responsive | Works on mobile, tablet, desktop |
| ⚙️ Admin Panel | Products, Orders, Users, Messages, Settings |
| 📲 Telegram | Instant new order notifications (optional) |
| 🔔 WhatsApp | Support button on every page |

---

## 🚀 Installation (3 Steps)

### Step 1 — Upload Files
- ZIP extract karo
- `public_html/` me sab files upload karo (cPanel File Manager ya FTP)

### Step 2 — Database
1. cPanel → MySQL Databases → **New Database** banao
2. **New User** banao + **All Privileges** do
3. phpMyAdmin → **Left side se apna database click karo**
4. Import tab → `database.sql` → **Go**

### Step 3 — Config Edit
`config/config.php` kholo aur fill karo:

```php
define('DB_NAME', 'u123456_devstore');   // apna DB naam
define('DB_USER', 'u123456_dbuser');     // apna DB user
define('DB_PASS', 'YourPassword');

define('SITE_NAME', 'DevStore');
define('SITE_URL',  'https://yourdomain.com');  // NO trailing slash

// Razorpay (razorpay.com → Settings → API Keys)
define('RAZORPAY_KEY_ID',     'rzp_live_...');
define('RAZORPAY_KEY_SECRET', '...');

// UPI fallback
define('UPI_ID',   'yourname@upi');

// Email (Gmail → Security → App Passwords)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'youremail@gmail.com');
define('MAIL_PASS', 'xxxx xxxx xxxx xxxx');  // 16-char App Password

define('WA_NUMBER', '919876543210');  // with 91, no +

// Optional — Telegram notifications
define('TG_BOT_TOKEN', 'your_bot_token');
define('TG_CHAT_ID',   'your_chat_id');
```

### Step 4 — Create Admin
Browser me open karo:
```
https://yourdomain.com/setup/create_admin.php
```
Admin username & password set karo → **SETUP FILE DELETE KARO!**

### Step 5 — Login
```
https://yourdomain.com/admin/login.php
```

---

## 📧 Gmail App Password Setup

1. Google Account → Security → 2-Step Verification ON karo
2. Security → App Passwords → Select app: Mail → Generate
3. 16-character password milega — config.php me daalo

---

## 💳 Razorpay Setup

1. [razorpay.com](https://razorpay.com) par signup karo (free)
2. Dashboard → Settings → API Keys → Generate Live Key
3. `rzp_live_...` aur secret `config.php` me daalo
4. Test ke liye `rzp_test_...` key use karo

---

## 📁 Folder Structure

```
devstore/
├── config/
│   ├── config.php       ← EDIT THIS — all settings here
│   └── functions.php
├── includes/
│   ├── header.php
│   └── footer.php
├── assets/css/style.css
├── assets/js/main.js    ← particles, animations, modals
├── admin/               ← Admin panel
│   ├── login.php
│   ├── dashboard.php
│   ├── products.php     ← add/edit/delete + file upload
│   ├── orders.php       ← approve manual payments
│   ├── messages.php
│   ├── users.php
│   └── settings.php
├── api/
│   ├── verify_payment.php  ← Razorpay auto-verify + deliver
│   └── submit_manual.php   ← Manual UPI submission
├── mail/Mailer.php         ← Email delivery
├── uploads/products/       ← Product thumbnails
├── uploads/proofs/         ← Payment screenshots (web-blocked)
├── secure_downloads/       ← Product ZIP files (web-blocked)
├── setup/create_admin.php  ← DELETE AFTER USE
├── index.php               ← Homepage
├── product.php             ← Product detail
├── checkout.php            ← Razorpay + UPI checkout
├── success.php             ← Payment success
├── download.php            ← Secure token download
├── dashboard.php           ← User orders
├── register.php / login.php / logout.php
├── contact.php
└── database.sql            ← Import in phpMyAdmin
```

---

## 🔄 Payment Flow

```
Razorpay (Automatic):
User → Buy → Razorpay Checkout → Payment →
api/verify_payment.php → License Generated →
Email Sent → Success Page → Download

Manual UPI (Fallback):
User → Buy → UPI Page → UTR Submit →
Admin Panel → Verify → Approve →
Auto Email Sent → User Downloads
```

---

## 📦 PHPMailer (Recommended for production)

```bash
# Hosting SSH se ya local se:
composer require phpmailer/phpmailer
```
Mailer.php automatically PHPMailer detect karta hai.
PHP mail() as fallback already included.

---

## 🔒 Security

- `/secure_downloads/` — web se blocked (.htaccess)
- `/uploads/proofs/` — web se blocked
- `/config/` — web se blocked
- Download links — token-based, 72hr expiry, max 3 downloads
- Admin — session-based authentication
- Razorpay — signature verification on every payment

---

## ⚙️ Admin Quick Reference

| Task | Where |
|------|-------|
| Add product | Admin → Products → Add Product |
| Upload file | Products form → "Downloadable File" field |
| Approve manual payment | Admin → Orders → Pending → Manage → Approve |
| View revenue | Admin → Dashboard |
| Change download limit | Admin → Settings |

---

Made with ❤️ | PHP 7.4+ | MySQL 5.7+ | Razorpay


---

## 🆕 v7 — Sales-Boosting Features

### 🎟️ Coupon / Discount Codes
- Admin → Coupons → Add coupon with code like `SAVE20`
- Supports **percentage** (20%) or **fixed** (₹100) discounts
- Set minimum order amount, max usage limit, expiry date
- Customers apply coupon on checkout — price updates live
- Razorpay auto re-creates order with discounted amount

### ⭐ Product Reviews & Ratings
- Only customers who **purchased** can submit a review
- 5-star rating + title + body
- Admin approves before publishing (Admin → Reviews)
- Star rating + average displayed on product page

### ❤️ Wishlist
- "Add to Wishlist" button on every product page (logged-in users)
- Dedicated `/wishlist.php` page to view saved products
- One-click remove from wishlist
- Wishlist link in nav header

### 🔗 Related Products
- 3 random products from same category shown on product detail page
- Automatically updates — no config needed

### 🔍 Admin Order Search & Filter
- Search by **Order Ref**, **Name**, **Email**, **UTR**, **Payment ID**
- Combine with status filter (pending/paid/etc.)
- Fast — works on all order columns simultaneously

### 📈 Sales Analytics Dashboard
- Admin → Analytics
- **Line chart**: Revenue by Day / Week / Month (toggle)
- **Best-selling products** bar chart
- **Order status** donut chart
- Summary cards: All-time, This Month, This Week revenue

## 📦 v7 Migration

After updating files, run in phpMyAdmin:
```sql
-- Import this file:
migration_v7_features.sql
```

This adds: `coupons`, `reviews`, `wishlist` tables + sample coupons.


---

## v3 Features (migration_v3.sql required)

### 6. Sell Link in Navbar
- **Navbar** aur **Footer** dono mein "💰 Sell" link add ho gaya — `sell.php` pe directly jaata hai.

### 7. Order Cancel/Refund (already in v2)
- Admin Orders page pe "↩️ Cancel Order & Refund" button hai.
- Buyer ka amount unke **store wallet** mein credit hota hai.
- Download link revoke ho jaata hai.
- Wallet balance dashboard mein **Referral tab** ke andar dikhta hai.

### 8. Newsletter Subscribe Box
- Footer mein har page pe newsletter box dikhta hai.
- `/api/newsletter_subscribe.php` — AJAX POST endpoint, honeypot + rate limit.
- `/unsubscribe.php?token=...` — unsubscribe link (auto-included if you email).
- Admin → `/admin/newsletter.php` — subscribers list + CSV export.

### 9. Affiliate / Referral System
- Har user ka ek unique referral code auto-generate hota hai signup pe.
- Referral link: `SITE_URL/register.php?ref=YOURCODE`
- Referral commission: **Admin → Settings → Referral** mein % set karo (default 5%).
- Commission automatically credited to referrer's wallet on each purchase.
- Dashboard → **🤝 Referral** tab: link share karo, stats dekho, wallet transactions.

### 10. Google Login (Social Login)
Setup:
1. [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → OAuth 2.0 Client ID banao
2. Authorized Redirect URI: `https://yourdomain.com/auth/google_callback.php`
3. **Admin → Settings → Google OAuth** mein Client ID + Secret save karo.
4. Login/Register page pe "Continue with Google" button automatically appear hoga.

**Files:**
- `auth/google_callback.php` — OAuth flow handler
- `admin/settings.php` — Google credentials save karo
- `login.php`, `register.php` — Google button added

---

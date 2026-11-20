# FeedPulse AI — Intelligent Campus Feedback & Resolution System

**Version:** 2.0.0  
**Stack:** PHP 8.2+, MySQL 8.0+, Bootstrap 5.3, Spline 3D, Chart.js  
**Design Philosophy:** Dark Glassmorphism, 3D Canvas, Micro-interactions, WCAG 2.2 AA Accessible  

---

## 🌟 Key Features

1. **Spline 3D Canvas Integration**:
   - Interactive 3D scene embedded in the public hero section via `<spline-viewer>` with graceful WebGL fallback.
2. **Real-time AI Sentiment Triage**:
   - NLP sentiment classification (Positive / Neutral / Negative) with confidence score and automatic category suggestion as students type.
3. **Strict SLA Timers & Lifecycle Engine**:
   - Automated deadline calculation per department and category.
   - Live visual countdown progress meters.
   - CLI cron (`cron/check_sla.php`) for automatic breach detection, escalation to `CRITICAL`, and Super Admin alerts.
4. **100% Anonymous Whistleblowing**:
   - Cryptographic identity separation for student protection with no user-ID linkage when toggled.
5. **Civic Engagement Gamification**:
   - Point scoring system (+10 Feedback, +15 Complaint, +5 Survey) with tier progression: **Bronze &rarr; Silver &rarr; Gold &rarr; Platinum**.
6. **Multi-Role Portals**:
   - **Student**: Dashboard, feedback submit, ticket lodge, audit timeline, post-resolution surveys.
   - **Staff**: Department action queue, ticket claim, status transitions, resolution notes.
   - **Admin / Super Admin**: Executive command center, live Chart.js graphs, moderation queue, ticket triage, and 2FA OTP verification.

---

## 🚀 Quick Start Guide

### 1. Database Setup

Ensure MySQL is running (e.g., via XAMPP, Laragon, or standalone MySQL):

**Option A (Browser 1-Click Setup):**
Navigate to:
```
http://localhost/feedpulse-ai/database/setup.php
```

**Option B (CLI):**
```bash
mysql -u root -p < database/feedpulse.sql
```

### 2. Configure Credentials

Review [`config/database.php`](file:///c:/Users/nisha/Downloads/FeedBack%20system/feedpulse-ai/config/database.php):
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'feedpulse_ai');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Launch the Server

Using PHP's built-in web server:
```bash
cd "c:\Users\nisha\Downloads\FeedBack system\feedpulse-ai"
php -S localhost:8000
```
Then visit `http://localhost:8000` in your web browser.

---

## 🔑 Demo Seed Accounts

All accounts share the default password: **`password`**

| Role | Email | Notes |
|---|---|---|
| **Super Admin** | `superadmin@feedpulse.edu` | Full command center access (2FA OTP) |
| **Admin** | `admin@feedpulse.edu` | Triage & moderation (2FA OTP) |
| **Staff** | `staff.cs@feedpulse.edu` | Computer Science department resolution desk |
| **Student** | `student@feedpulse.edu` | Full student portal & ticket history |

*Note: In development mode, the 2FA OTP is automatically previewed directly on the verification screen for convenience.*

---

## ⏰ SLA Escalation Cron Job

To run the automated SLA breach detection and escalation cycle:
```bash
php cron/check_sla.php
```
*In production, schedule this task to run every 15 minutes via Windows Task Scheduler or crontab.*

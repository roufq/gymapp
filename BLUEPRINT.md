# 🏋️‍♂️ Enterprise Gym Management System (EGMS) - Technical Blueprint

Dokumen Spesifikasi Teknis & Arsitektur Sistem Manajemen Pusat Kebugaran Berbasis Web (PWA-Ready).

---

## 1. Tech Stack & Arsitektur Sistem

Sistem ini menggunakan pendekatan **Monolith Modern** dengan performa setara *Single Page Application* (SPA) dan kemampuan *Offline-Resilient* pada meja kasir.

* **Backend Framework:** Laravel 11.x (PHP 8.3+)
* **Frontend Stack (Opsi Hybrid / Terbaik):**
  * **Portal Member, PT & Kasir (POS/Gate):** Vue 3 (Composition API) + Inertia.js + Tailwind CSS + Pinia (State Management) + VitePWA (IndexedDB untuk Offline Check-in).
  * **Portal Owner & Admin Backoffice:** Filament PHP v3 (TALL Stack) atau Vue 3 Dashboard.
* **Database Utama:** PostgreSQL 16 / MySQL 8.0 (Mendukung *Row-Level Locking* untuk pencegahan *Overbooking*).
* **Caching & Queue:** Redis (untuk *Job Queue* WhatsApp Blast, *Rate Limiting*, dan *Live Crowd Counter*).
* **Real-time Broadcasting:** Laravel Reverb (WebSockets untuk *Live Gate Monitor* di layar kasir).
* **Key Laravel Packages:**
  * `spatie/laravel-permission` (Role-Based Access Control / RBAC)
  * `spatie/laravel-activitylog` (Audit Log aktivitas sensitif staf/kasir)
  * `pragmarx/google2fa-laravel` (Algoritma TOTP Dynamic QR Pass 60 detik)
  * `barryvdh/laravel-dompdf` (Cetak kontrak *Digital Waiver* & Invoice)

---

## 2. Struktur Direktori Laravel (Domain-Driven / Service Pattern)

Untuk menjaga kerapian kode skala besar, seluruh logika bisnis ditempatkan di dalam `app/Services` dan `app/Actions`, bukan menumpuk di `Controller`.

```text
gym-management-app/
├── app/
│   ├── Actions/                  # Single-action classes (misal: CalculateProrataAction)
│   ├── Console/Commands/         # Cron Jobs (ExpireSubscriptions, AutoCheckoutMembers)
│   ├── Events/                   # MemberCheckedIn, ClassCancelledByGym
│   ├── Exceptions/               # Custom Exceptions (InsufficientQuotaException, GateDeniedException)
│   ├── Filament/                 # (Opsional) Resource Admin & Owner Dashboard
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              # Endpoint khusus Webhook Payment & Turnstile Hardware
│   │   │   ├── Member/           # Inertia Controllers untuk PWA Member
│   │   │   ├── Trainer/          # Inertia Controllers untuk Portal PT
│   │   │   └── Pos/              # Inertia Controllers untuk Kasir, Gate & Loker
│   │   └── Middleware/           # VerifyDeviceBinding, RoleGuard, EnsureActiveShift
│   ├── Jobs/                     # Background Jobs (SendWhatsAppReminderJob)
│   ├── Models/                   # Eloquent Models
│   └── Services/                 # --- OTAK LOGIKA BISNIS UTAMA ---
│       ├── AccessControlService.php   # Validasi TOTP QR, Anti-Passback, Loker
│       ├── BookingService.php         # Booking Kelas/PT, DB Locking, Waitlist, 3-Strike
│       ├── MembershipService.php      # Aktivasi, Freeze/Cuti, Prorata Upgrade, Couple Plan
│       ├── PosBillingService.php      # Checkout, Split Commission, Blind Cash Opname
│       ├── PtSessionService.php       # OTP Session Burn, Validasi Check-in Fisik, Handover PT
│       └── AccountingService.php      # Perhitungan Deferred Revenue vs Cash Revenue
├── database/
│   ├── migrations/               # Skema Tabel Database
│   └── seeders/                  # RoleSeeder, DefaultPlanSeeder
└── resources/
    └── js/                       # Aplikasi Frontend Vue 3
        ├── Components/           # QRScanner.vue, CrowdMeter.vue, SignaturePad.vue
        ├── Layouts/              # MemberMobileLayout.vue, PosDesktopLayout.vue
        ├── Pages/                # Halaman Inertia (Member, Trainer, POS)
        └── Stores/               # Pinia + IndexedDB Offline Cache (useOfflineGateStore.js)
```

---

## 3. Matriks Peran & Hak Akses (RBAC)

| Role | Guard / Akses | Tanggung Jawab & Pembatasan Keamanan |
| :--- | :--- | :--- |
| `super_admin` (Owner) | Web Backoffice | Akses penuh: Laporan *Cash vs Earned Revenue*, *Payroll* & Komisi, *Audit Log*, PIN Otorisasi *Void/Refund*, Export Data (dilindungi 2FA). |
| `branch_manager` | Web Backoffice | Manajemen jadwal kelas, *Approval* pengajuan cuti (*Freeze*), substitusi PT sakit/resign, *Live Floor Monitor* (cek PT *idle* vs aktif), manajemen *Leads*. |
| `front_desk` (Kasir) | POS & Gate UI | Registrasi *walk-in* (wajib webcam), POS ritel, peminjaman kunci loker, *Blind Cash Opname* saat tutup *shift*. **Dilarang:** menghapus invoice atau ganti foto member tanpa PIN Manajer. |
| `personal_trainer` | Mobile PWA | Atur slot jam tersedia, lihat profil medis klien (*No HP disensor/masked*), input progres *InBody/Workout*, klaim selesai sesi (*Session Burn* via QR/OTP). |
| `instructor` | Mobile PWA | Lihat daftar peserta kelas studio (*Class Roster*) dan absensi kehadiran peserta. |
| `member` | Mobile PWA | Tampilkan *Dynamic QR Pass* (terkunci di 1 *Device ID*), beli/perpanjang paket, booking kelas/PT, ajukan *Freeze*, pantau grafik progres tubuh & *Live Crowd Meter*. |

---

## 4. Skema Database Lengkap (Database Schema / Migrations)

Berikut adalah desain skema relasional utuh yang sudah mencakup **Patch Anti-Fraud, Akuntansi, dan Regulasi Hukum**:

```sql
-- 1. MASTER CABANG (MULTI-BRANCH)
CREATE TABLE branches (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    max_capacity INT DEFAULT 100, -- Untuk perhitungan persentase Crowd Meter
    opening_time TIME NOT NULL,
    closing_time TIME NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 2. USERS (MEMBER, STAF, PT, OWNER)
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    home_branch_id BIGINT REFERENCES branches(id),
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(25) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    photo_url VARCHAR(255), -- Hanya bisa diubah oleh Kasir/Admin
    totp_secret VARCHAR(100), -- Secret key untuk Dynamic QR Code 60 detik
    active_device_id VARCHAR(255), -- Anti-Joki: mengunci login di 1 HP
    
    -- Medis & Legal (PAR-Q & Minor Consent)
    birth_date DATE NOT NULL,
    is_minor BOOLEAN DEFAULT FALSE,
    guardian_name VARCHAR(150) NULL,
    guardian_phone VARCHAR(25) NULL,
    guardian_ktp_url VARCHAR(255) NULL,
    digital_waiver_signature_url VARCHAR(255),
    medical_risk_flag BOOLEAN DEFAULT FALSE,
    medical_notes TEXT NULL,

    -- Penalti & Denda
    no_show_strike_count INT DEFAULT 0,
    booking_suspended_until TIMESTAMP NULL,
    unpaid_fine_amount DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 3. BUKU TAMU & FREE TRIAL (CRM LEADS)
CREATE TABLE guest_trials (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT REFERENCES branches(id),
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(25) UNIQUE NOT NULL, -- Cegah klaim free trial berulang
    id_card_number VARCHAR(35) UNIQUE NOT NULL,
    lead_owner_id BIGINT REFERENCES users(id), -- Sales pencari prospek
    trial_date DATE NOT NULL,
    status VARCHAR(30) DEFAULT 'NEW', -- NEW, CONTACTED, TRIAL_USED, CONVERTED, LOST
    created_at TIMESTAMP
);

-- 4. MASTER PAKET MEMBERSHIP
CREATE TABLE membership_plans (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    plan_type VARCHAR(30) NOT NULL, -- TIME_BASED, QUOTA_BASED, COUPLE, CORPORATE
    duration_days INT NOT NULL,
    quota_visits INT NULL, -- Diisi jika paket berbasis kuota kedatangan
    max_members INT DEFAULT 1, -- > 1 jika paket Couple / Family / Corporate
    price DECIMAL(12,2) NOT NULL,
    allow_all_branches BOOLEAN DEFAULT FALSE,
    allowed_time_start TIME DEFAULT '00:00:00', -- Untuk pembatasan paket Off-Peak
    allowed_time_end TIME DEFAULT '23:59:59',
    max_freeze_days INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);

-- 5. LANGGANAN MEMBER AKTIF (SUBSCRIPTIONS)
CREATE TABLE user_subscriptions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id), -- Primary Holder
    parent_subscription_id BIGINT REFERENCES user_subscriptions(id) NULL, -- Untuk anggota grup/couple
    plan_id BIGINT REFERENCES membership_plans(id),
    invoice_id BIGINT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    remaining_quota INT NULL,
    remaining_freeze_days INT DEFAULT 0,
    status VARCHAR(30) NOT NULL, -- UPCOMING, ACTIVE, FROZEN, GRACE_PERIOD, EXPIRED, UPGRADED
    
    -- Akuntansi (Deferred Revenue Tracking)
    total_paid_amount DECIMAL(12,2) NOT NULL,
    daily_amortization_rate DECIMAL(12,2) NOT NULL, -- total_paid / duration_days
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 6. RIWAYAT CUTI (MEMBERSHIP FREEZES)
CREATE TABLE subscription_freezes (
    id BIGSERIAL PRIMARY KEY,
    subscription_id BIGINT REFERENCES user_subscriptions(id),
    requested_by BIGINT REFERENCES users(id),
    approved_by BIGINT REFERENCES users(id) NULL,
    freeze_start DATE NOT NULL,
    freeze_end DATE NOT NULL,
    total_days INT NOT NULL,
    document_proof_url VARCHAR(255),
    status VARCHAR(20) DEFAULT 'PENDING', -- PENDING, APPROVED, REJECTED, COMPLETED
    created_at TIMESTAMP
);

-- 7. LOG KEHADIRAN & GATE ACCESS (CHECK-IN / OUT)
CREATE TABLE attendance_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    branch_id BIGINT REFERENCES branches(id),
    subscription_id BIGINT REFERENCES user_subscriptions(id),
    check_in_at TIMESTAMP NOT NULL,
    check_out_at TIMESTAMP NULL,
    checkout_type VARCHAR(25) NULL, -- MANUAL_SCAN, AUTO_TIMEOUT_2H
    is_roaming_visit BOOLEAN DEFAULT FALSE, -- True jika branch_id != user.home_branch_id
    locker_number VARCHAR(20) NULL,
    verified_by_staff_id BIGINT REFERENCES users(id) NULL
);

-- 8. PAKET PERSONAL TRAINER (PT PACKAGES)
CREATE TABLE pt_packages (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    assigned_trainer_id BIGINT REFERENCES users(id), -- PT Utama
    invoice_id BIGINT,
    total_sessions INT NOT NULL,
    remaining_sessions INT NOT NULL,
    price_per_session DECIMAL(12,2) NOT NULL, -- Untuk pengakuan pendapatan & komisi per sesi
    expires_at DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'ACTIVE' -- ACTIVE, ON_HOLD_NO_MEMBERSHIP, EXHAUSTED, EXPIRED
);

-- 9. JADWAL & EKSEKUSI SESI PT (PT SESSIONS)
CREATE TABLE pt_sessions (
    id BIGSERIAL PRIMARY KEY,
    pt_package_id BIGINT REFERENCES pt_packages(id),
    user_id BIGINT REFERENCES users(id),
    scheduled_trainer_id BIGINT REFERENCES users(id),
    actual_trainer_id BIGINT REFERENCES users(id) NULL, -- Diisi jika digantikan PT Substitute
    member_checkin_log_id BIGINT REFERENCES attendance_logs(id) NULL, -- Wajib ada agar tidak fiktif!
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status VARCHAR(30) DEFAULT 'BOOKED', -- BOOKED, COMPLETED, CANCELLED_REFUNDED, BURNED_LATE_CANCEL
    verified_at TIMESTAMP NULL
);

-- 10. JADWAL KELAS STUDIO & BOOKING
CREATE TABLE class_schedules (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT REFERENCES branches(id),
    instructor_id BIGINT REFERENCES users(id),
    class_name VARCHAR(100) NOT NULL,
    room_name VARCHAR(50),
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    max_capacity INT NOT NULL,
    current_booked INT DEFAULT 0,
    status VARCHAR(25) DEFAULT 'SCHEDULED' -- SCHEDULED, COMPLETED, CANCELLED_BY_GYM
);

CREATE TABLE class_bookings (
    id BIGSERIAL PRIMARY KEY,
    class_schedule_id BIGINT REFERENCES class_schedules(id),
    user_id BIGINT REFERENCES users(id),
    status VARCHAR(30) NOT NULL, -- BOOKED, WAITLIST, ATTENDED, CANCELLED, NO_SHOW
    waitlist_position INT NULL,
    booked_at TIMESTAMP NOT NULL,
    cancelled_at TIMESTAMP NULL
);

-- 11. KASIR (POS), INVOICE & SHIFT KASIR
CREATE TABLE cashier_shifts (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT REFERENCES branches(id),
    cashier_id BIGINT REFERENCES users(id),
    opened_at TIMESTAMP NOT NULL,
    closed_at TIMESTAMP NULL,
    opening_cash DECIMAL(12,2) NOT NULL,
    system_expected_cash DECIMAL(12,2) DEFAULT 0, -- Disembunyikan saat kasir input Blind Opname
    actual_counted_cash DECIMAL(12,2) NULL,
    discrepancy_amount DECIMAL(12,2) NULL, -- Selisih uang kasir
    status VARCHAR(20) DEFAULT 'OPEN'
);

CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    branch_id BIGINT REFERENCES branches(id),
    user_id BIGINT REFERENCES users(id),
    cashier_shift_id BIGINT REFERENCES cashier_shifts(id) NULL,
    lead_owner_id BIGINT REFERENCES users(id) NULL, -- Untuk Split Komisi Sales
    closing_staff_id BIGINT REFERENCES users(id) NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(30), -- CASH, QRIS, VA, CC_RECURRING, EDC
    gateway_reference VARCHAR(100) NULL,
    status VARCHAR(25) DEFAULT 'PENDING', -- PENDING, PAID, VOID, REFUNDED
    void_authorized_by BIGINT REFERENCES users(id) NULL, -- Wajib PIN Owner/Manager
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP
);

-- 12. KOMISI STAF & CLAWBACK (PAYROLL)
CREATE TABLE staff_commissions (
    id BIGSERIAL PRIMARY KEY,
    staff_id BIGINT REFERENCES users(id),
    invoice_id BIGINT REFERENCES invoices(id) NULL,
    pt_session_id BIGINT REFERENCES pt_sessions(id) NULL,
    commission_type VARCHAR(30), -- LEAD_GEN, CLOSING_SALE, PT_TEACHING, CLAWBACK_DEDUCTION
    amount DECIMAL(12,2) NOT NULL, -- Bernilai negatif (-) jika Invoice di-Void/Refund
    status VARCHAR(20) DEFAULT 'EARNED', -- EARNED, PAID_IN_PAYROLL
    created_at TIMESTAMP
);

-- 13. PROGRES FISIK MEMBER (INBODY & WORKOUT LOG)
CREATE TABLE member_body_metrics (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    recorded_by BIGINT REFERENCES users(id),
    recorded_date DATE NOT NULL,
    weight_kg DECIMAL(5,2),
    body_fat_percentage DECIMAL(5,2),
    muscle_mass_kg DECIMAL(5,2),
    visceral_fat_level INT,
    private_photo_path VARCHAR(255) NULL, -- Disimpan di Private Storage S3/Local (Signed URL)
    notes TEXT
);
```

---

## 5. Spesifikasi Logika Bisnis Inti (Service Layer Blueprint)

Bagian ini adalah panduan penulisan kode pada direktori `app/Services/`:

### A. `AccessControlService::validateCheckIn($qrToken, $branchId)`
1. **Validasi TOTP:** Dekripsi `$qrToken` menggunakan `Google2FA::verifyKey($user->totp_secret, $otp, 1)`. Jika gagal, tolak akses (*Screenshot QR terdeteksi*).
2. **Cek Tunggakan Denda:** Jika `$user->unpaid_fine_amount > 0`, blokir pintu dan tampilkan alert di layar kasir: *"Tahan akses! Member memiliki denda belum dibayar."*
3. **Cek Status & Jam Paket:** Pastikan `status == 'ACTIVE'`, tanggal hari ini `<= end_date`, dan waktu sekarang berada di antara `allowed_time_start` dan `allowed_time_end`.
4. **Cek Cabang (Roaming):** Jika `$branchId != $user->home_branch_id`, pastikan `plan->allow_all_branches == true`. Tandai log dengan `is_roaming_visit = true`.
5. **Anti-Passback:** Cek apakah ada record di `attendance_logs` dalam 30 menit terakhir yang `check_out_at IS NULL`. Jika ada, tolak akses.
6. **Eksekusi:** Simpan log kehadiran, pancarkan event WebSocket `MemberCheckedIn` ke layar kasir, dan kirim respons HTTP `200 OK` ke mikrokontroler *turnstile*.

### B. `BookingService::bookClass($user, $scheduleId)`
1. **Cek Sanksi Penalti:** Jika `$user->booking_suspended_until > now()`, tolak booking (*Member sedang dalam masa penalti 3x No-Show*).
2. **Database Row Locking:** Jalankan `DB::transaction()` dan ambil jadwal menggunakan `ClassSchedule::where('id', $scheduleId)->lockForUpdate()->first()`.
3. **Alokasi Kursi vs Waitlist:**
   * Jika `current_booked < max_capacity`: Simpan dengan status `BOOKED`, increment `current_booked`.
   * Jika penuh: Simpan dengan status `WAITLIST`, berikan nomor urut `waitlist_position`.

### C. `BookingService::cancelBooking($user, $bookingId)`
1. Hitung selisih waktu: `$hoursDiff = now()->diffInHours($schedule->start_time, false)`.
2. **Jika `$hoursDiff >= 12` (Batal Tepat Waktu):**
   * Ubah status menjadi `CANCELLED`. Jika menggunakan paket kuota/PT, kembalikan kuota (`remaining_sessions + 1`).
   * **Auto-Promote Waitlist:** Cari urutan pertama di `WAITLIST` pada kelas tersebut, ubah menjadi `BOOKED`, dan kirim WhatsApp notifikasi.
3. **Jika `$hoursDiff < 12` (Batal Mendadak):**
   * Jika paket PT/Kuota: Status menjadi `BURNED_LATE_CANCEL` (kuota hangus, PT tetap dapat komisi).
   * Jika paket *Unlimited*: Tambahkan `$user->increment('no_show_strike_count')`. Jika mencapai `3`, isi `booking_suspended_until = now()->addDays(7)`.

### D. `PtSessionService::verifyCompletion($ptSessionId, $memberOtp)`
1. **Anti-Fiktif Check (Wajib):** Cari di tabel `attendance_logs` apakah Member (`user_id`) sudah melakukan *Check-in* di pintu masuk gym pada hari ini. **Jika belum ada log *check-in* fisik hari ini, TOLAK klaim sesi PT.**
2. Validasi `$memberOtp` dengan TOTP milik member.
3. Cek apakah Membership Gym utama milik member masih `ACTIVE`.
4. Ubah status sesi menjadi `COMPLETED`, kurangi `pt_packages.remaining_sessions - 1`, dan masukkan komisi mengajar ke `staff_commissions` untuk `actual_trainer_id`.

### E. `MembershipService::calculateProrataUpgrade($subscription, $newPlan)`
Digunakan saat member ingin *Upgrade* paket di tengah jalan:
1. Hitung sisa hari: `$remainingDays = now()->diffInDays($subscription->end_date)`.
2. Hitung sisa nilai uang paket lama: `$unusedValue = $remainingDays * $subscription->daily_amortization_rate`.
3. Tagihan Upgrade: `$amountToPay = max(0, $newPlan->price - $unusedValue)`.

---

## 6. Daftar Endpoints & Routing Utama (`routes/web.php` & `routes/api.php`)

### A. Webhook & Hardware API (`routes/api.php` - Stateless)
* `POST /api/webhooks/payment` — Menerima callback Midtrans/Xendit (Validasi Signature Key -> Aktifkan Paket).
* `POST /api/gate/scan` — Endpoint yang ditembak oleh scanner pintu *turnstile* fisik.
* `GET /api/pos/offline-sync` — Mengunduh daftar ringkas member aktif (`id, name, totp_secret, status`) terenkripsi setiap 15 menit ke IndexedDB browser kasir.

### B. Portal Member PWA (`routes/web.php` - Middleware: `auth, role:member, verify.device`)
* `GET /app/dashboard` — Sisa hari aktif, indikator *Live Crowd Meter*, jadwal terdekat.
* `GET /app/qr-pass` — Render QR TOTP dinamis (auto-refresh 60s di sisi klien Vue).
* `POST /app/classes/{id}/book` — Booking kelas studio / masuk *Waitlist*.
* `POST /app/subscriptions/freeze` — Upload bukti cuti & ajukan tanggal *Freeze*.

### C. Portal Kasir & Front Desk (`routes/web.php` - Middleware: `auth, role:front_desk, active.shift`)
* `GET /pos/live-gate` — Layar pantau WebSocket kedatangan member + input nomor loker.
* `POST /pos/walkin-register` — Registrasi member baru + upload tangkapan kamera *webcam* + tanda tangan *Digital Waiver*.
* `POST /pos/checkout` — Transaksi kasir (Membership / PT / Minuman POS).
* `POST /pos/shift/close` — Form *Blind Cash Opname* (kasir input uang fisik laci).

---

## 7. Penjadwalan Pekerja Otomatis (`routes/console.php` - Laravel Scheduler)

Tambahkan konfigurasi berikut pada *Task Scheduling* Laravel:

```php
use Illuminate\Support\Facades\Schedule;

// 1. Nonaktifkan member yang melewati batas masa aktif setiap tengah malam
Schedule::command('gym:expire-subscriptions')->dailyAt('00:01');

// 2. Aktifkan kembali member yang masa cuti (Freeze)-nya berakhir hari ini
Schedule::command('gym:unfreeze-subscriptions')->dailyAt('00:05');

// 3. Reset penalti no_show_strike_count setiap tanggal 1 awal bulan
Schedule::command('gym:reset-monthly-strikes')->monthlyOn(1, '00:10');

// 4. Auto-Checkout member yang lupa scan keluar setelah 120 menit (Akurasi Crowd Meter)
Schedule::command('gym:auto-checkout-ghost-members')->everyFifteenMinutes();

// 5. Batalkan invoice PENDING yang tidak dibayar lebih dari 60 menit
Schedule::command('gym:cancel-abandoned-invoices')->everyFiveMinutes();

// 6. Kirim pengingat WhatsApp untuk jadwal Kelas & Sesi PT H-2 Jam
Schedule::command('gym:send-schedule-reminders')->hourly();

// 7. Kirim pesan perpanjangan paket (H-7 dan H-3 sebelum expired)
Schedule::command('gym:send-renewal-reminders')->dailyAt('09:00');
```

---

## 8. Checklist Tahapan Eksekusi (Roadmap Pengerjaan)

- [ ] **Minggu 1 (Core & Auth):** Setup Laravel 11 + Inertia Vue 3, migrasi seluruh tabel database, seeder Role RBAC, dan CRUD Master Cabang & Paket.
- [ ] **Minggu 2 (POS & Akuisisi):** Buat halaman registrasi *walk-in* (integrasi Webcam & Signature Pad), sistem keranjang kasir POS, integrasi Payment Gateway (Midtrans/Xendit), dan *Blind Cash Opname*.
- [ ] **Minggu 3 (Access Control & Offline Mode):** Implementasi TOTP QR Generator di Vue Member, Scanner Gate di Kasir, WebSocket *Live Gate Monitor*, dan *IndexedDB Offline Caching*.
- [ ] **Minggu 4 (Scheduling & PT Management):** Buat kalender kelas studio, sistem booking dengan `lockForUpdate()`, antrean *Waitlist*, *Session Burn* PT dengan syarat *check-in* fisik, dan aturan *3-Strike Penalty*.
- [ ] **Minggu 5 (Lifecycle & Retention):** Fitur *Membership Freeze*, kalkulator *Upgrade Prorata*, pencatatan *InBody Metrics* (dengan *Private Signed URL*), dan buku tamu *Free Trial*.
- [ ] **Minggu 6 (Accounting, Cron & Audit):** Laporan *Cash vs Deferred Revenue*, *Payroll & Commission Clawback*, pemasangan seluruh *Cron Jobs* & integrasi WhatsApp Gateway.

---

## 9. Spesifikasi Desain UI/UX & Layout Antarmuka (Design System & Wireframes)

### A. Design System & Tema Warna (Tailwind CSS)
* **Theme Member (Mobile PWA):** Dark Athletic Mode (`bg-zinc-950`, card `bg-zinc-18181B`, aksen *Electric Lime* `#A3E635` untuk tombol aksi utama, font *Plus Jakarta Sans* & *JetBrains Mono* untuk hitung mundur QR).
* **Theme Kasir & Backoffice (Desktop):** Clean Light Slate Mode (`bg-slate-50`, card `bg-white`, teks kontras tinggi, aksen *Indigo/Emerald* untuk status gate).
* **Semantic Status Colors:** 
  * `Emerald (#10B981)` = Aktif / Gate Terbuka / Kursi Tersedia
  * `Amber (#F59E0B)` = Waitlist / Sisa Masa Aktif < 5 Hari / Cuti
  * `Rose (#E11D48)` = Expired / Gate Ditolak / Denda Belum Lunas

### B. Portal Member (Mobile PWA - `max-w-md`)
* **Home Dashboard:** Menampilkan sisa hari aktif, kartu *Live Crowd Meter* persentase kapasitas gym secara *real-time*, jadwal kelas terdekat dengan tombol cepat batalkan (>12 jam), serta ringkasan sisa sesi Personal Trainer.
* **Modal Dynamic QR Pass (Bottom-Sheet):** Diaktifkan melalui tombol melayang di tengah navigasi bawah (*Floating Action Button*). Menampilkan foto wajah terverifikasi, kode TOTP QR ukuran besar yang auto-refresh setiap 60 detik, progres bar hitung mundur, dan PIN OTP untuk klaim sesi PT.
* **Booking & Progres:** Kalender tanggal harian untuk pilih kelas (*Studio vs PT*), indikator status penalti *No-Show*, dan grafik garis ganda tren penurunan berat badan vs kenaikan massa otot.

### C. Portal Kasir & Front Desk (Desktop Split-Screen `1920x1080`)
* **Panel Kiri (40% - Live Gate Feed):** Terhubung via WebSocket. Menampilkan pop-up foto wajah dan detail member secara instan saat *scan* berhasil (berkedip hijau), disertai auto-focus keyboard untuk input nomor kunci loker dan peringatan medis PAR-Q / Denda.
* **Panel Right (60% - POS & Quick Registration):** Katalog produk/paket berbentuk kartu grid, pencarian cepat (Shortcut `F2`), keranjang transaksi interaktif, serta tombol pembayaran cepat Tunai (`F8`), QRIS/VA (`F9`), dan EDC. Termasuk modal *Blind Cash Opname* saat tutup shift kasir.

### D. Portal Personal Trainer (Mobile PWA)
* **Timeline Jadwal Harian:** Daftar sesi klien terurut waktu dari pagi hingga malam dengan status gate (*Sudah Check-in di Gym* atau *Belum*).
* **Modal Verifikasi Sesi (Anti-Fiktif):** Tombol klaim komisi sesi yang mewajibkan input PIN OTP 60 detik dari HP member serta validasi otomatis bahwa member tersebut benar-benar sudah melakukan *Check-in* fisik di pintu masuk hari ini.

### E. Portal Owner (Executive Bento Grid Dashboard)
* **Kuadran 1 (Akuntansi Keuangan):** Perbandingan langsung antara *Cash Basis Revenue* vs *Accrual Earned Revenue* (setelah dikurangi amortisasi deferred revenue paket tahunan & PT).
* **Kuadran 2 (Audit & Retensi):** Notifikasi merah/kuning instan untuk selisih kasir (*Discrepancy*), member yang akan kedaluwarsa minggu ini, dan deteksi PT *idle* di lantai gym.
* **Kuadran 3 & 4:** *Heatmap* jam sibuk mingguan dan tabel rekapitulasi *Payroll* serta *Commission Clawback* (potongan komisi otomatis jika invoice di-refund).
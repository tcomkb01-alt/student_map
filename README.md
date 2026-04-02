# 🏫 Student Home Visit Map System — Documentation

ระบบบริหารจัดการแผนที่และบันทึกการเยี่ยมบ้านนักเรียนแบบพรีเมียม (Premium Home Visit & Mapping System)

---

## 🔑 Quick Credentials
| Field | Value |
|---|---|
| **Admin URL** | `http://localhost/student_map/admin/login.php` |
| **Username** | `admin` |
| **Password** | `admin1234` |
| **Map Pass** | `admin` (Configure in `.env`) |

---

## ✨ Features & Modules

### 1. 🚀 Splash Screen (Entry)
- หน้า Loading Screen แบบพรีเมียมก่อนเข้าสู่ระบบ
- อนิเมชั่นล้ำสมัย พร้อมระบบเตรียมความพร้อมข้อมูลอัตโนมัติ

### 2. 🗺️ Interactive Map (Public & Admin)
- แสดงพิกัดบ้านนักเรียนทั้งหมดบนแผนที่ Leaflet.js
- ระบบค้นหาและตัวกรองแยกตามห้องเรียน (Class Filter)
- รองรับมุมมองแผนที่ปกติและดาวเทียม (Satellite View)
- ปุ่มทางลัดนำทาง (Google Maps / Apple Maps)

### 3. 📋 Home Visit System (Visit Log)
- บันทึกการเยี่ยมบ้านดิจิทัล: วันที่, ผลการเยี่ยม, บันทึกข้อความ และการติดตามผล
- ระบบอัปโหลดรูปภาพหลักฐานการเยี่ยมบ้าน
- รายงานสรุปผลความคืบหน้าแบบรายห้องเรียน (Interactive Progress Report)
- ดูรายชื่อนักเรียนที่ยังไม่ได้เยี่ยมผ่าน Modal แยกตามห้อง

### 4. 🧩 App Center
- ศูนย์รวมเครื่องมือจัดการระบบ: บันทึกการเยี่ยม, รายงานสรุปผล และแผนการเตรียมข้อมูล

### 5. 👥 User Management & Security
- ระบบจัดการผู้ใช้ (เพิ่ม/ลบ/แก้ไข/เปลี่ยนรหัสผ่าน)
- แบ่งสิทธิ์การใช้งาน (Admin / Admin_Editor)
- ป้องกันหน้าแผนที่สาธารณะด้วยรหัสผ่านผ่านไฟล์ `.env`

---

## 🚀 Installation & Setup

### Step 1 — Copy files
คัดลอกโฟลเดอร์ `student_map` ไปไว้ใน Web Root ของคุณ (เช่น `C:\AppServ\www\`)

### Step 2 — Configuration (.env)
1. คัดลอกไฟล์ `.env` (หรือสร้างใหม่ถ้าไม่มี)
2. แก้ไขข้อมูลการเชื่อมต่อฐานข้อมูลและรหัสผ่านแผนที่:
```env
DB_HOST=localhost
DB_NAME=student_map
DB_USER=root
DB_PASS=your_password
BASE_URL=http://localhost/student_map
MAP_PASSWORD=admin
```

### Step 3 — Database Setup
1. เข้าไปที่ `phpMyAdmin`
2. สร้างฐานข้อมูลชื่อ `student_map` (Charset: `utf8mb4_unicode_ci`)
3. **Import** ไฟล์ `database.sql` เข้าไปในฐานข้อมูลที่สร้างขึ้น

### Step 4 — Folder Permissions
ตรวจสอบให้แน่ใจว่าโฟลเดอร์เหล่านี้สามารถเขียนข้อมูลได้ (Writable):
- `uploads/students/` (รูปประจำตัวนักเรียน)
- `uploads/visits/` (รูปหลักฐานการเยี่ยมบ้าน)

---

## 🗂️ Project Structure

```
student_map/
│
├── index.php            ← 🚀 Splash / Entry Screen
├── .env                 ← 🔧 Security & Connection Config
│
├── admin/               ← 🔐 Admin Panel
│   ├── login.php        
│   ├── dashboard.php    ← Statistics & Map Overview
│   ├── students.php     ← CRUD + CSV Import + Location Setter
│   ├── users.php        ← RBAC User Management [NEW]
│   ├── apps.php         ← 🧩 App Center Hub
│   ├── visit_log.php    ← 📋 Data Entry for Visits
│   └── visit_report.php ← 📊 Interactive Analysis Report
│
├── public/              ← 🗺️ Public View
│   ├── index.php        
│   ├── map.php          ← Password Protected Map
│   └── api.php          
│
├── includes/            ← ⚙️ Core Logic
│   ├── config.php       ← Env Loader & Constants
│   ├── db.php           ← PDO Singleton
│   └── auth.php         
│
├── components/          ← 🧩 Shared UI Components
├── assets/              ← 🎨 CSS, JS, Images
└── uploads/             ← 🖼️ User Content (images)
```

---

## 📊 CSV Import Format
Required columns in CSV (Names must match exactly):
`student_number`, `student_id`, `prefix`, `first_name`, `last_name`, `class`, `parent_phone` (opt), `address` (opt), `latitude` (opt), `longitude` (opt)

---

## 🔧 Troubleshooting
- **Thai Characters Error**: ตรวจสอบว่า `config.php` และฐานข้อมูลเป็น `utf8mb4`
- **Images Not Showing**: ตรวจสอบ `UPLOAD_DIR` ใน `config.php` และสิทธิ์โฟลเดอร์ `uploads/`
- **Map Not Loading**: ต้องมีการเชื่อมต่ออินเทอร์เน็ตเพื่อดึง Leaflet CDN

---
*Developed with Modern PHP & Vanilla CSS Aesthetics*

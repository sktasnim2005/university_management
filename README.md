# 🎓 University Management System

A full-stack **University Management System** built with pure PHP, MySQL, and vanilla CSS — no frameworks. Features role-based dashboards for Admin, Faculty, and Student with complete CRUD operations across all modules.

🌐 **Live Demo:** [universitymanagement.infinityfree.me/login](https://universitymanagement.infinityfree.me/login)

---

## 📸 Preview

| Admin Dashboard | Student Dashboard | Faculty Dashboard |
|----------------|-------------------|-------------------|
| Full system control | Personal profile & grades | Courses & attendance |

---

## ✨ Features

### 🔐 Authentication & Roles
- Session-based login system with SHA-256 password hashing
- 3 completely separate dashboards — **Admin**, **Faculty**, **Student**
- Role-based access control on every page and action
- Automatic redirect to login if not authenticated

### 👨‍🎓 Student Management
- Full CRUD — add, edit, delete, search students
- Search by name, roll number, or email
- Students can only view their own profile
- Status tracking — Active, Inactive, Graduated, Dropped

### 👨‍🏫 Faculty Management
- Faculty profiles with department assignments
- Faculty can only view their own profile
- Admin can manage all faculty records
- Designation, qualification, salary tracking

### 🏢 Department Management
- Create and manage academic departments
- Live count of students and faculty per department
- Department code, HoD name, established year

### 📚 Course Management
- Assign courses to departments and faculty
- Credit hours, semester, and max student capacity
- Courses linked to enrollments and grades

### 📋 Enrollment System
- Enroll students into courses
- Duplicate enrollment prevention
- Status tracking — Enrolled, Dropped, Completed
- Admin-only access

### 🎯 Grade Management
- Auto grade calculation from marks (A+ to F)
- CGPA points system (4.00 scale)
- Faculty and admin can assign/update grades
- Students can view their own grades only

### 📅 Attendance Tracking
- Mark attendance per course per date
- Present / Absent / Late status
- Bulk "All Present" / "All Absent" buttons
- Attendance percentage with low attendance warning (< 75%)
- Students see their own attendance summary

### 💰 Fee Management
- Multiple fee types — Tuition, Hostel, Library, Lab, Exam, Transport
- Auto status calculation — Paid, Partial, Pending, Overdue
- Faculty completely blocked from fee records
- Students see only their own fee records

### 🎨 UI / UX
- Clean, responsive design — works on mobile and desktop
- POST-Redirect-GET pattern — no duplicate submissions, no modal re-opening
- Flash messages that auto-dismiss after 4 seconds
- Smooth modal animations
- Color-coded badges and stat cards

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.5 (no framework) |
| Database | MySQL 8 |
| Frontend | Vanilla CSS (Grid + Flexbox + CSS Variables) |
| Icons | Font Awesome 6 |
| DB Tool | MySQL Workbench |
| Dev Environment | VS Code, macOS M5 |
| Hosting | InfinityFree |

---

## 📁 Project Structure

```
university_management/
├── index.php               # Front controller / URL router
├── login.php               # Login page
├── logout.php              # Session destroy + redirect
├── css/
│   └── style.css           # All styles
├── js/
│   └── script.js           # Modal, flash, search, confirm
├── includes/
│   ├── config.php          # DB constants, session, auth helpers
│   ├── db.php              # mysqli connection ($conn)
│   ├── header.php          # Navbar + flash message renderer
│   └── footer.php          # Footer + script tag
└── modules/
    ├── dashboard.php       # Role-based dashboard
    ├── students.php        # Student CRUD
    ├── faculty.php         # Faculty CRUD
    ├── courses.php         # Course CRUD
    ├── departments.php     # Department CRUD
    ├── enrollments.php     # Enrollment management
    ├── grades.php          # Grade entry + auto calculation
    ├── fees.php            # Fee records + payment tracking
    └── attendance.php      # Attendance marking + summary
```

---

## 🗄️ Database Schema

```
departments ──┬── students ──┬── enrollments ──┬── grades
              │               │                 └── attendance
              └── faculty ────┤
                              └── fees
courses ──────────────────────┘
              │
              └── faculty (assigned teacher)

users (login table)
  └── ref_id → student_id / faculty_id / 0 (admin)
```

**Tables:** `departments`, `students`, `faculty`, `courses`, `users`, `enrollments`, `grades`, `attendance`, `fees`

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.0+
- MySQL 8.0+
- MySQL Workbench (optional but recommended)

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/yourusername/university-management.git
cd university-management
```

**2. Create the database**

Open MySQL Workbench and run:
```sql
CREATE DATABASE manage_uni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE manage_uni;
```

Then import the schema:
```bash
mysql -u root -p manage_uni < database/schema.sql
```

**3. Configure database connection**

Open `includes/config.php` and update:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'manage_uni');
define('SITE_URL', 'http://localhost:3000');
```

**4. Start the PHP server**
```bash
php -S localhost:3000 index.php
```

**5. Open in browser**
```
http://localhost:3000
```

---

## 👤 Default Login

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin123` |

To create faculty or student logins, first add them via the Admin panel, then run in MySQL:

```sql
-- Faculty login (ref_id = faculty_id)
INSERT INTO users (username, password, role, ref_id)
VALUES ('faculty_username', SHA2('password', 256), 'faculty', 1);

-- Student login (ref_id = student_id)
INSERT INTO users (username, password, role, ref_id)
VALUES ('student_username', SHA2('password', 256), 'student', 1);
```

---

## 🔒 Security Features

- All user inputs sanitized with `htmlspecialchars()`
- Prepared statements with `mysqli` to prevent SQL injection
- `intval()` used on all ID parameters
- Role checks on every POST and DELETE action
- Session-based authentication — no client-side trust
- Passwords hashed with SHA-256

---

## 📐 Architecture Decisions

**Why no framework?**
This was built as a DBMS course project to deeply understand how web applications work at the fundamental level — routing, sessions, SQL, prepared statements — without any framework abstracting it away.

**POST-Redirect-GET pattern**
Every form submission redirects after saving. This prevents duplicate submissions on refresh and ensures modals close cleanly after every action.

**Single `$conn` connection**
All modules share one mysqli connection created in `db.php`. No reconnecting per query, no PDO/mysqli mixing.

**Role-based data filtering**
Queries are scoped at the SQL level based on the logged-in user's role and `ref_id` — students can never access other students' data even by manipulating URLs.

---

## 🙋‍♂️ Author

**Tasnim**
- Live Demo: [universitymanagement.infinityfree.me](https://universitymanagement.infinityfree.me/login)
- Built as a DBMS course project



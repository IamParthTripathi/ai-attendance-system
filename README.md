# AttendAI — AI-Powered Student Attendance System

<div align="center">

![AttendAI Banner](https://img.shields.io/badge/AttendAI-Face%20Recognition%20Attendance-4F46E5?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0id2hpdGUiIGQ9Ik0xMiAyQTEwIDEwIDAgMCAwIDIgMTJhMTAgMTAgMCAwIDAgMTAgMTAgMTAgMTAgMCAwIDAgMTAtMTBBMTAgMTAgMCAwIDAgMTIgMk0xMiA0YTggOCAwIDAgMSA4IDggOCA4IDAgMCAxLTggOCA4IDggMCAwIDEtOC04IDggOCAwIDAgMSA4LThtMCA0YTQgNCAwIDAgMC00IDQgNCA0IDAgMCAwIDQgNCA0IDQgMCAwIDAgNC00IDQgNCAwIDAgMC00LTRtMCA4YTYgNiAwIDAgMC02IDZoMTJhNiA2IDAgMCAwLTYtNnoiLz48L3N2Zz4=)

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Python](https://img.shields.io/badge/Python-3.11-3776AB?style=flat-square&logo=python&logoColor=white)](https://python.org)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.111-009688?style=flat-square&logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](LICENSE)

**Upload a classroom photo. AI marks everyone present — instantly.**

[Live Demo](#live-demo) · [Features](#features) · [Architecture](#architecture) · [Setup Guide](#setup-guide) · [API Reference](#api-reference)

</div>

---

## What Is This?

AttendAI is a full-stack web application that automates student attendance using **facial recognition**. A teacher uploads 1–3 classroom photos, and the system identifies each student's face, marks them present or absent in MySQL, and generates exportable reports — all in seconds.

No clickers. No paper. No roll calls.

### Live Demo

> Live demo (UI): 🔗 **[IamParthTripathi.github.io/ai-attendance-system](https://IamParthTripathi.github.io/ai-attendance-system)** — Backend requires local setup, see README for instructions.


Demo credentials:
| Role    | Email                  | Password    |
|---------|------------------------|-------------|
| Admin   | admin@school.com       | admin123    |
| Teacher | teacher@school.com     | teacher123  |

---

## Features

**For Teachers**
- Upload 1–3 classroom photos per session and let AI mark attendance automatically
- Override any entry with one-click manual attendance correction
- View attendance history on a visual calendar
- Export attendance records as CSV with a single click

**For Admins**
- Manage teachers, classes, and student rosters from a central panel
- Add student photos and regenerate face encodings at any time
- Full visibility across all classes and dates

**Technical Highlights**
- Three-tier microservice architecture (Frontend → PHP API → Python AI)
- Face recognition powered by `dlib`'s 128-dimension face encoding model
- Deduplication across multiple images in the same session
- Session-based authentication with CSRF-safe cookie handling
- Graceful error handling and toast notifications throughout the UI

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│              Browser (HTML/CSS/JS)               │
│   index · dashboard · upload · reports · admin  │
└────────────────────┬────────────────────────────┘
                     │ fetch() / JSON
                     ▼
┌─────────────────────────────────────────────────┐
│            PHP Backend (Apache / XAMPP)          │
│  auth.php · upload.php · process.php            │
│  attendance.php · students.php · classes.php    │
│                   │                             │
│            MySQL Database                       │
│  users · classes · students · attendance        │
└────────────────────┬────────────────────────────┘
                     │ HTTP multipart/form-data
                     ▼
┌─────────────────────────────────────────────────┐
│         Python AI Service (FastAPI)              │
│              face_engine.py                     │
│   face_locations → face_encodings → compare     │
│   Returns: { present_rolls: ["CS001", ...] }    │
└─────────────────────────────────────────────────┘
```

### Tech Stack

| Layer        | Technology                                          |
|--------------|-----------------------------------------------------|
| Frontend     | HTML5, CSS3 (custom design system), Vanilla JS      |
| Backend      | PHP 8.x, PDO, Session Auth                         |
| Database     | MySQL 8 with foreign key constraints                |
| AI Service   | Python 3.11, FastAPI, face_recognition (dlib), OpenCV, NumPy |
| Dev Server   | XAMPP (Apache + MySQL), Uvicorn                     |

---

## Project Structure

```
attendance-system/
├── README.md
├── LICENSE
├── .gitignore
│
├── database/
│   └── schema.sql              ← Import this into MySQL first
│
├── ai-service/                 ← Python microservice
│   ├── app.py                  ← FastAPI entry point (start this)
│   ├── face_engine.py          ← Face detection & matching logic
│   ├── encode_students.py      ← Run once to build face database
│   ├── requirements.txt        ← pip dependencies
│   ├── student_photos/         ← Place <roll_number>.jpg here
│   └── encodings/              ← Auto-generated .pkl face vectors
│
├── backend/                    ← PHP REST API
│   ├── config/
│   │   └── db.php              ← DB credentials + AI service URL
│   ├── middleware/
│   │   └── auth_check.php      ← Session authentication guard
│   └── api/
│       ├── auth.php            ← POST login / DELETE logout / GET session
│       ├── classes.php         ← Class CRUD
│       ├── students.php        ← Student CRUD
│       ├── teachers.php        ← Teacher/user management (admin only)
│       ├── upload.php          ← Classroom image upload
│       ├── process.php         ← Orchestrates AI call + attendance write
│       ├── attendance.php      ← Fetch attendance records
│       └── manual_attendance.php ← Override AI decisions manually
│
└── frontend/                   ← Static web UI
    ├── index.html              ← Login page
    ├── dashboard.html          ← Overview & stats
    ├── upload.html             ← Upload photos & trigger AI
    ├── attendance.html         ← Calendar view + reports + CSV export
    ├── admin.html              ← Manage users, classes, students
    ├── manual.html             ← Manual attendance entry
    ├── css/
    │   └── style.css           ← Full custom design system
    └── js/
        └── config.js           ← API base URL, auth helpers, utilities
```

---

## Setup Guide

### Prerequisites

Before you begin, install these on your machine:

- **XAMPP** (Apache + MySQL + PHP 8.x) → https://www.apachefriends.org
- **Python 3.11** → https://www.python.org/downloads/release/python-3119/
- **Git** → https://git-scm.com

---

### Step 1 — Clone the Repository

```bash
git clone https://github.com/your-username/ai-attendance-system.git
cd ai-attendance-system
```

---

### Step 2 — Place Files in XAMPP

Copy the entire `attendance-system/` folder into XAMPP's web root:

```
C:\xampp\htdocs\attendance-system\
```

The app will be accessible at: `http://localhost/attendance-system/frontend/`

---

### Step 3 — Enable PHP cURL

1. Open `C:\xampp\php\php.ini` in any text editor
2. Find `;extension=curl` and remove the semicolon → `extension=curl`
3. Save the file and restart Apache in XAMPP Control Panel

---

### Step 4 — Set Up the Database

1. Start **Apache** and **MySQL** in XAMPP Control Panel
2. Open `http://localhost/phpmyadmin` in your browser
3. Click the **Import** tab at the top
4. Click **Choose File** → select `database/schema.sql`
5. Click **Go**

You should see: *"Import has been successfully finished."*

The database is now created with two demo accounts (see [Live Demo](#live-demo) above).

---

### Step 5 — Set Up the Python AI Service

Open a terminal inside the `ai-service/` folder:

```bash
cd C:\xampp\htdocs\attendance-system\ai-service
```

Create and activate a virtual environment:

```bash
python -m venv venv
venv\Scripts\activate
```

Install dependencies **in this exact order** (dlib must come before face-recognition):

```bash
pip install cmake
pip install dlib
pip install face-recognition
pip install fastapi uvicorn python-multipart Pillow opencv-python-headless numpy
```

> **If dlib fails on Windows**, download the pre-built wheel from:
> https://github.com/z-mahmud22/Dlib_Windows_Python3.x
> Choose `dlib-19.24.1-cp311-cp311-win_amd64.whl`, then:
> ```bash
> pip install dlib-19.24.1-cp311-cp311-win_amd64.whl
> ```

Verify the installation worked:

```bash
python -c "import face_recognition; print('SUCCESS')"
```

---

### Step 6 — Add Student Photos & Generate Encodings

Place one clear, front-facing photo per student in `ai-service/student_photos/`. Name each file after the student's roll number:

```
student_photos/
├── CS001.jpg
├── CS002.jpg
└── CS003.jpg
```

Then generate the face encodings:

```bash
python encode_students.py
```

You should see `[OK]` for each student. This creates `.pkl` files in the `encodings/` folder — these are the mathematical face vectors the AI compares against during recognition.

---

### Step 7 — Start the AI Service

```bash
cd C:\xampp\htdocs\attendance-system\ai-service
venv\Scripts\activate
uvicorn app:app --host 0.0.0.0 --port 5000 --reload
```

Verify it's running: open `http://localhost:5000/health` → should return `{"status":"ok"}`.

**Keep this terminal open** while using the app.

---

### Step 8 — Open the Application

Navigate to:

```
http://localhost/attendance-system/frontend/
```

Log in with `admin@school.com` / `admin123` and you're live.

---

### Daily Workflow

Every time you start working:

1. Open XAMPP → Start **Apache** and **MySQL**
2. In a terminal, run:
   ```bash
   cd C:\xampp\htdocs\attendance-system\ai-service
   venv\Scripts\activate
   uvicorn app:app --host 0.0.0.0 --port 5000
   ```
3. Open `http://localhost/attendance-system/frontend/`

---

## API Reference

All PHP endpoints live at `/attendance-system/backend/api/`. All responses are JSON.

| Method   | Endpoint               | Auth     | Description                              |
|----------|------------------------|----------|------------------------------------------|
| `POST`   | `auth.php`             | No       | Login — body: `{email, password}`        |
| `DELETE` | `auth.php`             | Session  | Logout                                   |
| `GET`    | `auth.php`             | Optional | Check session status                     |
| `GET`    | `classes.php`          | Session  | List all classes                         |
| `POST`   | `classes.php`          | Admin    | Create a class                           |
| `GET`    | `students.php`         | Session  | List students (filter by `?class_id=`)   |
| `POST`   | `students.php`         | Admin    | Add a student                            |
| `DELETE` | `students.php`         | Admin    | Delete a student                         |
| `POST`   | `upload.php`           | Session  | Upload classroom images → returns paths  |
| `POST`   | `process.php`          | Session  | Run AI on uploaded images → mark attendance |
| `GET`    | `attendance.php`       | Session  | Fetch records (filter by class + date)   |
| `POST`   | `manual_attendance.php`| Session  | Override attendance status manually      |

The Python AI service exposes two endpoints:

| Method | Endpoint    | Description                               |
|--------|-------------|-------------------------------------------|
| `GET`  | `/health`   | Returns `{"status":"ok"}`                 |
| `POST` | `/process`  | Multipart: `class_id` + `images[]` → present rolls |

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| "Connection error" on login | Make sure Apache is running in XAMPP |
| "Cannot reach AI service" | Start `uvicorn` in the `ai-service` folder |
| "No face detected" in results | Use a clearer, front-facing photo (min 400×400 px) |
| All students show Absent | Re-run `encode_students.py` after adding student photos |
| cURL error in PHP | Enable `extension=curl` in php.ini and restart Apache |
| phpMyAdmin login fails | Leave the password field blank (XAMPP default) |
| Port 5000 already in use | Run `uvicorn app:app --port 5001` and update `AI_SERVICE_URL` in `db.php` |
| `dlib` install fails | Use the pre-built wheel (see Step 5 above) |

---

## Contributing

Pull requests are welcome! Please open an issue first to discuss what you'd like to change. For major changes, provide context on the problem you're solving and your proposed approach.

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-improvement`
3. Commit your changes: `git commit -m "feat: describe your change"`
4. Push to the branch: `git push origin feature/my-improvement`
5. Open a Pull Request

---

## License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for full details.

You are free to use, modify, and distribute this project for personal or commercial purposes. Attribution is appreciated but not required.

---

## Author

Built by **[Parth Tripathi]**

- GitHub: [@IamParthTripathi](https://github.com/IamParthTripathi)
- LinkedIn: [linkedin.com/in/iamparthtripathi](https://linkedin.com/in/iamparthtripathi)
- Email: iamparthtripathi@gmail.com

---

<div align="center">
<sub>If this project helped you, consider giving it a ⭐ — it helps others find it.</sub>
</div>

# University Admission Portal

A complete online admission management system: students apply, upload documents, and
track their application; administrators review, score, rank, and export applicants —
all from a web browser.

## Features

**Student portal**
- Account signup/login with password reset (secure token flow: `random_bytes`,
  expiry, single-use)
- Online application with **80+ programme options** across faculties
- Document uploads (certificates, transcripts) with an `.htaccess`-guarded uploads directory
- Application status tracking and profile editing

**Admin panel**
- Application review with full applicant detail view
- **Custom scoring & ranking algorithm** — applicants are scored against programme
  requirements and ranked automatically
- Bulk export of applications
- Separate admin authentication

## Tech stack

| Layer | Tech |
|---|---|
| Backend | PHP 8 (PDO, prepared statements throughout) |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, vanilla JavaScript (client-side validation) |
| Auth | `password_hash` / `password_verify`, session-based |

## Getting started

1. Run a local PHP + MySQL stack (XAMPP/WAMP/LAMP, PHP 8.0+).
2. Create a database and import `db/ub_admission.sql`.
3. Copy `config.sample.php` → `config.php` and fill in your DB credentials
   (`config.php` is git-ignored, so credentials never reach version control).
4. Serve the project root with Apache (mod_rewrite enabled) and open `index.php`.
5. Create the initial admin account via `setup_db.php`, then remove that file.

## Project structure

```
├── admin/          # admin panel: applications, ranking, export
├── db/             # schema (ub_admission.sql)
├── includes/       # auth, shared functions, layout partials
├── js/             # client-side validation
├── uploads/        # applicant documents (.htaccess protected)
├── application.php # the application form
├── dashboard.php   # student dashboard
└── index.php
```

---

Built by **Kwame Boateng** ([@kwame12396](https://github.com/kwame12396)) — AI automation
& full-stack development.

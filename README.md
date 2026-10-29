# Health Records Management System — Admin Module

A PHP + MySQL (PDO) admin panel for managing patient health records, built for XAMPP.

## Features
- Shared login page for Admin, Doctor, and patient accounts (session-based, bcrypt-hashed passwords)
- Doctor accounts can use the administrative patient-record tools, calendar, laboratory requests, certificates, archives, and user management
- Admin and Doctor accounts can assign or change User, Doctor, and Admin roles
- Register and verify SHA-256 document hashes with a tamper-evident chained integrity ledger
- Monitor timestamped audit activity, filter it, and generate printable/exportable audit reports
- Create, Read (list + view), Update, Delete patient records
- Archive inactive patient records without deleting them, and view or restore all old records
- Search patients by name
- Review complete previous patient record versions after edits
- Create and print clinician-authored laboratory requests from a patient's consultation
- Schedule patient consultations to an account-specific calendar
- Generate clinician-reviewed medical certificate drafts
- Give linked patient accounts access to their own records, appointments, laboratory requests, certificates, and record history
- Publish doctor clinic status, regular hours, and planned absences to patient accounts

## Setup (XAMPP)

1. **Copy the project folder**
   Copy this whole `health_records` folder into your XAMPP `htdocs` directory, e.g.:
   `C:\xampp\htdocs\health_records` (Windows) or `/Applications/XAMPP/htdocs/health_records` (Mac).

2. **Start Apache and MySQL** in the XAMPP Control Panel.

3. **Import the database**
   Open `http://localhost/phpmyadmin`, click **Import**, and select
   `database/health_records_db.sql`. This creates the `health_records_db` database with
   the `admins`, `users`, `patients`, `patient_history`, `lab_requests`, `appointments`,
   and `medical_certificates` tables. The seed accounts are skipped if they already exist,
   so the script can be imported again to add missing tables to an existing installation.

4. **Check the DB config**
   `config/database.php` is already set for the default XAMPP MySQL user (`root`, no password).
   Change it there if your setup differs.

5. **Open the app**
   Visit `http://localhost/health_records/` in your browser. It will redirect you to the
   admin login page.

## Default Admin Login
- **Username:** `admin`
- **Password:** `admin123`

⚠️ Change this password (or add a new admin and delete this one) before using this with
real patient data.

## Patient History and Laboratory Requests
- Editing a patient record saves the complete prior version and the admin account that made the change. Open a patient and expand **Previous Record Versions** to review it.
- The patient detail page contains a laboratory request form. Consultation/diagnosis and notes are prefilled as clinical context; the clinician enters the tests to request and can print the saved request.
- Tests are selected by the clinician; the application does not infer or recommend tests. History is stored in the local database and is not blockchain-backed.
- Re-import `database/health_records_db.sql` in phpMyAdmin to add the new tables to an existing installation. Existing patient data is not dropped.
- Existing installations automatically receive the `archived_at` column when the application connects. If the database user cannot alter tables, run `ALTER TABLE patients ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER notes;` in phpMyAdmin.
- Schedule a face-to-face or follow-up appointment from a patient record. Appointments appear on the signed-in admin account's calendar, overlapping scheduled visits are rejected, and events can be marked completed or cancelled.
- The calendar is internal to this application; it does not synchronize with Google Calendar or Outlook.
- Generate a medical certificate draft from a patient record. Findings and recommendations are clinician-entered, and each printout states that it requires clinician verification and signature.
- Admin accounts manage clinical records, appointments, and certificates. Doctor-role user accounts can publish availability but do not yet have access to clinical charts or appointment management.
- In **Users → Edit**, an admin can link one User account to one patient record. The patient dashboard shows only that linked record; new accounts must be linked by clinic staff before records are visible.
- Doctor-role accounts can sign in through the regular user login and publish status, clinic location/hours, public messages, and planned absence dates from **My availability**. Patients can view active doctors' published information on their dashboard.

## Project Structure
```
health_records/
├── config/
│   └── database.php        # PDO DB connection
├── includes/
│   └── session.php         # shared session starter
├── database/
│   └── health_records_db.sql
├── admin/
│   ├── auth_check.php      # login guard, include at top of protected pages
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php       # READ: active patient list + search
│   ├── old_records.php     # READ: archived patient records
│   ├── create.php          # CREATE
│   ├── edit.php            # UPDATE
│   ├── view.php            # READ: patient details, history, and lab requests
│   ├── lab_request_create.php
│   ├── lab_request.php     # Printable laboratory request
│   ├── appointment_create.php
│   ├── calendar.php        # Account-specific appointment calendar
│   ├── medical_certificate_create.php
│   ├── medical_certificate.php
│   ├── archive.php         # ARCHIVE / RESTORE
│   └── includes/
│       ├── header.php
│       └── footer.php
└── index.php                # redirects to admin/login.php
```

## Next Steps
This covers the **admin** side. Natural next additions:
- A public/staff-facing section with more limited permissions
- Pagination on the dashboard table for large record sets
- File uploads (e.g. lab results, scanned documents) attached to a record
- An audit log of who created/edited/deleted each record

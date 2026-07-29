# Parliament of Uganda Cafeteria Management System

## Overview

The Parliament of Uganda Cafeteria Management System is a web-based application developed to streamline cafeteria operations within Parliament. The system provides a secure authentication portal and serves as the foundation for managing food orders, departmental billing, payments, reports, and administrative activities.

This project was developed using:

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- Font Awesome

---

## System Requirements

- PHP 8.x or later
- MySQL / MariaDB
- Apache Web Server
- XAMPP, WAMP or Laragon
- Modern Web Browser (Chrome, Edge, Firefox)

---

## Installation

### 1. Clone or Copy the Project

Place the project inside your web server directory.

Example (XAMPP):

```
htdocs/
    parliament-cafeteria/
```

---

### 2. Import the Database

Open phpMyAdmin.

Create a database named:

```
parliament_cafeteria
```

Import the provided SQL file:

```
database/parliament_cafeteria.sql
```

---

### 3. Configure Database Connection

Open:

```
config/database.php
```

Update the credentials if necessary.

```php
$host="localhost";
$db="parliament_cafeteria";
$user="root";
$pass="";
```

---

### 4. Run the System

Open your browser and visit:

```
http://localhost/parliament-cafeteria/public/index.php
```

---

# Demo Login Credentials

| Username | Password | Role |
|-----------|----------|------|
| admin | admin123 | Administrator |
| sarah.nabachwa | password123 | Accountant |

---

# Current Features

- Secure Login
- Session Authentication
- Dashboard Layout
- Sidebar Navigation
- Logout Functionality
- Responsive User Interface

---

# Planned Features

- Food Ordering
- Daily Menu Management
- Department Billing
- Payments
- Credit Accounts
- Catering Events
- Reports
- Audit Logs
- User Management

---

# Folder Structure

```
parliament-cafeteria/

public/
│
├── index.php
├── dashboard.php
├── login_access.php
├── logout.php
│
├── css/
│
└── assets/

config/
│
└── database.php

database/
│
└── parliament_cafeteria.sql
```

---

# Security

Current Version

- Session Authentication
- Prepared Statements
- Input Validation

Future Improvements

- Password Hashing
- CSRF Protection
- Role-Based Access Control
- Audit Logging

---

# Developer

Developed by

**IT INTERNS 2026**

Bachelor of Information Technology & Computing

Kyambogo University

ICT Intern

Parliament of Uganda

2026

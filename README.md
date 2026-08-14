# TAPP — Digital Attendance

## Database
This project connects to the **remote MySQL database** configured in `backend/.env`:

- Host: `sql63.jnb2.host-h.net`
- Database: `digitcexse_db2`

Login, employee registration, attendance, and leave all read/write that database.

## Run locally

From this folder (project root):

```bash
php -S localhost:8000 router.php
```

Then open: http://localhost:8000/login.php

## Optional migration (force password change on first login)

```sql
ALTER TABLE `users`
  ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0
  AFTER `status`;
```

Until this is applied, the app still works; the first-login password popup simply will not appear.

## Features added
1. Admin enrols employees → server auto-generates password (admin cannot set it manually)
2. Employee first login → forced password-change popup (employee portal only)
3. Employee profile → full name, employee ID, department are read-only; only contact (email) editable

# BSU Academic Administration Portal

A university-wide portal whose primary user is the **Authorized Academic Administrator**. It supports academic office staff explicitly authorized to manage institutional academic records, rather than individual faculty accounts. It tracks incoming applicants, enrolled students, the class schedule, grade submissions, at-risk students and faculty evaluations, with charts on every page.

It is plain PHP and MySQL with no frameworks and nothing to install or build. It comes with a sample university (about 5,800 students, 1,000 classes and 3,200 applicants) that is created automatically the first time you open it.

## What you need

- [XAMPP](https://www.apachefriends.org/) with **PHP 8.1 or newer** (developed and tested on PHP 8.5 with MariaDB 10.4)
- A web browser

Nothing else. There is no Composer or npm step.

## Set up

1. **Put the project in XAMPP's web folder**, in a folder named `Faculty`:

   ```
   cd C:\xampp\htdocs
   git clone https://github.com/Lindon-Arinton/Faculty.git
   ```

   Or download the ZIP from GitHub and extract it so that the files are in `C:\xampp\htdocs\Faculty`.

2. **Start Apache and MySQL** in the XAMPP Control Panel.

3. **Open the portal:** <http://localhost/Faculty/>

4. **Sign in:**

   | Field | Value |
   |---|---|
   | Email | `facultyfaculty` |
   | Password | `12345` |

The first sign-in takes a few seconds longer than usual. During it the portal creates the `veritas_portal` database, its tables, the login account and all the sample records. You do not need to create a database or import anything yourself.

## Settings

All settings are in [`config.php`](config.php).

| Setting | Default | What it is |
|---|---|---|
| `DB_HOST`, `DB_USER`, `DB_PASS` | `127.0.0.1`, `root`, empty | MySQL connection. These are XAMPP's defaults; change them if your MySQL has a password. |
| `DB_NAME` | `veritas_portal` | Database name. It is created if it does not exist. |
| `APP_NAME` | `BSU` | Name shown in the sidebar, page titles and footer. |
| `DEFAULT_USER_EMAIL`, `DEFAULT_USER_PASS` | `facultyfaculty`, `12345` | The login account, created only when the `users` table is empty. |

The logo is [`assets/img/logo.png`](assets/img/logo.png). Replace that file to change it.

### Changing the login

Editing `DEFAULT_USER_EMAIL` or `DEFAULT_USER_PASS` has no effect once the account exists. To apply new values, empty the `users` table in phpMyAdmin (<http://localhost/phpmyadmin>, database `veritas_portal`) and open the portal again; it recreates the account from `config.php`.

## Access and responsibilities

- The primary user is the **Authorized Academic Administrator**.
- This role manages applicants, enrollment, schedules, grade submissions, student interventions, faculty records, and aggregate evaluations across colleges.
- Faculty members are managed records in this portal; a Faculty role does not grant login access.
- The server checks the stored user role on login and every protected request, including record changes and exports.
- Existing default credentials are unchanged. Only the original `Faculty Office` demonstration account is migrated automatically to the administrator role. Other accounts require explicit authorization; they are not promoted automatically.
- For an existing deployment, run `database/migrations/001_academic_administrator.sql` to update the role default and the original demonstration account. This migration does not delete users or academic records.

## Using the portal

| Page | What you can do |
|---|---|
| Overview | See school-wide figures; filter everything by college |
| Incoming enrollment | Add, edit and delete applicants and their submitted requirements |
| Campus schedule | Add, edit and delete classes and their weekly meetings; see room conflicts |
| Enrollment | Add, edit and delete students |
| Grade submissions | Open a class to encode grades, save a draft, submit, or reopen |
| At-risk students | Log, edit and delete interventions |
| Faculty evaluations | Add, edit and delete faculty; view evaluation results |

- **Add** buttons are at the top right of each page. **Edit** (pencil) and **Delete** (bin) are at the end of each table row. Deleting always asks first.
- **Charts** show exact figures on hover. Clicking a bar, donut slice or funnel step filters the list below; clicking a legend entry hides or shows that series.
- **Export CSV** downloads the list as currently filtered.
- **Search** with `Ctrl` + `K`. The **?** button at the bottom right opens a short guide.

The portal treats **27 May 2025** as "today", because that is the date of the sample data. Records you add are dated that day.

## Sample data

| Task | How |
|---|---|
| Rebuild the sample records from scratch | `C:\xampp\php\php.exe database\seed.php` (run from the project folder) |
| Load the ready-made copy instead | Import [`database/veritas_portal.sql`](database/veritas_portal.sql) into a database named `veritas_portal` in phpMyAdmin |

Both of these **replace all records**, including anything you added or changed in the portal. Login accounts are kept.

`database/schema.sql` holds the table definitions and is needed by the rebuild; do not delete it.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Cannot reach the database" on the login page | MySQL is not running. Start it in the XAMPP Control Panel. |
| Page not found | Check that the folder is `C:\xampp\htdocs\Faculty` and Apache is running. If Apache uses another port, add it: `http://localhost:8080/Faculty/`. |
| Blank page or a PHP error mentioning `never` or `array_is_list` | PHP is older than 8.1. Install a newer XAMPP. |
| "Too many failed attempts" | Wait 5 minutes, or close the browser to start a new session. |

## Project layout

```
Faculty/
├── config.php            Settings: database, name, default login
├── login.php, logout.php Sign in and out
├── index.php             Overview dashboard
├── incoming.php          Applicants
├── schedule.php          Class timetable
├── enrollment.php        Students
├── grades.php, class.php Grade submissions and each class's roster
├── at-risk.php           At-risk students and interventions
├── evaluations.php       Faculty and evaluation results
├── search.php            Search across all records
├── actions.php           Saves every add, edit and delete
├── includes/             Shared code: database, queries, page shell, forms, charts, sample-data builder
├── assets/               Styles, scripts and the logo
└── database/             Table definitions, rebuild script, sample-data export
```

## Before putting this online

This is set up for a local machine. Before hosting it anywhere public, change the login in `config.php` to a strong password, give MySQL a password, and serve the site over HTTPS.

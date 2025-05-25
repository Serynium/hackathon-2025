# Budget Tracker – Evozon PHP Internship Hackathon 2025

## Starting from the skeleton

Prerequisites:

- PHP >= 8.1 with the usual extension installed, including PDO.
- [Composer](https://getcomposer.org/download)
- Sqlite3 (or another database tool that allows handling SQLite databases)
- Git
- A good PHP editor: PHPStorm or something similar

About the skeleton:

- The skeleton is built on Slim (`slim/slim : ^4.0`)
- The templating engine of choice is Twig (`slim/twig-view`)
- The dependency injection container of choice is `php-di/php-di`
- The database access layer of choice is plain PDO
- The configuration should be provided in a .env file (`vlucas/phpdotenv`)
- There is logging support by using `monolog/monolog`
- Input validation should be simply done using `webmozart/assert` and throwing Slim dedicated HTTP exceptions

## Step-by-step set-up

Install dependencies:

```
composer install
```

Set up the database:

```
cd database
./apply_migrations.sh
```

Note: be aware that, if you are using WSL2 (Windows Subsystem for Linux), you'll have trouble opening SQLite databases
with a DB management app (PHPStorm, for example) in Windows **when they are stored within the virtualized WSL2 drive**.
The solution is to store the `db.sqlite` file on the Windows drive (`/mnt/c`) and configure the path to the file in the
application config (`.env`):

```
cd database
./apply_migrations.sh /mnt/c/Users/<user>/AppData/Local/Temp/db.sqlite
```

Copy `.env.example` to `.env` and configure as necessary:

```
cp .env.example .env
```

Run the built-in server on http://localhost:8000

```
composer start
```

## Features

## Tasks

### Before you start coding

Make sure you inspect the skeleton and identify the important parts:

- `public/index.php` - the web entry point
- `app/Kernel.php` - DI container and application setup
- classes under `app` - this is where most of your code will go
- templates under `templates` are almost complete, at least in terms of static mark-up; all you need is to make use of
  the Twig syntax to make them dynamic.

### Main tasks — for having a functional application

Start coding: search for `// TODO: ...` and fill in the necessary logic. Don't limit yourself to that; you can do
whatever you want, design it the way you see fit. The TODOs are a starting point that you may choose to use.

### Extra tasks — for extra points

Solve extra requirements for extra points. Some of them you can implement from the start, others we prefer you to attack
after you have a fully functional application, should you have time left. More instructions on this in the assignment.

### Deliver well designed quality code

Before delivering your solution, make sure to:

- format every file and make sure there is no commented code left, and code looks spotless

- run static analysis tools to check for code issues:

```
composer analyze
```

- run unit tests (in case you added any):

```
composer test
```

A solution with passing analysis and unit tests will receive extra points.

## Delivery details

Participant:
- Full name: Vlad Bordianu
- Email address: bordianu16@gmail.com

Features fully implemented:
- Authentication System:
  - Register (/register)
    - GET: Displays registration form
    - POST: Creates new user account
    - Validates username (≥ 4 chars) and password (≥ 8 chars, 1 number)
    - On success: Redirects to /login on success
    - On failure: Renders /register and shows corresponding error messages.
  - Login (/login)
    - GET: Displays login form
    - POST: Authenticates user and creates session
    - On success: Redirects to Dashboard on success
    - On failure: Renders /login and shows corresponding error messages.
  - Logout (/logout)
    - GET: Destroys user session
    - Redirects to /login
- Expenses Management:
  - Expenses – List (/expenses)
    - GET: Page with expenses table (20 rows/page by default)
    - Lists monthly expenses for the logged-in user, sorted by date descending and paginated
    - Year-month selector (current year always available, previous years shown if user had expenses)
    - Columns: description, amount (formatted €), category, "Edit" link, "Delete" link
    - Pagination controls: previous/next page, total items
    - "Add" button navigates to Expenses – Add
    - "Edit"/"Delete" links navigate to respective routes
  - Expenses – Add (/expenses/create)
    - GET: Renders form to add a new expense (date, category, amount, description)
    - Backend validation: Date ≤ today, category selected, amount > 0, description not empty
    - On success: Redirects to Expenses – List
    - On failure: Redirects back to Add with prefilled values
  - Expenses – Edit (/expenses/{id}/edit)
    - GET: Renders pre-filled edit form for given expense
    - POST: Updates expense with same validation as Add
    - On success/failure: Redirects as above
  - Expenses – Delete (/expenses/{id}/delete)
    - POST: Hard-deletes expense by ID
    - On success/failure: Redirects to Expenses – List
  - Expenses – CSV Import (on Expenses – List page)
    - Upload form for importing expenses from CSV (no header: date, description, amount, category)
    - Skips duplicates and unknown categories, logs skipped rows and import summary
    - On success/failure: Redirects to Expenses – List
- Dashboard (/)
  - GET: Overview page with monthly expenses summary and overspending alerts
  - Year/month selectors for summary (defaults to current)
  - Shows total expenditure, per-category totals and averages
  - Overspending alerts for current month if category budget exceeded (categories configured in env, not DB)

Other instructions about setting up the application (if any): ...

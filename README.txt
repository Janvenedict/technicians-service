SERVICE TECHNICIANS MANAGER - v2 (with Jobs, Login, CSV Export, API)
=======================================================================

WHAT'S NEW IN THIS VERSION
---------------------------
1. LOGIN / AUTHENTICATION
   - The whole app is now behind a login screen (session-based).
   - Default account: username "admin", password "admin123"
   - Change this later by updating the "users" table, or add your own
     account via a quick SQLite insert (see NOTES below).

2. JOBS + TECHNICIAN ASSIGNMENT
   - New "Jobs" section: create/edit/view/delete service jobs.
   - Each job can be assigned to a technician from a dropdown
     (either on the Jobs list page for a quick change, or on the
     job's edit form).
   - Deleting a technician automatically unassigns (not deletes)
     any jobs that were assigned to them.
   - Filter jobs by status or by technician (including "Unassigned").

3. CSV EXPORT
   - "Export CSV" button on both the Technicians and Jobs pages.
   - Downloads a UTF-8 CSV file with all current records
     (respects nothing else — it always exports the full table).

4. JSON API
   - A full JSON REST-style API at api.php for both technicians and jobs.
   - Secured with a per-user API key (shown on the "API Access" page
     after logging in, with a "Regenerate Key" button).
   - Supports GET (list/single), POST (create), PUT (update),
     DELETE — see the API Access page in-app for exact endpoints
     and ready-to-copy curl examples.

5. UI
   - Redesigned with a persistent sidebar (Technicians / Jobs /
     Export CSV / API Access) and a cleaner topbar showing who's
     logged in.
   - Consistent styling shared across all pages via assets/style.css.

REQUIREMENTS
------------
- PHP 7.4+ with pdo_sqlite extension enabled (bundled with XAMPP/
  WAMP/MAMP and most standard PHP installs).

HOW TO RUN (Quickest - PHP's built-in server)
-----------------------------------------------
1. Unzip this folder anywhere on your computer.
2. Open a terminal in the folder.
3. Run:
       php -S localhost:8000
4. Open your browser to:
       http://localhost:8000
5. Log in with username "admin" / password "admin123".
6. The database (technicians.db) is created automatically on first
   load, with 3 sample technicians, 4 sample jobs, and the admin
   account.

HOW TO RUN (XAMPP / WAMP / MAMP / Apache)
--------------------------------------------
1. Copy this folder into your web root (htdocs / www).
2. Start Apache.
3. Visit http://localhost/<folder-name>/index.php

FILE OVERVIEW
-------------
- db.php            -> Database connection, schema, seed data
- auth.php          -> Login/session/API-key helper functions
- login.php         -> Login page
- logout.php        -> Ends the session
- index.php         -> Technicians CRUD (list/add/edit/view/delete)
- jobs.php          -> Jobs CRUD + technician assignment
- export_csv.php    -> CSV export for technicians or jobs
- api.php           -> JSON API (technicians + jobs), key-protected
- api_info.php      -> Shows your API key + usage examples
- includes/         -> Shared page layout (sidebar/topbar)
- assets/style.css  -> Shared stylesheet
- technicians.db    -> Auto-created SQLite database (after first run)

USING THE API - QUICK EXAMPLE
------------------------------
    curl "http://localhost:8000/api.php?resource=technicians" \
      -H "X-API-Key: YOUR_KEY_HERE"

Find your actual key on the "API Access" page after logging in.

NOTES
-----
- All queries use prepared statements (SQL-injection safe) and all
  output is escaped (XSS safe).
- To reset all data, stop the server, delete technicians.db, and
  restart - it will be recreated with sample data and a fresh
  admin account (with a new random API key).
- To add a second user account, you can run a short PHP snippet:

      php -r '
      $pdo = new PDO("sqlite:technicians.db");
      $pdo->prepare("INSERT INTO users (username, password_hash, api_key) VALUES (?, ?, ?)")
          ->execute(["newuser", password_hash("newpassword", PASSWORD_DEFAULT), bin2hex(random_bytes(16))]);
      echo "User created.\n";
      '

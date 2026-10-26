# PawCare — Pet Adoption and Foster Tracker

Midterm Exam submission for **Information Management 2 (PHP Data Objects & OOP)**.
Main entity: **Pet** (from the *Pet Adoption and Foster Tracker* persona/ERD project — Kristine Nicole Rios, Rescue Coordinator).

## Project Structure

```
pawcare/
├── config/
│   └── Database.php      # PDO connection class (PDO::ATTR_ERRMODE_EXCEPTION)
├── classes/
│   └── Pet.php            # Pet entity class — OOP + PDO CRUD and search
├── public/
│   ├── index.php          # Dashboard: pet listing table + live search
│   ├── add-pet.php        # Add-pet form and POST handler
│   ├── edit-pet.php       # Edit-pet form (pre-populated) and update handler
│   ├── delete-pet.php     # Delete handler
│   └── assets/
│       └── header.php     # Shared PawCare-branded navigation header
├── schema.sql              # DDL + seed data
└── README.md
```

## Setup

1. Create the database: import `schema.sql` into MySQL/MariaDB
   (`mysql -u root -p < schema.sql`).
2. Update the credentials in `config/Database.php` if your local
   MySQL user/password differ from `root` / *(empty)*.
3. Serve the `public/` folder (e.g. `php -S localhost:8000 -t public`)
   and open `http://localhost:8000/index.php`.

## Explanation of the Five Core Processes

### 1. Adding a Record
`add-pet.php` renders an HTML `<form method="POST">` with fields matching the
`PETS` columns (`name`, `breed`, `age`, `gender`, `intake_date`, `status`,
`species_id`). On submit, the script checks `$_SERVER['REQUEST_METHOD'] === 'POST'`,
reads and trims each `$_POST` value, and validates that required fields are not
empty. The cleaned values are passed positionally into
`Pet::addPet($name, $breed, $age, $gender, $intake_date, $status, $species_id)`.
Inside that method, the class prepares an `INSERT INTO PETS (...) VALUES (:name, :breed, ...)`
statement with `$this->conn->prepare()`, binds every value with
`bindParam(':placeholder', $variable, PDO::PARAM_*)`, and calls `execute()`
inside a `try/catch` block. Prepared statements mean the raw form input is
never concatenated into the SQL string, which prevents SQL injection. On
success, `add-pet.php` redirects back to `index.php` so the new pet appears
in the listing immediately.

### 2. Fetching Records
`Pet::getPets()` builds a `SELECT` that joins `PETS` with `SPECIES` on
`species_id` so the listing can show `species_name` instead of a raw foreign
key. It prepares and executes the query, then calls
`$stmt->fetchAll(PDO::FETCH_ASSOC)`, which returns every matching row as an
associative array (column name => value). `index.php` calls `getPets()` once
at the top of the page, stores the result in `$pets`, and loops through it
with `foreach ($pets as $p) { ... }` inside the HTML `<table>`, printing one
`<tr>` per pet and escaping every value with `htmlspecialchars()` to prevent
XSS when the data is echoed back into the page.

### 3. Updating a Record
When a user clicks **Edit** on a row, they are sent to
`edit-pet.php?id=<pet_id>`. On a normal `GET` request, the script reads `id`
from the query string, calls `Pet::getPetById($id)` (a `SELECT ... WHERE
pet_id = :id`), and uses the returned associative array to pre-fill every
`value="..."` attribute in the form — this is how the form arrives already
populated with the pet's current details. When the same form is submitted
back to `edit-pet.php` via `POST`, the script re-reads the fields from
`$_POST`, validates them, and calls
`Pet::updatePet($id, $name, $breed, $age, $gender, $intake_date, $status, $species_id)`.
That method prepares an `UPDATE PETS SET name = :name, ... WHERE pet_id = :id`
statement, binds each field plus the `:id` placeholder, and executes it —
only the one row whose `pet_id` matches is modified.

### 4. Deleting a Record
Each row's **Delete** link points to `delete-pet.php?id=<pet_id>` and has a
JavaScript `confirm()` prompt to prevent accidental clicks. `delete-pet.php`
reads `id` from `$_GET`, casts it to an integer, and calls
`Pet::deletePet($id)`. That method prepares `DELETE FROM PETS WHERE pet_id =
:id`, binds `:id` with `PDO::PARAM_INT`, and executes it inside a
`try/catch`. Because the `id` is bound rather than interpolated, a malicious
or malformed query string cannot alter or inject into the SQL. After
deletion, the script redirects to `index.php?deleted=1`, which shows a
confirmation banner and an up-to-date table (the deleted row's `INNER JOIN`
to `SPECIES` no longer returns it because the row itself is gone).

### 5. Searching Records
The search box on `index.php` is a `GET` form that submits a `q` parameter
back to the same page. If `q` is non-empty, `index.php` calls
`Pet::searchPets($keyword)` instead of `getPets()`. That method wraps the
keyword as `"%{$keyword}%"` and binds it once to a single `:keyword`
placeholder reused across five `OR` conditions —
`name LIKE :keyword OR breed LIKE :keyword OR gender LIKE :keyword OR status
LIKE :keyword OR s.species_name LIKE :keyword` — so one search box matches
partial text in any of those columns (e.g. searching "fost" matches pets
with status "Fostered"). The matching rows are returned with `fetchAll()`
and rendered through the same table-loop used for the full listing, so
search results and the default listing share one code path.

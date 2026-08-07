# Cloud Database Setup (TiDB Cloud)

The HIMS database lives on **TiDB Cloud Serverless**. This document is the
complete setup path from a fresh clone to a working connection.

## Why TiDB Cloud

TiDB Cloud speaks the **MySQL wire protocol**. That matters for two practical
reasons:

- The app needs no new PHP driver. `pdo_mysql` ships with XAMPP and is already
  enabled; Supabase would have required enabling `pdo_pgsql` in `php.ini` first.
- Existing MySQL tooling still works. You can point phpMyAdmin, MySQL Workbench,
  DBeaver or TablePlus at the cluster and browse it like any MySQL database.

Laravel needs no code changes either — `DB_CONNECTION=mysql` is all it takes,
because Laravel's MySQL grammar is what TiDB expects.

## One-time setup

### 1. Create the cluster

1. Sign up at <https://tidbcloud.com/>
2. Create a **Serverless** cluster (free tier, ready in about 30 seconds)
3. Pick the region closest to you — for the Philippines, `ap-southeast-1`
   (Singapore) has the lowest latency

### 2. Create the database

In the TiDB console open **SQL Editor** and run:

```sql
CREATE DATABASE IF NOT EXISTS hims;
```

The default cluster ships with a `test` database, but naming it explicitly
keeps things obvious when several projects share an account.

### 3. Get the credentials

Go to **Cluster → Connect → General**. TiDB shows a connection string like:

```
mysql://4KmzKfKNbxxxxxx.root:<password>@gateway01.ap-southeast-1.prod.aws.tidbcloud.com:4000/test
```

Map its parts onto `.env`:

| Connection string part                             | `.env` key       |
| -------------------------------------------------- | ---------------- |
| `4KmzKfKNbxxxxxx.root`                             | `DB_USERNAME`    |
| `<password>`                                       | `DB_PASSWORD`    |
| `gateway01.ap-southeast-1.prod.aws.tidbcloud.com`  | `DB_HOST`        |
| `4000`                                             | `DB_PORT`        |
| `hims` (the database created in step 2)            | `DB_DATABASE`    |

The username **keeps its cluster prefix** — `4KmzKfKNbxxxxxx.root`, not `root`.
Dropping the prefix is the single most common cause of an access-denied error.

### 4. Fill in `.env`

```dotenv
DB_CONNECTION=mysql
DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=hims
DB_USERNAME=4KmzKfKNbxxxxxx.root
DB_PASSWORD=your-password-here
MYSQL_ATTR_SSL_CA="D:/HIMS/HIMS/storage/certs/isrgrootx1.pem"
```

Two things that will cost you an hour if you get them wrong:

- **`MYSQL_ATTR_SSL_CA` must be an absolute path.** PDO does not resolve paths
  relative to the project root. Use forward slashes even on Windows.
- **Quote the path** if it contains spaces.

TLS is mandatory on TiDB Cloud Serverless — the connection is refused without a
valid CA. The certificate (Let's Encrypt ISRG Root X1) is committed at
`storage/certs/isrgrootx1.pem`, so a fresh clone already has it. It is a public
root CA, not a secret.

### 5. Build the schema

```bash
php artisan migrate:fresh --seed
```

### 6. Verify

```bash
php artisan db:check
```

This reports the driver, server version and a row count per key table. A
successful run against the cloud prints `Driver: mysql` and a TiDB version
string — if it prints `Driver: sqlite`, `.env` was not picked up (see
Troubleshooting).

## Working offline

The SQLite file at `supply_chain` still works as a fallback for when you have no
network. Swap the connection in `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=supply_chain
```

Then `php artisan migrate --seed`. Remember the two databases hold separate
data — a record created offline is not in the cloud.

Automated tests are unaffected either way: `phpunit.xml` pins them to an
in-memory SQLite database, so `php artisan test` never touches the cloud and
stays fast.

## Troubleshooting

**`SQLSTATE[HY000] [1045] Access denied`**
The username is missing its cluster prefix. It must read `<prefix>.root`.

**`SQLSTATE[HY000] [2002] Connection refused` / TLS errors**
`MYSQL_ATTR_SSL_CA` is wrong, relative, or pointing at a file that does not
exist. Confirm with `php artisan tinker --execute="echo env('MYSQL_ATTR_SSL_CA');"`
and check the file is really at that path.

**Config changes appear to be ignored**
Laravel caches configuration. Run `php artisan config:clear`.

**`php artisan db:check` still reports sqlite**
Either the config cache is stale (above), or `.env` still has an active
`DB_CONNECTION=sqlite` line further down the file overriding the intended one —
the *last* assignment wins.

## Notes for future integration

Other hospital modules can point at the same cluster by copying the same five
`DB_*` values. Two things to decide when that happens:

- **Separate database vs. shared.** A separate `CREATE DATABASE` per module on
  the same cluster keeps schemas from colliding while still being one server and
  one bill.
- **A dedicated user per module.** The cluster's `root` user is convenient for a
  capstone but grants everything. TiDB supports `CREATE USER` / `GRANT` in the
  SQL Editor if module-level isolation is wanted later.

Serverless clusters have a free-tier request quota and will throttle rather than
bill you once it is exhausted. For a capstone demo this is not a practical
limit, but it is worth knowing before a live presentation that a long seeding
loop is not free.

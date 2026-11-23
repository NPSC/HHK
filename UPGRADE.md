# Upgrade notes

Manual steps for upgrading an existing site. Each section lists what has to be done by hand on the server; anything the site update (patchSQL) does on its own is not listed.

## .env config and public/ web root

Site configuration moved from `conf/site.cfg` to a `.env` file, and everything served to browsers moved into `public/`. Code, config, `vendor/`, `sql/` and `patch/` stay outside the web root.

Do these in order, on each site.

### 1. Create `.env` from `site.cfg`

Copy `.env.example` to `.env` in the site root, next to `public/` (not inside it), and copy the values over from `conf/site.cfg`:

| site.cfg                    | .env                   |
|-----------------------------|------------------------|
| `[site] sitePepper`         | `SITE_PEPPER`          |
| `[db] DBMS`                 | `DBMS`                 |
| `[db] URL`                  | `DB_URL`               |
| `[db] User`                 | `DB_USER`              |
| `[db] Password`             | `DB_PASSWORD`          |
| `[db] Schema`               | `DB_SCHEMA`            |
| `[db] ReadonlyUser`         | `DB_READONLY_USER`     |
| `[db] ReadonlyPassword`     | `DB_READONLY_PASSWORD` |

- Passwords are still stored encrypted. Copy them exactly as they are; don't decrypt them.
- `[site] SSL`, `[backup]` and `[webServices]` aren't used any more and have no `.env` equivalent.
- `DB_SCHEMA` must match the old `Schema` exactly. The session cookie name is built from it, so if it changes, everyone is logged out.
- Make `.env` readable by the web server user **and** by the user the cron jobs run as. Nobody else needs it.
- Without `.env`, every page (and every cron run) stops with "Site configuration file (.env) is missing."

### 2. Move custom files out of `conf/`

Logos and images that pages link to now live in `public/conf/`. Move the site's own files there from the old `conf/` folder: receipt, statement and registration logos, SAML IdP logos, and the `img/` folder used by confirmation form templates, if the site has one.

### 3. Point the web server at `public/`

Change the vhost's document root from the site folder to its `public/` subfolder, and update the matching `<Directory>` block. Apache example:

```apache
DocumentRoot /var/www/<site>/public
<Directory /var/www/<site>/public>
    AllowOverride All
</Directory>
```

Do this for both the `:80` and `:443` vhosts, then reload the web server. URLs don't change (`/house/`, `/admin/`, … stay the same).

Check it: `https://<site>/.env` and `https://<site>/vendor/autoload.php` must return 404, and the login page must load.

### 4. Update the crontab

Script paths moved under `public/`:

| Old                     | New                            |
|-------------------------|--------------------------------|
| `admin/ws_cron.php`     | `public/admin/ws_cron.php`     |
| `house/GenLedger.php`   | removed, see below             |

The scripts no longer need a `cd` into their folder first, so `php /var/www/<site>/public/admin/ws_cron.php` works from anywhere.

**GL transfer (sites with `useGLCodes` on):** `GenLedger.php` is gone; the transfer is now a Job Scheduler job instead of its own crontab line. Until the job is set up, no GL file is sent.

1. Delete the `GenLedger.php` crontab line. The script no longer exists, so the line only produces "file not found" errors.
2. In the Job Scheduler, add a **General Ledger Transfer** job. Set it to monthly, on or after the fiscal month start day (Site Configuration, `fm_Start_Day`). Set the notification email; it defaults to `NotificationAddress`.
3. Do a dry run first. The log should show the period and the number of journal lines, and nothing is uploaded.

The job only runs if the site has the `ws_cron.php` crontab line from the table above.

### 5. Clean up

Once the site works from `public/`, delete what's left of the old layout in the site root:

- `admin/`, `house/`, `install/`, `api/`, `auth/`, `js/`, `css/`, `images/`, `index.php`, `favicon.svg`, `robots.txt`
- `conf/`, after checking nothing from step 2 is still in it. `site.cfg` holds the database credentials, so delete it rather than leaving it around.

Unzipping a release over a site only adds files, so these won't go away by themselves.

### Developers

- Run `composer install` (adds `vlucas/phpdotenv`).
- Local `phpstan.neon` (not in git): change `admin`, `house`, `install` paths to `public/admin` etc.
- `public/debugbar-assets` is a symlink into `vendor/` for the debug bar; it only works with dev dependencies installed and is left out of release zips.

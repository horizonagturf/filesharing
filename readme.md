# Files Sharing

>  
> FILES SHARING VERSION 2 JUST RELEASED
>  


## Description

This PHP application based on Laravel 12 allows to share files like Wetransfer. You may install it **on your own server**. It **does not require** a traditional database — bundle and user data is stored as JSON flat files in the storage folder via [Orbit](https://github.com/ryangjchandler/orbit). It is **multilingual** and comes with english, french, german and korean translations for now. You're welcome to help translating the app.

This application provides two links per bundle :
- a bundle preview link : you can send this link to your recipients who will see the bundle content. For example: http://yourdomain/bundle/dda2d646b6746b96ea9b?auth=965242. The recipient can see all the files of the bundle and download the bundle as a ZIP archive.
- a bundle download link : you can send this link yo your recipients who will download all the files of the bundle at once (without any preview). For example: http://yourdomain/bundle/dda2d646b6746b96ea9b/download?auth=965242.

Each of these links comes with an authorization code. This code is the same for the preview and the download links.

The application also comes with a Laravel Artisan command as a background task who will physically remove expired bundle files of the storage disk. This command is configured to run every five minutes among the Laravel scheduled commands.

## Features

- **uploader access permission**: IP based or login/password
- **bundle's settings**: title, description, expiration date, number max of downloads, password...
- upload one or more files via drag and drop or via browsing your filesystem
- ability to keep adding files to the bundle days later
- sharing link with bundle content preview
- download rate limiter
- ability to download the entire bundle as ZIP archive (password protected when applicable)
- direct download link (doesn't preview the bundle content)
- garbage collector which removes the expired bundles as a background task
- multilingual (EN, FR, DE and KR)
- easy installation, **no database required**
- secured by tokens, authentication codes and non-publicly-accessible files

## Demo

### Online Demo

You may visit my [Online Demo](https://filesharing.webinno.fr/)

### Video Demo

A video demo is available [on Youtube](https://youtu.be/hO4tRaZa4N4)

### Screenshot

![demo image](https://github.com/axeloz/filesharing/blob/main/public/images/capture.png "Demo Image")

## Requirements

Basically, nothing more than Laravel itself:
- PHP >= 8.3
- Ctype PHP Extension
- OpenSSL PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension

Plus:
- JSON PHP Extension
- ZipArchive PHP Extension

The application also uses:
- http://www.dropzonejs.com/
- https://alpinejs.dev/
- https://tailwindcss.com/
- https://day.js.org/
- https://axios-http.com/

## Installation

### Docker

You may now install FileSharing via Docker. 
See [https://hub.docker.com/r/axeloz/filesharing](https://hub.docker.com/r/axeloz/filesharing)

```
docker run -d \
-p 8080:80 \
-v <local_path>:/app/storage/content \
--name filesharing \
-e APP_NAME="FileSharing" \
-e APP_URL="<your_url>" \
-e APP_KEY="<your_generated_key>" \
-e ASSET_URL="<your_asset_url>" \
-e UPLOAD_MAX_FILESIZE="1G" \
-e APP_TIMEZONE="Europe/Paris" \
-e UPLOAD_PREVENT_DUPLICATES=true \
-e HASH_MAX_FILESIZE="1G" \
-e UPLOAD_MAX_FILES=100 \
-e LIMIT_DOWNLOAD_RATE="100K" \
axeloz/filesharing:latest
```
- use the `-v` option to bind your local storage to the docker instance (persisting data)
- adapt the `-p` option to listen to the port you need
- you may pass env variables with the `-e` option
- `APP_KEY` is required at container startup (generate with `php artisan key:generate --show`)
- the Docker image runs the Laravel scheduler internally via cron
- you can use a reverse proxy for SSL termination (example: nginx)

Simple config for Nginx:

```
server {
	server_name filesharing.box.webinno.fr;
	charset utf-8;

	location / {
		proxy_set_header Host $host;
		proxy_set_header X-Real-IP $remote_addr;
		proxy_set_header   X-Forwarded-Proto $scheme;
		proxy_set_header   X-Scheme $scheme;
		proxy_pass http://localhost:8080;
	}

	listen [::]:443 ssl http2;
	listen 443 ssl http2;
	ssl_certificate [...]
	ssl_certificate_key [...]
}
```

You can also use in docker compose with the following template:

```yaml
services:
  app:
    image: axeloz/filesharing:latest
    environment:
      APP_KEY: "<your_generated_key>"
      UPLOAD_MAX_FILESIZE: "1G"
      UPLOAD_MAX_FILES: "100"
      UPLOAD_LIMIT_IPS: "127.0.0.1"
      UPLOAD_PREVENT_DUPLICATES: true
      HASH_MAX_FILESIZE: "1G"
      LIMIT_DOWNLOAD_RATE: "1M"
    volumes:
      - files_v:/app/storage/content
    ports:
      - 8080:80

volumes:
  files_v:
    driver: local
```


### Standalone

- configure your domain name. For example: files.yourdomain.com
- clone the repo or download the sources into the webroot folder
- configure your webserver to point your domain name to the `./public` folder
- run `composer install`
- run `npm ci`
- run `npm run build`
- make sure that the PHP process has write permission on the `./storage` folder
- run `cp .env.example .env` and edit `.env` to fit your needs
- generate the Laravel KEY: `php artisan key:generate`
- (optional) you may create your first user `php artisan fs:user:create`
- start the Laravel scheduler (it will delete expired bundles of the storage). For example `* * * * * /usr/bin/php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1`
- (optional) to purge bundles manually, run `php artisan fs:bundle:purge`


Use your browser to navigate to your domain name (example: files.yourdomain.com) and **that's it**.

## Configuration

In order to configure your application, copy the .env.example file into .env. Then edit the .env file.

| Configuration | Description |
| ------------- | ----------- |
| `APP_NAME`    | the title of the application |
| `APP_ENV`     | change this to `production` when in production (`local` otherwise) |
| `APP_DEBUG` | change this to `false` when in production (`true` otherwise) |
| `APP_TIMEZONE` | change this to your current timezone |
| `APP_LOCALE` | change this to "fr", "en", "de" or "kr" |
| `UPLOAD_PREVENT_DUPLICATES` | Should the app block duplicate files (true / false) |
| `HASH_MAX_FILESIZE`| max size for hashing file to check for duplicate files. If files are bigger than limit, they will not be hashed. Find the best value for better cpu / memory consumption |
| `UPLOAD_MAX_FILES` | (*optional*) maximal number of files per bundle |
| `UPLOAD_MAX_FILESIZE` | (*optional*) change this to the value you want (K, M, G, T, ...). Attention : you must configure your PHP settings too (`post_max_size`, `upload_max_filesize` and `memory_limit`). When missing, using PHP lowest configuration |
| `UPLOAD_LIMIT_IPS` | (*optional*) Comma-separated IPs allowed to upload without login. Ignored when `MICROSOFT_SSO_ENABLED=true`. Different formats supported: full IP (192.168.10.2), wildcard (192.168.10.*), CIDR (192.168.10/24), or range (192.168.10.0-192.168.10.10). Leave empty when SSO is enforced. |
| `LIMIT_DOWNLOAD_RATE` | (*optional*) if set, limit the download rate. For instance, you may set `LIMIT_DOWNLOAD_RATE=100K` to limit download rate to 100Ko/s |
| `MICROSOFT_SSO_ENABLED` | Set to `true` for Azure AD sign-in (production). See [Microsoft SSO setup](#microsoft-sso-setup). |
| `AZURE_CLIENT_ID` | Azure app registration client ID |
| `AZURE_CLIENT_SECRET` | Azure app registration client secret |
| `AZURE_TENANT_ID` | Azure directory (tenant) ID |
| `AZURE_REDIRECT_URI` | OAuth callback URL (default: `{APP_URL}/auth/microsoft/callback`) |
| `AZURE_ALLOWED_DOMAINS` | Comma-separated email domains allowed to sign in |
| `BRANDING_SHOW_CREDIT` | Show the "Made with love" project credit in the footer (`true` / `false`). Can be overridden in the admin Branding settings. |

## Authentication

Upload access can be controlled in three ways:

| Mode | Use case |
| ---- | -------- |
| **Microsoft SSO** | Production / organizational deployment (recommended) |
| **Login / password** | Local development when `MICROSOFT_SSO_ENABLED=false` |
| **IP whitelist** | Legacy standalone installs when SSO is disabled |

> **Warning:** If `UPLOAD_LIMIT_IPS` is empty, SSO is disabled, and no users exist, upload is publicly accessible.

When Microsoft SSO is enabled (`MICROSOFT_SSO_ENABLED=true`):

- Users sign in with their organization Microsoft account only — there is no password login in the web UI.
- `UPLOAD_LIMIT_IPS` is ignored; unauthenticated users cannot upload.
- New users are created automatically on first sign-in with the `user` role. An admin must assign roles and groups via the [admin panel](#admin-panel) at `/admin` (or CLI for bootstrap).

### Microsoft SSO setup

Single-tenant Azure AD (Entra ID) sign-in. There is no break-glass local admin account in production.

#### 1. Register an app in Azure

1. Open [Microsoft Entra admin center](https://entra.microsoft.com/) → **App registrations** → **New registration**.
2. Name the app (e.g. "Secure File Send").
3. Supported account types: **Accounts in this organizational directory only (Single tenant)**.
4. Redirect URI — platform **Web**:
   ```
   https://files.yourcompany.com/auth/microsoft/callback
   ```
   Must match `APP_URL` exactly (including `https` and no trailing slash on the base URL).
5. Create the registration and note:
   - **Application (client) ID** → `AZURE_CLIENT_ID`
   - **Directory (tenant) ID** → `AZURE_TENANT_ID`
6. Under **Certificates & secrets**, create a **Client secret** → `AZURE_CLIENT_SECRET`.
7. Under **API permissions**, ensure these delegated permissions are granted (admin consent if required):
   - `openid`
   - `profile`
   - `email`
   - `Microsoft Graph` → `User.Read`

#### 2. Configure environment variables

Add to `.env` (see `.env.example`):

```env
MICROSOFT_SSO_ENABLED=true
AZURE_CLIENT_ID=your-application-client-id
AZURE_CLIENT_SECRET=your-client-secret
AZURE_TENANT_ID=your-directory-tenant-id
AZURE_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
AZURE_ALLOWED_DOMAINS=yourcompany.com
```

| Variable | Description |
| -------- | ----------- |
| `MICROSOFT_SSO_ENABLED` | Set to `true` to enable SSO and disable password login |
| `AZURE_CLIENT_ID` | Application (client) ID from the app registration |
| `AZURE_CLIENT_SECRET` | Client secret value (not the secret ID) |
| `AZURE_TENANT_ID` | Directory (tenant) ID — only users from this tenant can sign in |
| `AZURE_REDIRECT_URI` | OAuth callback URL; defaults to `{APP_URL}/auth/microsoft/callback` |
| `AZURE_ALLOWED_DOMAINS` | Comma-separated list of allowed email domains (e.g. `yourcompany.com,subsidiary.com`) |

Also ensure:

- `APP_URL` matches the URL users visit and the Azure redirect URI base (e.g. `https://files.yourcompany.com`).
- `UPLOAD_LIMIT_IPS` is empty or unset when SSO is enforced.

#### 3. Run migrations

SSO requires the SQL user schema (`email`, `azure_oid`, roles, etc.):

```bash
php artisan migrate
```

#### 4. Assign roles after first sign-in

The first time a user signs in, an account is created with the default `user` role. Assign additional roles with Artisan:

```bash
# List users (shows all assigned roles)
php artisan fs:user:list

# Create a local user (bootstrap / dev only — not for production login)
php artisan fs:user:create

# Role is set at create time; edit via admin panel (/admin) or CLI
php artisan fs:user:create adminuser --role=admin

# Assign a role to an existing user (username or email; roles are additive)
php artisan fs:user:promote you@yourcompany.com --role=admin
php artisan fs:user:promote you@yourcompany.com --role=reviewer

# Revoke an elevated role (user role cannot be revoked)
php artisan fs:user:revoke you@yourcompany.com --role=admin
```

Users can hold multiple roles (e.g. `user` + `admin` + `reviewer`). Every account always retains the `user` role. Manage roles in the admin panel at `/admin` or via CLI:

```bash
php artisan fs:user:promote you@yourcompany.com --role=admin
php artisan fs:user:revoke you@yourcompany.com --role=reviewer
```

#### 5. Verify

1. Visit `/login` — you should see **Sign in with Microsoft** (no password form).
2. Sign in with an account from your tenant and allowed domain.
3. Confirm you land on the homepage and can create uploads.
4. Test rejection: an account from another tenant or disallowed email domain should return to `/login` with an error message.

#### Local development without SSO

Set `MICROSOFT_SSO_ENABLED=false` in `.env`, then use IP whitelist and/or local users:

```bash
php artisan fs:user:create
```

Password login and IP bypass work only when SSO is disabled.

### Admin panel

Admins can manage the organization from `/admin` (Filament). Sign in with an account that has the `admin` role, then open the panel from the footer link or directly at `/admin`.

| Section | Purpose |
| -------- | -------- |
| **Users** | Search users; edit role, group membership, and per-user approval override |
| **Groups** | Create/edit groups; toggle approval requirement and static-link policy; assign members |
| **Bundles** | View all shares with filters; revoke, extend expiry, or permanently delete |
| **Reviewers** | Read-only list of users in the reviewer pool |
| **Branding** | App name, logo, colors, footer text, and legal URLs (stored in `settings`; applied without redeploy) |
| **Sharing** | Default share mode (invitation vs static link) for new bundles |

Ensure `php artisan storage:link` has been run so uploaded logos are served from `public/storage`.

### Production database (MySQL)

SQLite works for local development and small single-node deployments. For production, use MySQL (or MariaDB):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=filesharing
DB_USERNAME=filesharing
DB_PASSWORD=your-secure-password
```

Recommended MySQL settings:

- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`
- Run migrations: `php artisan migrate --force`
- Schedule regular backups of the database and `storage/` uploads
- Use a dedicated database user with minimal privileges

SQLite remains valid for dev, CI, and small installs where a separate database server is not required.

### Queue workers

Mail (invitations, OTP, approval notifications) is queued for async delivery. Set a queue driver in production:

```env
QUEUE_CONNECTION=database   # or redis
```

Run migrations (includes `jobs` table), then start a worker:

```bash
php artisan queue:work --sleep=3 --tries=3
```

For Docker, the image runs `queue:work --stop-when-empty` via cron each minute. For production VMs, use Supervisor or systemd to keep a worker process running.

### Rate limiting & security

Configurable via `.env`:

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `OAUTH_RATE_LIMIT_PER_MINUTE` | 10 | Microsoft OAuth callback |
| `DOWNLOAD_RATE_LIMIT_PER_MINUTE` | 30 | Bundle preview/download |
| `OTP_RATE_LIMIT_PER_HOUR` | 5 | OTP request per recipient email |
| `SESSION_IDLE_TIMEOUT` | 60 | Minutes of inactivity before logout |

Security headers (CSP, `X-Frame-Options`, etc.) are applied globally. Session cookies use `Secure` in production. See [docs/SMOKE_TEST.md](docs/SMOKE_TEST.md) for pre-release QA checklist.

## Known issues

If you are using Nginx, you might be required to do additional setup in order to increase the upload max size. Check the Nginx's documentation for `client_max_body_size`.

## Development

To modify the sources, use Vite for frontend asset compilation:
- configure your domain name. For example: files.yourdomain.com
- clone the repo or download the sources into the webroot folder
- configure your webserver to point your domain name to the public/ folder
- run `composer install`
- run `npm install`
- run `npm run dev` to recompile assets when changed

### Testing

Run the test suite and linter:

```
composer test
composer lint
```

## Roadmap / Ideas / Improvements

There are many ideas to come. You are welcome to **participate**.
- more testing on heavy files
- background process for creating Zips asynchronously after completion of the bundle
- invitation to external users to upload file into existing bundle 
- customizable / white labeling (logo, name, terms of service, footer ...)

## Licence

GPLv3

| Permissions     | Conditions                    | Limitations |
| --------------- | ----------------------------- | ----------- |
| Commercial use  | Disclose source               | Liability   |
| Distribution    | License and copyright notice  | Warranty    |
| Modification    | Same license                  |             |
| Patent use      |  State changes                |             |
| Private use     |                               |             |

https://choosealicense.com/licenses/gpl-3.0/

## Welcome on board

If you are willing to **participate** or if you just want to talk with me : axel@mabox.eu


Powered by
<p><img src="https://laravel.com/assets/img/components/logo-laravel.svg"></p>

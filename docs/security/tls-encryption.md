# TLS encryption deployment and SSL report

## Inspected baseline

HIMS is a Laravel 12 application on PHP 8.2+ with Blade/Vite pages, session-authenticated staff/admin/super-admin panels, and Sanctum-protected `/api/v1` routes. `bootstrap/app.php` already trusts reverse-proxy forwarding headers, including `X-Forwarded-Proto`. URL generation uses Laravel helpers and `APP_URL`; Vite assets are generated through Laravel's Vite integration. Session cookies come from `config/session.php`, and the trusted-device cookie follows the same secure-cookie setting. Both now default to `Secure` in the production environment while remaining compatible with local HTTP development.

The repository had no web-server, reverse-proxy, container, CDN, load-balancer, or hosting configuration. Consequently, TLS did not terminate anywhere defined by the project, port 80 was not redirected, TLS 1.3 was not declared, and production secure-cookie behavior was not documented. Local development currently uses Laravel's HTTP server and must remain HTTP-capable.

The supplied deployment template makes Nginx the TLS termination point. Laravel/PHP-FPM remains the application origin and must not be exposed directly to the public network.

## Production configuration

Prerequisites:

- Nginx built with OpenSSL 1.1.1 or newer (`nginx -V`) so TLS 1.3 is available.
- A publicly trusted certificate whose subject/SAN contains the deployed hostname, including its intermediate chain.
- A private key stored outside this repository with access restricted to the Nginx service account.
- PHP-FPM listening on a private TCP address or Unix socket.

Set these values in the deployment environment; do not commit them:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hims.example.org
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=hims.example.org
```

Use the real public hostname in `APP_URL` and `SANCTUM_STATEFUL_DOMAINS`. If the application is served from the same host as shown, `SESSION_DOMAIN=null` remains appropriate. Do not put a scheme in `SANCTUM_STATEFUL_DOMAINS`.

Render the Nginx configuration without substituting Nginx's own variables:

```bash
export HIMS_HOST='hims.example.org'
export HIMS_PUBLIC_ROOT='/srv/hims/current/public'
export HIMS_TLS_CERTIFICATE='/etc/letsencrypt/live/hims.example.org/fullchain.pem'
export HIMS_TLS_CERTIFICATE_KEY='/etc/letsencrypt/live/hims.example.org/privkey.pem'
export HIMS_PHP_FPM='unix:/run/php/php8.2-fpm.sock'

envsubst '$HIMS_HOST $HIMS_PUBLIC_ROOT $HIMS_TLS_CERTIFICATE $HIMS_TLS_CERTIFICATE_KEY $HIMS_PHP_FPM' \
    < deploy/nginx/hims.conf.template \
    | sudo tee /etc/nginx/conf.d/hims.conf >/dev/null

sudo nginx -t
sudo systemctl reload nginx
php artisan optimize:clear
php artisan config:cache
```

The template accepts TLS 1.2 for compatible clients and TLS 1.3 for current clients. The verification below requires and proves an actual TLS 1.3 negotiation. Nginx redirects every HTTP request to the same HTTPS host and URI, supplies the forwarded HTTPS scheme to Laravel, and marks HTTPS responses with HSTS. Do not enable HSTS before the valid certificate and HTTPS listener are working.

If a CDN, managed load balancer, or hosting platform sits in front of Nginx, TLS terminates there instead. Configure that service to permit TLS 1.3, redirect HTTP to HTTPS, use a valid public certificate, and forward `X-Forwarded-Proto: https`. In that topology, do not install the certificate private key in this repository or duplicate public TLS termination at the Laravel layer. Restrict the origin so clients cannot bypass the managed edge.

## Mixed-content and application checks

The repository scan found no browser-loaded `http://` scripts, stylesheets, images, form actions, or API endpoints. Remaining `http://` literals are local defaults, XML namespace identifiers, or a cXML document type and are not browser subresources. Production `APP_URL=https://...` ensures generated storage, password-reset, notification, signed, and API URLs use HTTPS.

After deployment, use a clean browser profile and exercise all three login panels, MFA where enabled, logout, an authenticated `/api/v1` request, and representative asset/file routes. In browser developer tools:

1. Confirm the main document, redirects, XHR/fetch calls, scripts, styles, fonts, images, and storage files use HTTPS.
2. Confirm the Console has no mixed-content or certificate errors.
3. Confirm the session, remember-me, inactivity, and trusted-device cookies have `Secure` and `HttpOnly`; confirm the session cookie has `SameSite=Lax`.
4. Confirm direct HTTP navigation returns a `301` to the equivalent HTTPS URL.

## Generate the required SSL report

Run from a machine outside the deployment network with Bash, curl, and OpenSSL installed:

```bash
chmod +x deploy/verify-tls.sh
./deploy/verify-tls.sh hims.example.org > ssl-report.md
```

Replace the example hostname with the deployed hostname. A passing report has all of the following:

- the first request succeeds without `--insecure`, proving hostname, expiry, and trust-chain validation;
- the OpenSSL section reports TLS 1.3 and `Verify return code: 0 (ok)`;
- the HTTP section reports `301` and a `Location: https://...` header;
- the certificate summary shows the expected subject/SAN issuer and valid dates.

Attach `ssl-report.md` as the checklist's SSL Report. For a public Internet deployment, also generate and attach a Qualys SSL Labs report for the same hostname. Never attach a private key, certificate-key bundle, environment file, session cookie, or authentication token.

The script cannot prove authenticated workflows or mixed-content absence by itself; retain screenshots or test notes from the browser checks above beside the SSL report. This repository cannot perform those live checks until the hostname, certificate, and deployment endpoint exist.

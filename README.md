# AppCenter — Android App/APK Download Script

## Requirements
- PHP 7.4+ (tested on PHP 8.3)
- MySQL / MariaDB
- `mod_rewrite` enabled (Apache) — used for SEO-friendly URLs
- PHP extensions: `pdo_mysql`, `fileinfo`, `gd`

## Installation
1. Upload all files to your web root.
2. Visit your domain in a browser — you'll be redirected into the `/install/` wizard automatically.
3. Follow the steps: database connection → site settings → create your admin account.
4. **Delete the `/install/` folder once setup is complete.** The wizard already
   blocks itself from running again as long as `includes/config.php` exists,
   but removing the folder entirely is the safest practice and is required
   by most hosting security scans.
5. Log in at `/admin/login.php` with the admin username/password you created
   during step 3.

There is no default/fallback admin account — you set your own credentials
during installation, and that's the only way an admin account is created.

## Uploading content
- App icons: JPG, PNG, GIF, or WebP.
- APK files: `.apk` only (verified by file signature, not by the browser's
  reported file type).
- Logos: JPG, PNG, GIF, WebP, or SVG.
- Favicon: JPG, PNG, GIF, WebP, or ICO.

All uploaded files are re-validated on the server (real file content, not
just the filename/extension) and saved under a randomly generated name —
the original filename is never reused.

## Security notes
- Every admin form is protected against CSRF (cross-site request forgery)
  via a per-session token.
- `/uploads/*` subfolders have `.htaccess` rules blocking script execution,
  so even if an unexpected file type ever ended up there, it could not run
  as code.
- Uploaded SVG logos are stripped of `<script>` tags and inline event
  handlers before being saved.
- Keep PHP and your server software up to date, and use a strong,
  unique admin password.

## CSS structure
All CSS previously embedded in `<style>` blocks inside PHP files has been
moved to external stylesheets under `/assets/css/`:

- `assets/css/public/` — one file per public-facing page (`index.css`,
  `app.css`, `category.css`, etc.), plus `header.css` / `footer.css` for
  the shared layout partials.
- `assets/css/admin/` — one file per admin page.
- `assets/css/admin-base.css` — rules that were identical, word-for-word,
  across 6+ admin pages (sidebar, menu, buttons, alerts) — loaded once and
  shared instead of being duplicated in every admin file.
- `assets/css/install/` — installer wizard styles (uses a relative path
  since `SITE_URL` isn't defined yet during install).

Public pages didn't have any rules that were identical across enough
files to justify a shared base — each page intentionally has its own
color scheme — so nothing was merged there beyond the per-page split.

Two small `<style>` blocks remain inline in `includes/functions.php`:
these belong to `displayPopupAd()` / the sidebar ad function, which
return an HTML+CSS snippet at runtime and can be injected into any page
depending on which ad slots are active. They weren't extracted since
doing so safely would require a conditional CSS-enqueue system; they're
tiny and don't carry the duplication problem the page templates had.

No visual output was changed — this was purely moving CSS to external,
cacheable files and de-duplicating exact repeats.

## Support
For questions about setup or configuration, please reach out through your
purchase page's comment/support section.

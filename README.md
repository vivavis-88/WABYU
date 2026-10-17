# WABYU — The Forever Album

A private, password-protected dedication album. **PHP 8 + JSON files + photos on disk.** No database, no Node.js.

- Photos are saved as real files in `uploads/memories/` and are only served to a logged-in session.
- Titles, dedications and dates are saved in `data/memories.json`.
- Login and deletion passwords are stored as **hashes** in `config/secrets.php` (never committed to Git).

---

## 1. Set it up on XAMPP (Windows, beginner steps)

1. **Install XAMPP** (PHP 8.1 or newer) from apachefriends.org.
2. Open the **XAMPP Control Panel** and click **Start** next to **Apache**. You do **not** need MySQL. Leave it stopped.
3. Copy this whole folder to **`C:\xampp\htdocs\wabyu\`** (so `C:\xampp\htdocs\wabyu\index.php` exists).
4. **Create your passwords:** double-click **`setup.bat`** in that folder.
   - Type a login password (8+ characters). Note: Windows shows what you type.
   - For the deletion password, just press **Enter** to use the required `YESCONFIRM` (case-sensitive).
   - This creates `config/secrets.php` (hashes only) and also creates the `data/`, `uploads/memories/` and `database-backups/` folders and an empty `data/memories.json`. Running it again never erases photos.
   - If XAMPP is not in `C:\xampp`, open a terminal in the folder and run `path\to\php.exe setup.php`.
5. **Folder permissions:** on a normal Windows XAMPP install PHP can already write to these folders. If saving fails, right-click the project folder, Properties, Security, and allow your user **Modify**.
6. Open **http://localhost/wabyu/** and log in.
7. **Test it** with the checklist in section 7.

### If large photos fail to upload
XAMPP's default limits (`upload_max_filesize`, `post_max_size`) are usually 40M, which is fine. WABYU itself accepts photos up to 15 MB (change `WABYU_MAX_IMAGE_BYTES` in `config/app.php`). Phone photos are normally 2-8 MB.

### If "Download backup" says zip is disabled
Open `C:\xampp\php\php.ini`, make sure the line `extension=zip` has no `;` in front, then restart Apache.

### If folder protection does not seem to work
`.htaccess` files keep `config/`, `data/`, `includes/`, `uploads/` and `database-backups/` private. XAMPP allows this by default (`AllowOverride All`). Check by visiting `http://localhost/wabyu/data/memories.json`: you must see **403 Forbidden**.

---

## 2. What is in the project

```
wabyu/
├── index.php  login.php  logout.php  gallery.php   Pages and session endpoints
├── setup.php (+ setup.bat)   One-time password setup (command line only)
├── backup.php                Command-line backup
├── config/      app.php (settings), secrets.example.php   [secrets.php is created by setup, ignored by Git]
├── includes/    helpers · auth · csrf · storage · validation · api
├── api/         memories-list · memory-create · memory-update · memory-delete · image · export
├── data/        memories.json (created at runtime) · memories.example.json
├── uploads/memories/    your photos (private)
├── database-backups/    output of backup.php
└── assets/css (7 files) · assets/js (ES modules)
```

Design note: the photo upload is part of `memory-create.php` / `memory-update.php` (one request saves the file *and* the record, and undoes the file if the JSON write fails). There is therefore no separate `image-upload.php`; `image.php` serves photos to logged-in sessions only.

Gallery heading text can be changed in `config/app.php` (`WABYU_HERO_TITLE`, `WABYU_HERO_SUB`).

---

## 3. How saving and deleting stay safe

- **Save:** the photo is validated on the server (real file type, real image, size), stored under a random name, then the JSON record is written (lock + temp file + atomic rename, with `memories.json.bak` of the previous version). If the JSON write fails, the new photo is removed and existing records are untouched. Success is shown only after both steps finished.
- **Replace a photo:** the old photo is deleted only **after** the new record is saved.
- **Corrupt JSON:** if `memories.json` is ever unreadable, WABYU reports an error and leaves the file alone. Never auto-resets. Restore from `data/memories.json.bak` or a backup.
- **Delete:** every delete (card, viewer) opens the password popup. PHP checks the session, CSRF token, the password (`password_verify`, case-sensitive) and the memory ID, then removes the JSON record first and the image second. If only the image file cannot be removed, you are told so and WABYU retries automatically; it never claims a clean delete when it was not one.
- **Rate limits:** 6 wrong login attempts and 6 wrong deletion passwords per 15 minutes per IP.

## 4. Backups and recovery

**Make a backup**
- In the gallery: account menu → **Download backup** (zip of every photo + `memories.json`), or
- Command line: `C:\xampp\php\php.exe backup.php` creates `database-backups\<timestamp>\` (safe while Apache runs; keeps the newest 14).
- Schedule it with **Windows Task Scheduler** (daily) if you like, and **copy the backups somewhere else** (external drive, cloud storage).

**Restore** (this replaces the current album with the backup, so make a fresh backup first if unsure)
1. Stop Apache.
2. Copy the backup's `memories.json` to `data\memories.json`.
3. Copy the backup's photos into `uploads\memories\` (the JSON and photos must come from the same backup).
4. Start Apache and check the gallery.

## 5. GitHub, XAMPP, GitHub Pages, and real hosting

- **GitHub** stores your *code*. `.gitignore` already excludes `config/secrets.php`, `data/memories.json`, your photos, backups and logs. Do not remove those lines.
- Photos you upload through the site are saved on **this computer**, not on GitHub, and do not sync to other devices.
- **GitHub Pages cannot run PHP** and cannot save files, so WABYU will not work there.
- To use it from other devices, deploy to a **PHP host with writable persistent storage and HTTPS**. Upload the code, run `php setup.php` on the server (or create `config/secrets.php` there), make sure `data/`, `uploads/memories/` and `database-backups/` are writable, keep Apache `.htaccess` enabled (on nginx, add equivalent deny rules for those folders), and keep backups.

## 6. Honest guarantees

Photos and dedications survive page refresh, closing the browser, logging out, clearing the cache, restarting Apache/XAMPP, restarting the computer, and editing CSS/JS. No local setup can protect against a failed disk, accidental deletion of the folder, or losing the computer. Only **off-computer backups** do that. Deleting removes the record and photo from the album; copies in your own backups remain until you delete them.

Fonts (Sora, Montserrat) load from Google Fonts; offline, the browser falls back to system fonts.

## 7. Testing checklist

- [ ] `http://localhost/wabyu/` shows WABYU and one password field (no username/email).
- [ ] A wrong password is rejected; the right one plays the intro, then "Enjoy the Album", then Enter/Space/click opens the gallery.
- [ ] Add a memory with a JPEG/PNG/WebP photo; it appears; refresh: it is still there; `uploads\memories\` contains the file and `data\memories.json` contains the record.
- [ ] Choosing a `.txt`/`.pdf`/oversized file shows an error and saves nothing.
- [ ] Edit title/dedication/date/category; replace the photo; the card updates, no duplicate appears.
- [ ] Press Escape in the editor with changes: you are asked to keep or discard.
- [ ] Favorites, Recently added, search, and category filter work; "No memories match" appears when nothing matches.
- [ ] Open a photo: arrow keys / swipe move between photos; close returns you to the gallery.
- [ ] Delete from a card **and** from the viewer: the popup always appears.
- [ ] `yesconfirm` (wrong case) and a wrong word are rejected and nothing is deleted. Cancel deletes nothing. `YESCONFIRM` deletes the record and the photo file.
- [ ] Log out, then open `http://localhost/wabyu/api/memories-list.php`: it says you must log in. Log in again: everything is still there.
- [ ] Stop and start Apache (or restart the PC): everything is still there.
- [ ] `http://localhost/wabyu/data/memories.json` and `.../config/secrets.php` show 403.
- [ ] On a phone (same network, `http://<your-PC-IP>/wabyu/`): cards swipe sideways, page scrolls vertically, no sideways page scroll, dialogs fit.
- [ ] "Download backup" downloads a zip; `backup.php` creates a folder.

## 8. Security summary

PHP sessions (HttpOnly, SameSite=Strict, ID regenerated at login, destroyed at logout), password hashes with `password_hash`, CSRF token on every state-changing request, authentication on every API endpoint, rate limiting, server-side image validation (finfo + getimagesize, random filenames, fixed extensions), private photo folder served only through PHP, path allow-listing before any file operation, Content-Security-Policy, comments rendered as text only, detailed errors written to `data/error.log` (private) instead of shown.

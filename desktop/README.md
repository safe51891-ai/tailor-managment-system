# Tailor Shop desktop

The Windows x64 installer bundles Electron, the Laravel application and Composer dependencies, PHP 7.4.33, SQLite extensions, and Microsoft's Visual C++ runtime. The client installs one `.exe` and opens **Tailor Shop** from the desktop shortcut. No Docker, PHP installation, MySQL service or hosted website is required.

After offline activation, a fresh installation creates username `admin` with password `admin@12`. The release contains no development `.env`, customer database or employee records. Existing installations keep their accounts and passwords. Its PHP process listens only on `127.0.0.1`, starts automatically, and stops when the desktop app closes.

## Run on this development computer

```sh
cd desktop
npm ci
npm start
```

The development command uses the existing project and database. It detects PHP 7.4 on macOS. Set `TAILOR_PHP_BIN` when PHP is elsewhere. The packaged Windows app instead uses bundled PHP and its own SQLite database.

## Build the Windows installer

Install Node.js and the project's locked Composer dependencies. Download these official prerequisites to `.local/desktop-runtime` in the project root:

- `php-win.zip`: [PHP 7.4.33 NTS x64](https://downloads.php.net/~windows/releases/archives/php-7.4.33-nts-Win32-vc15-x64.zip)
- `vc_redist.x64.exe`: [Microsoft Visual C++ x64 runtime](https://aka.ms/vc14/vc_redist.x64.exe)

Then run:

```sh
cd desktop
npm ci
npm run build:win
```

The installer is created at `.local/desktop-dist/Tailor-Shop-Setup-1.0.6.exe`. It includes the runtime prerequisite and requests administrator permission during installation. The application then runs with the customer's normal account. This build is unsigned. A client release should use the developer's Windows signing certificate.

## Local data and backups

On Windows, data lives in `%APPDATA%\Tailor Shop\data`, outside Program Files:

- `shop.sqlite`: customers, measurements, orders, employees, income and expenses.
- `app-key`: persistent encryption key. Keep it with the database.
- `storage`: logs, sessions and application storage.
- `backups`: consistent daily SQLite snapshots, made at startup before migrations. Snapshot filenames also distinguish application releases.

Uploaded avatars and measurement images are in `%APPDATA%\Tailor Shop\application\public\storage`. Updates preserve these files and the database. Uninstalling does not delete app data. Different Windows accounts have separate databases.

For a full backup, close Tailor Shop and copy the entire `%APPDATA%\Tailor Shop` folder to an external drive. To restore, close the app and restore that folder to the same location. Keep multiple dated copies. Automatic database snapshots stay on the same disk and do not protect against disk failure.

## Verification before client delivery

The desktop window and SQLite initialization are tested on macOS. The Windows installer is built here, but installation and the bundled Windows PHP runtime still need validation on a clean Windows 10/11 x64 computer:

1. Install offline and sign in with the initial admin account.
2. Save a customer, measurements, an employee, an order, income and an expense.
3. Reopen the app; verify records and both Overview charts.
4. Print an order using a local printer.
5. Install an updated release and verify records and uploaded images survive.
6. Restore a full backup into a separate test Windows account and check records.

PHP 7.4 and this Laravel version are legacy dependencies. Upgrade them to supported versions before treating this as a maintained client release. WhatsApp automation and cloud backup are separate integrations, not included in this desktop conversion.

## Backups to another drive

Use the **Backups** button and **Automatic backup** checkbox at the top right. Checking the box asks for a folder if none is configured, saves the setting, and enables daily backups; unchecking pauses daily and closing backups while **Back up now** remains available. The setting is remembered after restarting. In the self-contained desktop app, choose **Backups → Choose folder** and select a folder on an external SSD, hard drive, USB drive, or another physical disk. The app saves a complete dated backup on the first launch of each day, checks for a new day every 30 minutes while open, and saves again when closing. **Back up now** makes an extra copy. The backup contains a consistent, integrity-checked SQLite database, the encryption key, uploaded logos/images, and a manifest of file checksums. Finished backup folders start with `Tailor-Shop-Backup-`; partial backups are never offered as completed copies. No older backups are automatically deleted.

Keep the drive connected. If it is unavailable, the app records the failure and keeps the last successful backup date; the next scheduled check retries. Startup and closing failures show a message. Backups cannot run when the app is closed or the computer is off. The backup menu shows the chosen folder, last success and any failure. Backups on the same physical disk do not protect against that disk failing.

To recover after reinstalling Windows or the app, install this version or a newer version and choose **Restore an existing shop backup** on the welcome screen. Select the entire dated backup folder, confirm, and sign in with the account saved in that backup. For an existing installation use **Backups → Restore backup**. Restore checks file hashes, rejects incomplete/damaged or newer-version backups, stops the local backend, and keeps a safety copy of current records before replacement. A failed migration rolls back the replacement. App uninstall preserves local data, and backups in the selected external folder stay outside the app installation.

Backup operations apply to the self-contained SQLite desktop build. This development checkout uses its separate MariaDB database, shows an explanatory Backups button, and disables the automatic checkbox. Test drive disconnection and restore on Windows before client delivery.

## Development admin login

The development seed account is `admin` with password `admin@12`. To update the existing development account without reseeding or changing shop records, run from the project root:

```sh
/opt/homebrew/opt/php@7.4/bin/php reset-local-admin.php
```

Fresh desktop installs create `admin` / `admin@12` automatically. Passwords must contain at least eight characters. Updating an existing desktop installation preserves its existing account and password.


## License activation

The packaged app opens an activation screen before starting PHP or touching the
shop database. Send the displayed TS- computer code to the provider, save the
returned .lic file locally, and use Choose activation file. Copy code and Save
code as a file both work without internet. Updates retain the activation on the
same PC. A new PC, changed MachineGuid or Windows reinstall needs a license for
its new code. Restoring shop records does not transfer a license. Settings shows
the activated customer and computer code. Closing activation exits the app.

Owner-only issuance: use `.local/licensing/Create-License.command` or the copy
in `~/Tailor-Shop-Owner-Only`. Keep the private key backed up and private. Never
include that folder in the customer ZIP. Existing releases do not gain licensing
retroactively. Development mode (`npm start`) uses the development database;
packaged and self-contained mode enforce licensing.

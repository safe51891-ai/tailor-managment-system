# Installation

## Desktop application

The desktop launcher and self-contained Windows installer are in [desktop](desktop/README.md). The packaged app includes PHP and a local SQLite database, first-run account setup, and daily database snapshots. Client data stays outside the installation directory.

Overview now includes local income/expense and employee workload charts for the weekly/monthly report. The cutting queue has been removed from Overview; order statuses and filtering remain available in Orders.

### Customer receipts and clothes tracking

Every order has a persistent, database-unique tracking code such as **TS-000123**. Existing orders, including archived orders, receive codes from their original IDs. Codes stay unchanged when editing an order. Orders search accepts the full code, the older short reference (`TS-123`), customer name or mobile number.

Open an order and select **Print customer receipt** for the customer copy, or **Print clothes tag** to attach the matching code to the clothes bundle. The receipt shows customer name/mobile, garment and quantity, booking/collection dates, total price, cumulative amount paid and current remaining balance in PKR. Internal measurements, stitching notes and employee earnings are excluded. Reprints reflect the current order record, not an individual payment transaction.

The tag shows the same tracking code, customer, garment, total order quantity, collection date and assigned karigar. Print additional copies if an order's bundle is split. It contains no customer phone number or prices.

**Print karigar measurement sheet** creates the separate cutting/stitching copy: customer and karigar names, matching tracking code, garment, quantity, received/delivery dates, emergency priority, garment-specific saved measurements, units and Full/Half convention, style choices and instructions. Shalwar kameez prints Kameez and Shalwar once each; shirts, trousers and waistcoats print only their relevant measurements. It uses the order's frozen measurement snapshot, including on reprints after customer measurements change. Prices, balances and employee pay are excluded. An unassigned karigar or missing legacy measurements are clearly marked. The work sheet uses the printable page width with compact two-column measurement sections.

Both print previews work locally, require sign-in and let the user choose their printer and paper size. Receipt content is 140 mm wide; tag content is 110 mm wide, with a cut-out border. This update changes the development project only; rebuild the Windows installer when the remaining updates are complete.

## Workspace design

The measurement page shows the latest saved profile once, with one section per garment. Older profiles are available through **View earlier measurement versions** rather than repeating them under the current sizes. Submitting identical sizes and measurement details reuses the saved record; actual changes retain a previous version so existing order snapshots remain intact.

The interface uses navy navigation, an ivory canvas and teal primary actions.
The overview links to due-today, overdue, ready and active order lists. Keyboard
navigation includes a skip link, visible focus states and Escape to close the
mobile menu. Statuses have text labels as well as colours; feedback remains
visible until dismissed. System fonts work without an internet connection.
The developer toolbar is hidden by default. Set `DEBUGBAR_ENABLED=true` in a
local development `.env` to enable it.

## Garment measurement workflow

1. Open **Customers**, search by name or phone, or choose **Add customer**.
   Name and phone are required; other contact details are optional.
2. Open **Measurements** and enter manually taken kameez, shalwar, shirt, pant/trouser, sleeveless waistcoat,
   or sleeved waistcoat sizes. Open only the sections needed for the customer.
   Choose inches or centimetres and the girth convention. Saving creates a new
   measurement version; older versions remain in the customer's history.
3. Alternatively, enter measurements directly in **Create order**. Load or add
   the customer, select a garment, edit its prefilled sizes and stitching choices,
   then set priority, received/completion dates, quantity, price and payment.
   Add further garments and save the complete booking once.
4. Open the order to print its card or edit its stitching status. Orders keep
   their own measurement copy. Editing a customer's measurements does not change
   existing orders; replacing an order's measurements requires selecting a new
   version in the order editor.

Existing orders remain available. Their garment and measurement details can be
added through **Edit**. The new workflow migration is applied with
`php artisan migrate`. A backup from before this migration is saved locally in
`.local/database-before-measurements.sql`.

Run the workflow tests with
`/opt/homebrew/opt/php@7.4/bin/php vendor/bin/phpunit` on this Mac. Tests use an
isolated, in-memory SQLite database.

## Run locally with Docker

Start Docker Desktop first. Set a nonempty `DB_PASSWORD` in `.env`, then run
these commands from the project directory:

```sh
docker compose build app
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
docker compose up -d
```

Open http://127.0.0.1:8000 and log in with username `admin` and password `admin@12`.
The existing compiled frontend assets are included, so Node.js is only needed
when changing those assets. Database data is stored in a Docker volume.

After the first setup, use `docker compose up -d` to start and
`docker compose stop` to stop the project. Use `docker compose logs -f app`
to view server logs. Run the migration and seed command only during initial setup.

## Run locally with PHP and Composer

The locked dependencies require PHP 7.4. On this Mac, native PHP and MariaDB
are installed through the Apple Silicon Homebrew at `/opt/homebrew`.
With the local database configured in `.env` and dependencies installed, run:

```sh
/opt/homebrew/bin/brew services start mariadb
./run-local.sh
```

Open http://127.0.0.1:8000. Press Ctrl+C to stop the app server. MariaDB runs
as a background service; stop it with `/opt/homebrew/bin/brew services stop mariadb`.
`run-local.sh` selects the installed PHP 7.4 automatically. Use `PHP_BIN` to
choose another PHP executable or `PORT` to choose another app port.
Native mode uses `DB_HOST=127.0.0.1` in `.env`; Docker overrides it to `db`.
Stop one app server before starting the other, since both use port 8000.
The native database was copied from Docker during setup. The two databases
are separate; later changes to one do not automatically appear in the other.

A local Composer executable is available for maintaining dependencies:

```sh
COMPOSER_CACHE_DIR="$PWD/.local/composer-cache" /opt/homebrew/opt/php@7.4/bin/php .local/composer.phar install
```

The database export is saved in `.local/database-before-native.sql`.
The `.local` directory is excluded from Git.

For a fresh installation, follow the steps below with PHP 7.4 and Composer.

1. Download the repository or clone using this command in the terminal.
 ```
 git clone https://github.com/MusheAbdulHakim/TailorShop-Management-System.git

```

2. Go into the project folder. or use this command in the terminal 
```
cd TailorShop-Management-System
```

3. rename the file name `.env.example` to `.env` or copy it and rename paste it at the project root and name it `.env`.You can simply use this command.
```
cp .env.example .env
```

4. Generate app key using this command.
```php artisan key:generate ```

5. setup your database and your app name in the .env file 

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tms
DB_USERNAME=root
DB_PASSWORD=

```

6. Install dependencies using the following command
```
composer install

```

7. Run the following command in the terminal to run migrations and seeds

 ```
 php artisan migrate:fresh --seed
 ```
 You can also import the tms.sql file in the database folder. 

8. Install npm packages
```
npm install; npm run dev
```
9. Start the development server in the terminal using 

```
php artisan serve
```

10. Open your browser and go to the address in the terminal.Usually like this 

```
http://127.0.0.1:8000/

```

11. Login to the default user :

```
username: admin
password: admin@12

```

12. Star the repository,request features,report bugs, fork and send your pull request i will be happy to merge them :).

# Screenshots
![ScreenShot](/screenshots/login.png?raw=true "Login page")
![Dashboard](/screenshots/dashboard.png?raw=true "Dashbaord page")

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 1500 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


### KPK stitching preferences, karigars and employee pay

- In **Karigars & pay**, add employees, enable their karigar role, and choose one method: monthly salary, percentage, PKR per order, or PKR per complete shalwar kameez suit. Existing staff keep their previous salary as the initial monthly rate. Rates are in Pakistani rupees; the business timezone is Asia/Karachi.
- On an order, select a karigar and quantity. A shalwar kameez set counts as one suit; other garments count as pieces. The entered total price covers the entire quantity. Orders can remain unassigned while unfinished; select a karigar before marking Ready / Delivered.
- Specify Straight / Seedha, Round / Gol, Side-cut or custom daman, plus collar, cuff, shalwar and pocket instructions. Only relevant style options are saved for each garment. Urdu names and instruction text are supported. Choices remain attached to the individual order and appear on its printed work card; internal earnings are hidden when printing.
- **Overview** and **Expenses** default to the current week (Monday through today), with a current-month option. The team report shows current unfinished assignments, orders and suits completed within the period, accrued salary, saved work earnings and a total per employee.
- Percentage uses the full order price, regardless of customer payment balance. Per-order pay counts an order once; per-suit pay multiplies the rate by the complete set quantity. A non-set garment earns zero under the per-suit method, with an explanation shown before saving. Monthly salary accrues each calendar day as monthly rate divided by the days in that month, beginning on employment start and ending on the archive date inclusive. Reports accrue through today.
- Salary changes take effect today and retain earlier daily rates. Work earnings are saved on the first transition to Ready / Delivered using that employee's current method and rate. Delivery does not earn twice. Completed orders retain their karigar, customer, garment, price, quantity and earnings; they cannot be deleted or returned to unfinished status. Reassign unfinished work before archiving an employee; history is retained.
- Existing Ready / Delivered orders without a completion timestamp remain excluded from new completion/pay totals; editing their notes does not fabricate a completion date.
- Calculated earnings are separate from recorded expense payments. This is an earnings report, not a paid/unpaid payroll ledger. Enter actual wage payments under an expense category when paid; expense totals use purchase date.

Verification: `php vendor/bin/phpunit` uses an isolated in-memory SQLite database, including pay-method calculations, rate history, date boundaries, assignment restrictions, completion snapshots and expense filters.


### Garment selector and international mobile numbers

Choose **Measurement type / garment** to show only the relevant fields. Complete shalwar kameez shows both sections; shirt, pant and each waskat type show their own fields. The selected garment's required sizes must be entered before saving. Switching types recalls matching saved sizes and keeps drafts in the current page. Save each garment before leaving the page: only the selected garment is submitted.

Saving creates a new version. Other garment sizes are retained when the previous version has the same unit and Full/Half convention; otherwise a separate version is saved so measurements with different units or conventions are never mixed. Earlier versions and order snapshots remain available.

Pakistani mobile numbers must start with **03** and contain **11 digits**. **+923…** is also accepted and stored as **03…** for consistent search. Saudi mobile numbers use **+9665** followed by eight digits, for example **+966512345678**. Other international numbers require **+**, a country code and 8–15 total digits. Input must have no spaces, letters or punctuation other than the leading plus. This checks supported number formats, not whether a number is active or belongs to the customer. Customer and order searches accept phone numbers; formatted full Pakistani searches are normalized to their stored local equivalent. Staff use the same phone validation.

Reference: [ITU E.164 international number length](https://www.itu.int/rec/T-REC-E.164/en), [Saudi CST mobile numbering plan](https://www.cst.gov.sa/en/about/Numbering).


### Separate Employees, Expenses and Income modules

Create order offers two customer paths. **Add a new customer** saves their details and returns directly to the order. **Search & load customer** finds an existing record by its complete normalized mobile number; shared numbers require selecting the correct customer. After selecting a customer, only their name/mobile and Edit customer details / Change customer links appear above the garment form; the customer selection panel and duplicate customer record card are hidden. Selecting a garment displays editable measurement fields, prefilled from the latest profile, alongside the applicable daman/collar/cuff/shalwar choices. Suit shows Kameez and Shalwar together. Each additional garment has its own editable sizes and karigar. Normal/emergency priority and received/completion dates are shared across the booking. **Save complete customer order** saves the orders and updated measurement profiles in one transaction. Unchanged sizes reuse the profile, and past orders keep their original snapshots. Changing units or Full/Half clears entered sizes without conversion. Standalone measurement entry and historic selection when editing existing orders remain available. Navigating away or loading a different customer clears unsaved order input.

The sidebar shows **Employees**, **Expenses** and **Income** as separate, always-visible business modules. Business modules are direct sidebar links. Related settings are accessed through page tabs inside each module: Employees & pay / Employee roles, Expense records / Expense categories, and Income records / Income categories.

**Employees** manages staff details, karigar eligibility and pay rates. **Expenses** records payments by purchase/payment date and offers default categories for shop rent, electricity/gas/water/internet bills, employee salaries, tools, sewing machines, maintenance, buttons, buckram/bukram, thread, needles, zips/accessories, fabric/lining and other expenses. Additional categories can be added under Expense categories.

For **Employee salaries**, select the employee receiving payment and enter the actual amount paid; use the description for the salary period. Existing salary entries retain links to archived employees, while new salary entries require an active employee. Employee earnings are calculated separately and never automatically posted as expenses. The salary category and categories used by financial records cannot be deleted.

**Income** records actual money received, with default categories for stitching payments, alterations and other income. Income and expenses have their own forms, lists, edit/delete actions and weekly/monthly totals. Their dashboard totals use income date and purchase date respectively. Recording an order payment does not automatically create an income entry; enter received money once in Income.

The finance migration preserves existing records and adds categories without replacing them. Before applying it locally, a database backup was saved at `.local/database-before-finance-modules.sql`.


Employee forms also include optional payment-account details: Cash, Easypaisa, JazzCash, Bank account or Other account; account number/IBAN, bank/provider name and account title. Wallet numbers use 11 digits starting with 03. Bank identifiers are stored as text so leading zeros remain unchanged; the form checks characters and length, not bank ownership or IBAN checksum. The team list shows the provider and last four digits, while the edit form shows the full account. All account details may be left blank. Changing account details leaves pay-rate history unchanged.


All delete/archive actions open a shared confirmation dialog with the selected record's name or reference. Cancel is focused first; deletion requires the red **Yes, delete** button (or **Yes, archive** for employees). Closing or cancelling clears the selected ID, and repeat submissions are blocked while processing. Existing server rules still protect completed orders, employees with unfinished work and categories with financial records.


### Emergency orders and planned work dates

Customer receipts can include several garment orders. In Create Order, load the customer once, enter the first garment, then use **Add garment to this order** for additional categories. Enter or edit measurements, quantity, price/payment and karigar per garment. Received/delivery dates, customer and initial status are entered once and shared. **Save complete customer order** saves every garment in one transaction; an invalid garment rolls back the complete booking. The form shows combined totals and preserves submitted garment rows after validation errors. Up to 20 garment entries are supported per submission. After saving, use **Add another garment to this receipt** on the detail page if needed. Each garment retains its own bundle code, quantity, price, payment, dates, assignment, status and measurement snapshot. Only explicitly linked garments for the same customer are combined; unrelated past bookings are excluded. The customer receipt shows the stable receipt number, all garment categories and quantities, per-garment received/delivery dates and combined price/paid/balance. Searching the receipt number finds its linked garments. Deleted garment entries are excluded from current receipts.

**After saving a complete order**, the app opens **Print order copies** with **Print for customer** (one combined receipt) and **Print for karigar** (all garment sheets). Each print view links back to this screen so the other copy can be printed. Existing orders can reopen this screen using **Print copies**. Printing does not change the stitching status.

**Print all karigar sheets** produces a separate print page for each garment order and its assigned karigar. A Shalwar kameez set prints Kameez and Shalwar together; waskat, shirt and pant orders each print separately, using only the relevant saved measurements. Individual garment sheets and tags remain available.

**Shop settings** is accessible from the sidebar, Orders and order details. Store the shop name, phone, address and an optional PNG/JPG/WebP logo there. These print on customer receipts and karigar sheets; an initial appears until a logo is uploaded. Logo delivery uses a dedicated local route, also available on the sign-in page, so the desktop app does not depend on a storage symlink. The app has a Back button on its main pages; it uses same-origin navigation history, with Overview as the direct-launch fallback. Unsaved form edits are not saved by Back.

The sidebar no longer lists Garment types, Measurement settings, Users or Preferences. Existing data and internal routes are retained.

Orders support **Normal** (default) and **Emergency** priority. Emergency orders have a red text-labelled badge on order lists, details and printable order cards. Orders can be filtered by priority. The form highlights Emergency in red when selected.

Work start and end dates describe the **planned stitching schedule**, separately from date received, customer collection date and actual completion timestamp. Validation requires: date received ≤ work start ≤ work end ≤ collection date. Same-day schedules are allowed. Priority is stored in a database enum and work dates in date columns. Existing valid orders receive their received/collection dates as initial schedule values; invalid legacy ranges are left unset for correction. Updates that omit the new fields retain saved values. Before the local migration, a backup was saved in `.local/database-before-order-priority.sql`.


The simplified customer forms omit City, Customer notes and Email. Order forms omit work start/end fields and free-text order/custom-style instructions; additional garment rows use the same simplified fields. Standard daman, collar, sleeve finish and shalwar style dropdowns remain, with Custom omitted. Only received and delivery dates are shown; internal schedule defaults follow that range and remain valid when it changes. Earlier saved email, notes and custom instructions are retained when those omitted fields are not submitted.


### Dashboard and owner dues

Overview shows clickable order cards, customer order balances, other money to receive and money to pay. These outstanding balances cover all dates, while recorded income/expenses and employee charts use the selected weekly/monthly period. Next deliveries open directly into an order. Employee work/pay details expand on one click.

Income includes **Money to receive** and **Money to pay** tabs. Record a person/contact, optional phone, category, amount, recorded date and optional due date. Customer order balances are already tracked separately; do not re-enter those as manual dues. Dues record money owed, not cash received or paid. A partial or full payment automatically creates an Income entry under Dues received, or an Expense under Dues paid. Do not manually record that settlement again. Loan repayments are recorded cash movements, not a profit calculation. Each due shows its payment history and remaining balance. Duplicate submission tokens are idempotent, overpayments are rejected, and payment/balance/financial writes are atomic. Settled records and linked Income/Expense entries cannot be edited or deleted through the forms. Unpaid dues may be deleted after confirmation.

The local database was backed up to `.local/database-before-dues.sql` before applying the dues migration.

### Expense ledger

Expenses starts with a weekly ledger, totals for all matching records, actual employee payments and record count. Filter by Week, Month or a custom date range, category, or description/employee name. The ledger shows 20 records per page; totals and category breakdown include all matches. Add expense opens a separate form for category, amount paid, payment date, employee (required for salary payments) and optional details/reference. Amounts must be positive with at most two decimal places. Custom report filters survive saving. Payments made through Money to pay appear in the ledger with a link to their due history and cannot be edited or deleted independently. Recorded salary payments remain separate from calculated earnings.

## Backups to another drive

In the self-contained desktop app, choose **Backups → Choose folder** and select a folder on an external SSD, hard drive, USB drive, or another physical disk. The app saves a complete dated backup on the first launch of each day, checks for a new day every 30 minutes while open, and saves again when closing. **Back up now** makes an extra copy. The backup contains a consistent, integrity-checked SQLite database, the encryption key, uploaded logos/images, and a manifest of file checksums. Finished backup folders start with `Tailor-Shop-Backup-`; partial backups are never offered as completed copies. No older backups are automatically deleted.

Keep the drive connected. If it is unavailable, the app records the failure and keeps the last successful backup date; the next scheduled check retries. Startup and closing failures show a message. Backups cannot run when the app is closed or the computer is off. The backup menu shows the chosen folder, last success and any failure. Backups on the same physical disk do not protect against that disk failing.

To recover after reinstalling Windows or the app, install this version or a newer version and choose **Restore an existing shop backup** on the welcome screen. Select the entire dated backup folder, confirm, and sign in with the account saved in that backup. For an existing installation use **Backups → Restore backup**. Restore checks file hashes, rejects incomplete/damaged or newer-version backups, stops the local backend, and keeps a safety copy of current records before replacement. A failed migration rolls back the replacement. App uninstall preserves local data, and backups in the selected external folder stay outside the app installation.

The backup controls apply to the self-contained SQLite desktop build; this development checkout uses its separate MariaDB database. Test drive disconnection and restore on Windows before client delivery.


## Shop appearance and offline activation (1.0.6)

Shop settings saves the shop name, logo/picture, phone/address, button color,
navigation color and app background color. A preview shows unsaved changes;
Save applies them across the workspace and sign-in screen. Receipts and karigar
sheets use the saved identity and keep their existing print styling. Colors
are validated as six-digit hex values; button/navigation text uses contrasting
colors. Defaults can be restored without changing the shop identity. The
appearance migration is applied at desktop startup and included in backups.

The customer desktop build now requires offline activation before starting its
backend or opening the shop database. It displays a computer code derived from
the Windows MachineGuid. The customer copies the code or exports a request text
file, sends it to the provider, and imports the signed .lic file returned by the
provider. The license is permanent and bound to that computer. It persists
outside installation files, is verified on every launch, and is not transferred
in a shop-data backup. A restored shop on another PC needs its own license.
Windows reinstallation or changed machine identity may require reactivation.
Existing customer accounts and passwords are preserved.

The private signing key and owner tool are in `.local/licensing` (excluded from
Git and customer builds). Only `desktop/license-public-key.pem` is packaged.
Keep a secure backup of the private key to issue future licenses. Do not share
the owner tool folder with customers. The generated owner tool folder is
`~/Tailor-Shop-Owner-Only`; double-click `Create-License.command` to enter a
computer code and shop name. Send only the resulting .lic file.

Earlier releases, including 1.0.5, have no activation requirement and cannot be
revoked offline. This protection discourages casual copying; it does not prevent
modified builds or deliberate cracking. Validate installer launch, code export,
license import, relaunch and wrong-computer rejection on Windows before delivery.

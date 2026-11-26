<?php
// Invoked only by the desktop main process, never through an HTTP route.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    if (($argv[1] ?? '') === 'account') {
        if (App\Models\User::exists()) throw new RuntimeException('An account already exists. Sign in instead.');
        $data = json_decode(stream_get_contents(STDIN), true) ?: [];
        $validator = Illuminate\Support\Facades\Validator::make($data, [
            'name' => ['required', 'string', 'max:100', new App\Rules\PersonName],
            'username' => ['required', 'string', 'regex:/^[A-Za-z0-9_.-]{3,50}$/'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);
        if ($validator->fails()) throw new RuntimeException($validator->errors()->first());
        App\Models\User::create([
            'name' => $data['name'], 'username' => $data['username'],
            'email' => $data['username'].'@local.invalid',
            'password' => Illuminate\Support\Facades\Hash::make($data['password']),
        ]);
    } else {
        $database = config('database.connections.sqlite.database');
        // SQLite produces a consistent snapshot, even after an interrupted exit.
        if (is_file($database) && filesize($database) > 0) {
            $pdo = new PDO('sqlite:'.$database);
            $backupDir = dirname($database).'/backups';
            if (!is_dir($backupDir)) mkdir($backupDir, 0700, true);
            $release = json_decode(file_get_contents(getcwd().'/release.json'), true);
            $releaseId = substr($release['digest'] ?? 'local', 0, 12);
            $destination = $backupDir.'/shop-'.date('Y-m-d').'-'.$releaseId.'.sqlite';
            if (!is_file($destination)) $pdo->exec('VACUUM INTO '.$pdo->quote($destination));
            $pdo = null;
        }
        if ($kernel->call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Database update failed. Your previous data is preserved in backups.');
    }
    if (!App\Models\User::exists() && getenv('TAILOR_DEFAULT_ACCOUNT') === '1') {
        App\Models\User::create([
            'name' => 'Shop Admin', 'username' => 'admin',
            'email' => 'admin@local.invalid',
            'password' => Illuminate\Support\Facades\Hash::make('admin@12'),
        ]);
    }
    echo json_encode(['needsSetup' => !App\Models\User::exists()]);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage());
    exit(1);
}

<?php
// Run from a terminal with the development database available.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $user = App\Models\User::where('username', 'admin')->firstOrFail();
    $user->password = Illuminate\Support\Facades\Hash::make('admin@12');
    $user->remember_token = null;
    $user->save();
    if (!Illuminate\Support\Facades\Hash::check('admin@12', $user->fresh()->password)) {
        throw new RuntimeException('Password verification failed.');
    }
    echo "Admin password updated and verified.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Could not reset the local admin account: ".$error->getMessage()."\n");
    exit(1);
}

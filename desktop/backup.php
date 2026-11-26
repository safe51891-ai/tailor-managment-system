<?php
// Standalone snapshot helper; never served by the app's web server.
try {
    $source = $argv[1] ?? '';
    $target = $argv[2] ?? '';
    if (!is_file($source) || file_exists($target)) throw new RuntimeException('Invalid backup source or destination.');
    $database = new PDO('sqlite:'.$source);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA busy_timeout = 10000');
    $database->exec('VACUUM INTO '.$database->quote($target));
    $copy = new PDO('sqlite:'.$target);
    if ($copy->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('The database backup did not pass its integrity check.');
    echo 'ok';
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage());
    exit(1);
}

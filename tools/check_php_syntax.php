<?php

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$errors = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR)) continue;
    $cmd = 'php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
    $output = shell_exec($cmd);
    if (!str_contains($output, 'No syntax errors detected')) {
        echo $output, PHP_EOL;
        $errors++;
    }
}
echo $errors === 0 ? "PHP syntax check passed.\n" : "$errors PHP file(s) need attention.\n";
exit($errors ? 1 : 0);

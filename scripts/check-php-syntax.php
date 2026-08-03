<?php

declare(strict_types=1);

$roots = [
    __DIR__.'/../apps/api/app',
    __DIR__.'/../apps/api/bootstrap',
    __DIR__.'/../apps/api/config',
    __DIR__.'/../apps/api/public',
    __DIR__.'/../apps/api/routes',
    __DIR__.'/../apps/api/tests',
    __DIR__.'/../packages/duel-engine/src',
    __DIR__.'/../packages/duel-engine/tests',
    __DIR__.'/../packages/bot-engine/src',
    __DIR__,
];

$files = [];
$singleFiles = [__DIR__.'/../apps/api/artisan'];
foreach ($singleFiles as $file) {
    if (is_file($file)) {
        $files[realpath($file)] = true;
    }
}
foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            $files[$file->getRealPath()] = true;
        }
    }
}

ksort($files);
foreach (array_keys($files) as $file) {
    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg((string) $file);
    passthru($command, $status);
    if ($status !== 0) {
        exit($status);
    }
}

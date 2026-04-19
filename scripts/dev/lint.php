<?php

declare(strict_types=1);

$paths = array_slice($argv, 1);

if ($paths === []) {
    $paths = ['src', 'tests', 'scripts'];
}

$phpBinary = PHP_BINARY;
$failures = [];

foreach ($paths as $path) {
    if (!file_exists($path)) {
        fwrite(STDERR, "Path not found: {$path}" . PHP_EOL);
        $failures[] = $path;
        continue;
    }

    $iterator = is_file($path)
        ? new ArrayIterator([new SplFileInfo($path)])
        : new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $command = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($file->getPathname());
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $failures[] = $file->getPathname();
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, PHP_EOL . 'Lint failed for ' . count($failures) . ' path(s).' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Lint passed.' . PHP_EOL);

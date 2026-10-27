<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

test('independent processes preserve run admission leases cancellation and source writes under contention', function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Crash recovery uses POSIX signals; run the compatibility probe on Unix.');
    }
    $root = sys_get_temp_dir().'/lens-concurrency-'.Str::uuid();
    mkdir($root, 0700, true);
    file_put_contents($root.'/.lens-compatibility-consumer', hash('sha256', realpath($root)));
    symlink(dirname(__DIR__, 2).'/vendor', $root.'/vendor');
    file_put_contents($root.'/composer.json', '{"name":"lens/stress-consumer","autoload":{"psr-4":{"App\\\\":"app/"}}}');
    $process = new Process([PHP_BINARY, dirname(__DIR__).'/Compatibility/stress.php'], $root, timeout: 60);
    try {
        $process->run();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $result = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        expect($result['locked_updates'])->toBe(480)->and($result['concurrent_reads'])->toBe(4000)
            ->and($result['checks'])->toBeGreaterThan(100);
    } finally {
        File::deleteDirectory($root);
    }
})->group('stress');

<?php
require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Bandwidth\Bandwidth;
use React\Promise\Deferred;
use function React\Async\async;
use function React\Async\await;

function getMemoryUsage(): array
{
    return [
        'date' => date('Y-m-d H:i:s'),
        'current_usage_mb' => round(memory_get_usage() / 1024 / 1024, 3),
        'current_usage_real_mb' => round(memory_get_usage(true) / 1024 / 1024, 3),
        'peak_usage_mb' => round(memory_get_peak_usage() / 1024 / 1024, 3),
        'peak_usage_real_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 3),
    ];
}

define("BURST_RATE", 1024 * 1024 * 5); // 150KB/sec burst rate
define("FILL_RATE", 1024 * 1024 * 1);   // 50KB/sec sustained rate


async(function () {
    while (true) {
        echo "start\n";
        $deferred = new Deferred();
        $bandwidth = new Bandwidth(BURST_RATE, FILL_RATE);

        // Listen starting memory
        echo 'Start memory: ' . json_encode(getMemoryUsage(), JSON_UNESCAPED_SLASHES) . PHP_EOL;

        $filePath = __DIR__ . '/test.txt'; // Put a sample file here to try
        $stream = $bandwidth->file($filePath);

        $stream->on('data', function ($data) {
            echo (strlen($data)/1024/1024 ). "MB\n";
            gc_collect_cycles();
        });

        $stream->on('end', function () use ($deferred) {
            echo PHP_EOL . "end" . PHP_EOL;
            echo 'End memory: ' . json_encode(getMemoryUsage(), JSON_UNESCAPED_SLASHES) . PHP_EOL;
            $deferred->resolve(true);
        });

        $stream->on('error', function ($e) use ($deferred) {
            fwrite(STDERR, 'Error: ' . ($e instanceof \Throwable ? $e->getMessage() : (string) $e) . PHP_EOL);
            echo 'Memory at error: ' . json_encode(getMemoryUsage(), JSON_UNESCAPED_SLASHES) . PHP_EOL;
            $deferred->reject(new \Exception($e->getMessage()));
        });

        await($deferred->promise());
        break;

    }
})();


async(function () {
    // $filesystem = \React\Filesystem\Factory::create();
    $filesystem = new \React\Filesystem\Fallback\Adapter;
    $path = __DIR__ . '/test.txt';
    $size = filesize($path);

    $chunkSize = 1024 * 1024 * 1;
    $start = 0;
    while (true) {
        echo "start\n";
        await($filesystem->file($path)->getContents($start, $chunkSize));
        \React\Async\delay(1);
        $start += $chunkSize;
        if ($start >= $size) {
            break;
        }
    }
});

$startMemory = getMemoryUsage();

\React\EventLoop\Loop::addPeriodicTimer(1, function () use ($startMemory) {
    gc_collect_cycles();
    echo json_encode([
        'start_memory' => $startMemory,
        'current_memory' => getMemoryUsage(),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
});
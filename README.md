# reactphp-x/bandwidth

## install

```bash
composer require reactphp-x/bandwidth -vvv
```

## usage

### file

```php
<?php
require 'vendor/autoload.php';

use ReactphpX\Bandwidth\Bandwidth;

define("BURST_RATE", 1024 * 1024 * 150); // 150KB/sec burst rate
define("FILL_RATE", 1024 * 1024 * 50); // 50KB/sec sustained rate

$bandwidth = new Bandwidth(BURST_RATE, FILL_RATE);

$stream = $bandwidth->file('test.txt');

$stream->on('data', function ($data) {
    echo $data;
});

$stream->on('end', function () {
    echo "end\n";
});

```
### stream

```php
<?php
require 'vendor/autoload.php';

use ReactphpX\Bandwidth\Bandwidth;

define("BURST_RATE", 1024 * 1024 * 5); // 5MB/sec burst rate

define("FILL_RATE", 1024 * 1024 * 1); // 1M/sec sustained rate

$bandwidth = new Bandwidth(BURST_RATE, FILL_RATE);

$sourceStream = new \React\Stream\ThroughStream();

$bandwidthStream = $bandwidth->stream($sourceStream);

$bandwidthStream->on('data', function ($data) use ($sourceStream) {
    echo $data;
    // when get data, you can write to stream
    // todo $sourceStream->write('sonething');
}); 

// to write data to the source stream
$sourceStream->write('hello');


$sourceStream->on('end', function () use ($bandwidthStream) {
    $bandwidthStream->close();
});

\React\EventLoop\Loop::addTimer(5, function () use ($sourceStream) {
    $sourceStream->end();
});

```

### memory usage

```php
<?php
require 'vendor/autoload.php';

use ReactphpX\Bandwidth\Bandwidth;

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

define("BURST_RATE", 1024 * 1024 * 150); // 150KB/sec burst rate
define("FILL_RATE", 1024 * 1024 * 50); // 50KB/sec sustained rate

$bandwidth = new Bandwidth(BURST_RATE, FILL_RATE);

// listen starting memory
$startMemory = getMemoryUsage();
echo 'Start memory: ' . json_encode($startMemory, JSON_UNESCAPED_SLASHES) . PHP_EOL;

$stream = $bandwidth->file('test.txt');

$stream->on('data', function ($data) {
    echo $data;
});

$stream->on('end', function () {
    echo "end\n";
    echo 'End memory: ' . json_encode(getMemoryUsage(), JSON_UNESCAPED_SLASHES) . PHP_EOL;
});
```

## License

MIT
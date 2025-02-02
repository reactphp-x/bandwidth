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

define("BURST_RATE", 1024 * 1024 * 150); // 150KB/sec burst rate

define("FILL_RATE", 1024 * 1024 * 50); // 50KB/sec sustained rate

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

## License

MIT
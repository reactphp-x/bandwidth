# reactphp-x/bandwidth

## install

```bash
composer require reactphp-x/bandwidth -vvv
```

## usage

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

## License

MIT
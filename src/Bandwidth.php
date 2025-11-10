<?php

namespace ReactphpX\Bandwidth;

use ReactphpX\Concurrent\Concurrent;
use ReactphpX\Limiter\TokenBucket;
use React\Filesystem\Factory;
use function React\Async\async;
use function React\Async\await;

final class Bandwidth
{

    protected TokenBucket $bucket;
    protected $filesystem;

    protected $KB;

    protected Concurrent $concurrent;

    public function __construct(int $bucketSize = 0, int $tokensPerInterval = 1024, protected $queue = false)
    {
        $this->bucket = new TokenBucket($bucketSize, $tokensPerInterval, 1000);
        $this->KB = $this->bucket->getTokensPerInterval()/1024/1024;
        $this->concurrent = new Concurrent(1);

        if (function_exists('app') && app()->bound('reactphp.filesystem')) {
            $this->filesystem = app('reactphp.filesystem');
        } else {
            $this->filesystem = Factory::create();
        }
    }

    public function file(string $path, $p = 0, $length = -1, $readKB = 0)
    {
        $stream = new \React\Stream\ThroughStream();

        $readKB = $readKB > 0 ? min($readKB, $this->KB) : $this->KB;

        async(function ($path, $stream, $p, $length, $readKB) {
            try {
                $node = await($this->filesystem->detect($path));
                if (!($node instanceof \React\Filesystem\Node\FileInterface)) {
                    throw new \RuntimeException($path . ' is not a file');
                }
                $stat = await($node->stat());

                if ($this->queue) {
                    await($this->concurrent->concurrent(
                        async(function () use ($stream, $stat, $p, $length, $readKB) {
                            $file = $this->filesystem->file($stat->path());
                            $size = $stat->size();
                            if ($length > 0) {
                                $size = min($size, $p + $length);
                            }
                            return await($this->fileStream($file, $stream, $p, $size, $readKB));
                        })
                    ));
                } else {
                    $file = $this->filesystem->file($stat->path());
                    $size = $stat->size();
                    if ($length > 0) {
                        $size = min($size, $p + $length);
                    }
                    await($this->fileStream($file, $stream, $p, $size, $readKB));
                }
            } catch (\Throwable $e) {
                $stream->emit('error', [$e]);
            }
        })($path, $stream, $p, $length, $readKB);
        return $stream;
    }

    public function stream($stream)
    {
        $_stream = new \React\Stream\ThroughStream();

        $concurrent = $this->queue ? $this->concurrent : new Concurrent(1);
        
        $stream->on('data', function ($data) use ($_stream, $concurrent) {
            $concurrent->concurrent(
                async(function () use ($_stream, $data) {
                    await($this->bucket->removeTokens(1024 * strlen($data)));
                    $_stream->write($data);
                })
            );
        });
       
        return $_stream;
    }

    protected function fileStream($file, $stream, $p, $size, $readKB)
    {

		return async(function () use ($file, $stream, $p, $size, $readKB) {
			while (true) {
				if (!$stream->isWritable()) {
					return true;
				}
				$remaining = $size - $p;
				if ($remaining <= 0) {
					return true;
				}
				if ($remaining/1024 < $readKB) {
					await($this->bucket->removeTokens(1024 * 1024 * ceil($remaining/1024)));
					$contents = await($file->getContents($p, $remaining));
					$stream->end($contents);
					return true;
				} else {
					await($this->bucket->removeTokens(1024 * 1024 * $readKB));
					$contents = await($file->getContents($p, 1024 * 1024 * $readKB));
					$p += strlen($contents);
					if ($p >= $size) {
						$stream->end($contents);
						return true;
					}
					$stream->write($contents);
				}
			}
		})();
    }
}
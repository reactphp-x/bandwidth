<?php

namespace ReactphpX\Bandwidth;

use ReactphpX\Concurrent\Concurrent;
use ReactphpX\Limiter\TokenBucket;
use React\Filesystem\Factory;

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

    public function file(string $path)
    {
        $stream = new \React\Stream\ThroughStream();

        $this->filesystem->detect($path)->then(function ($node) use ($path) {
            if ($node instanceof \React\Filesystem\Node\FileInterface) {
                return $node->stat();
            } else {
                throw new \RuntimeException($path. ' is not a file');
            }
        })->then(function ($stat) use ($stream) {
            if ($this->queue) {
                return $this->concurrent->concurrent(function () use ($stream, $stat) {
                    $file = $this->filesystem->file($stat->path());
                    return $this->fileStream($file, $stream, 0, $stat->size());
                });
            } else {
                $file = $this->filesystem->file($stat->path());
                return $this->fileStream($file, $stream, 0, $stat->size());
            }
        }, function ($e) use ($stream) {
            $stream->emit('error', [$e]);
        });
        return $stream;
    }

    protected function fileStream($file, $stream, $p, $size)
    {

        if (!$stream->isWritable()) {
            return \React\Promise\resolve(null);
        }

        $currentSize = $size - $p;

        if ($currentSize/1024 < $this->KB) {
            return $this->bucket->removeTokens(1024 * 1024 * ceil($currentSize/1024))->then(function () use ($file, $stream, $p, $currentSize) {
                return $file->getContents($p, $currentSize)->then(function ($contents) use ($stream) {
                    $stream->end($contents);
                    return null;
                });
            });
        } else {
            return $this->bucket->removeTokens(1024 * 1024 * $this->KB)->then(function () use ($file, $stream, $p, $size) {
                return $file->getContents($p, 1024 * 1024 * $this->KB)->then(function ($contents) use ($stream, $file, $p, $size) {
                    $p += strlen($contents);
                    if ($p >= $size) {
                        $stream->end($contents);
                        return null;
                    } else {
                        $stream->write($contents);
                        return $this->fileStream($file, $stream, $p, $size);
                    }
                });
            });
        }
    }
}
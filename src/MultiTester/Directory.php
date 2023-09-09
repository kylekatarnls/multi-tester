<?php

declare(strict_types=1);

namespace MultiTester;

class Directory
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    private $executor;

    public function __construct(string $path, ?string $executor = 'shell_exec')
    {
        $this->path = $path;
        $this->executor = $executor ?? 'shell_exec';
    }

    public function copy($destination, $exceptions = []): bool
    {
        $source = $this->path;
        $files = @scandir($source);

        if (!is_array($files)) {
            return false;
        }

        (new static($destination))->create();
        $success = true;

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && !in_array($file, $exceptions, true)) {
                $this->copyItem($source, $destination, $file, $success);
            }
        }

        return $success;
    }

    public function clean(): bool
    {
        $dir = $this->path;

        if (!@is_dir($dir)) {
            return false;
        }

        clearstatcache();
        $arg = escapeshellarg($dir);
        ($this->executor)('rm -rf ' . $arg . '/.* 2>&1 && rm -rf ' . $arg . '/* 2>&1');
        $success = true;

        foreach (@scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $this->cleanItem($dir . '/' . $file, $success);
            }
        }

        return $success;
    }

    public function remove(): bool
    {
        $this->clean();

        return @rmdir($this->path);
    }

    public function create(): bool
    {
        $dir = $this->path;
        if (@is_dir($dir)) {
            return $this->clean();
        }

        if (@is_file($dir)) {
            @unlink($dir);
        }

        return @mkdir($dir, 0777, true);
    }

    protected function copyItem($source, $destination, $file, &$success): void
    {
        $path = "$source/$file";

        if (@is_dir($path)) {
            if (!(new static($path))->copy("$destination/$file")) {
                $success = false;
            }

            return;
        }

        if (!@copy($path, "$destination/$file")) {
            $success = false;
        }
    }

    protected function cleanItem($path, &$success): void
    {
        if (@is_dir($path)) {
            if (!(new static($path))->clean() || !@rmdir($path)) {
                $success = false;
            }

            return;
        }

        if (!@unlink($path)) {
            $success = false;
        }
    }
}

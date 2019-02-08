<?php

namespace MultiTester;

class Directory
{
    /**
     * @var string
     */
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function copy($destination, $exceptions = [])
    {
        $source = $this->path;
        $files = @scandir($source);

        if (!is_array($files)) {
            return false;
        }

        (new static($destination))->create();
        $success = true;

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && !in_array($file, $exceptions)) {
                $this->copyItem($source, $destination, $file, $success);
            }
        }

        return $success;
    }

    public function clean()
    {
        $dir = $this->path;

        if (!@is_dir($dir)) {
            return false;
        }

        clearstatcache();
        $arg = escapeshellarg($dir);
        shell_exec('rm -rf ' . $arg . '/.* 2>&1 && rm -rf ' . $arg . '/* 2>&1');
        $success = true;

        foreach (@scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $this->cleanItem($dir . '/' . $file, $success);
            }
        }

        return $success;
    }

    public function remove()
    {
        $this->clean();

        return @rmdir($this->path);
    }

    public function create()
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

    protected function copyItem($source, $destination, $file, &$success)
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

    protected function cleanItem($path, &$success)
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

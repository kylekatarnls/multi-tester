<?php

namespace MultiTester;

use MultiTester\Traits\ErrorHandler;

final class SourceFinder
{
    use ErrorHandler;

    public function __construct($workingDirectory)
    {
        $this->setWorkingDirectory($workingDirectory);
    }

    public function getFromFirstValidPlatform($package, $platforms): ?array
    {
        foreach ($this->parsePlatformList($platforms) as $platform) {
            $result = $this->getSourceFromPlatform($package, $platform);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private function getSourceFromLibrariesIo($package): ?array
    {
        $file = new File('https://libraries.io/api/Packagist/' . urlencode($package), 'json');

        return $file->isValid() ? $file->toArray() : null;
    }

    private function getSourceFromPackagist($package, $namespace = 'p2'): ?array
    {
        $file = new File("https://repo.packagist.org/$namespace/$package.json");

        return $file->isValid()
            ? (($file['packages'] ?? [])[$package] ?? null)
            : null;
    }

    private function getSourceFromPackagist2($package): ?array
    {
        $list = $this->getSourceFromPackagist($package);

        if (!is_array($list)) {
            return $list;
        }

        $listByVersion = [];
        $previousItem = [];

        foreach ($list as $item) {
            if (isset($item['version'])) {
                $previousItem = array_merge($previousItem, $item);
                $listByVersion[$item['version']] = $previousItem;
            }
        }

        return $listByVersion;
    }

    private function getSourceFromPlatform($package, $platform): ?array
    {
        switch ($platform) {
            case 'composer1':
            case 'packagist1':
                return $this->getSourceFromPackagist($package, 'p');

            case 'composer':
            case 'packagist':
            case 'composer2':
            case 'packagist2':
            case 'packagist.org':
                return $this->getSourceFromPackagist2($package);

            case 'libraries':
            case 'libraries.io':
                return $this->getSourceFromLibrariesIo($package);

            default:
                $this->error("Unknown platform '$platform'");
        }
    }

    /**
     * @param mixed $platforms
     */
    private function parsePlatformList($platforms): array
    {
        if (is_string($platforms)) {
            return array_values(array_filter(preg_split('/[,\s]+/', $platforms)));
        }

        return (array) ($platforms ?? ['packagist.org', 'libraries.io']);
    }
}

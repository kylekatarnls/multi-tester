<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait Composer
{
    protected function getComposerProgram(array $settings): string
    {
        if (isset($settings['composer'])) {
            $version = $settings['composer'];

            if (!file_exists("composer-$version.phar")) {
                copy(
                    "https://getcomposer.org/download/$version/composer.phar",
                    "composer-$version.phar"
                );
            }

            return "composer-$version.phar";
        }

        return 'composer';
    }
}

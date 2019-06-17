<?php

namespace MultiTester;

class Color
{
    /**
     * @return bool
     */
    public static function isSupported()
    {
        // @codeCoverageIgnoreStart
        return
            DIRECTORY_SEPARATOR === '\\'
                ? false !== getenv('ANSICON') ||
                'ON' === getenv('ConEmuANSI') ||
                false !== getenv('BABUN_HOME')
                : (false !== getenv('BABUN_HOME')) ||
                (function_exists('posix_isatty') &&
                    @posix_isatty(STDOUT));
        // @codeCoverageIgnoreEnd
    }
}

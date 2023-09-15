<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait Settings
{
    /**
     * @var array|string|null Settings of the particular project.
     */
    protected $settings;

    public function getSettings(): array
    {
        $settings = $this->settings;

        switch ($settings) {
            case 'travis':
            case 'github':
                return [
                    'script'  => $settings,
                    'install' => $settings,
                ];
        }

        return is_array($settings) ? $settings : [];
    }

    protected function asArray(&$value): void
    {
        if (!is_array($value)) {
            $value = [$value];
        }
    }
}

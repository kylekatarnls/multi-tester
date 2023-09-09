<?php

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

        if ($settings === 'travis') {
            return [
                'script'  => 'travis',
                'install' => 'travis',
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

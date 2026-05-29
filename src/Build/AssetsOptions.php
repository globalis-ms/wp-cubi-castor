<?php

declare(strict_types=1);

namespace WpCubiCastor\Build;

/**
 * Asset build options — immutable value object.
 */
readonly class AssetsOptions
{
    public function __construct(
        public bool $disableMinify = false,
        public bool $skipStyles    = false,
        public bool $skipScripts   = false,
        public bool $skipImages    = false,
        public bool $skipFonts     = false,
    ) {}

    public function getFormat(): string
    {
        return $this->disableMinify ? 'normal' : 'minified';
    }
}

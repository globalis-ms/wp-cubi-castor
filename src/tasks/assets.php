<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use WpCubiCastor\Build\AssetsOptions;

// ---------------------------------------------------------------------------
// build:assets
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'build',
    name: 'assets',
    description: 'Compile SCSS, JS, images et fonts du thème',
)]
function build_assets(
    string $environment   = 'development',
    string $themeSlug     = '',
    bool   $disableMinify = false,
    bool   $skipStyles    = false,
    bool   $skipScripts   = false,
    bool   $skipImages    = false,
    bool   $skipFonts     = false,
): void {
    wcc_assets_builder($themeSlug)->buildAll(
        $environment,
        new AssetsOptions($disableMinify, $skipStyles, $skipScripts, $skipImages, $skipFonts),
    );
}

// ---------------------------------------------------------------------------
// theme:watch
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'theme',
    name: 'watch',
    description: 'Surveille les fichiers source et recompile à la volée',
)]
function theme_watch(
    string $environment   = 'development',
    string $themeSlug     = '',
    bool   $disableMinify = false,
    bool   $skipStyles    = false,
    bool   $skipScripts   = false,
    bool   $skipImages    = false,
    bool   $skipFonts     = false,
): void {
    wcc_assets_builder($themeSlug)->watch(
        $environment,
        new AssetsOptions($disableMinify, $skipStyles, $skipScripts, $skipImages, $skipFonts),
    );
}

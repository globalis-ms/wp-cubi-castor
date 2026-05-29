<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use function Castor\io;

// ---------------------------------------------------------------------------
// configure — environment configuration wizard
// ---------------------------------------------------------------------------

#[AsTask(
    name: 'configure',
    description: 'Configure interactivement les variables d\'environnement',
)]
function configure_env(string $environment = 'development', bool $onlyMissing = false): void
{
    $manager = wcc_config();

    if (!file_exists($manager->getConfigPath($environment))) {
        io()->section('CONFIGURATION DE L\'ENVIRONNEMENT');
        io()->text("Environnement : {$environment}");
    }

    $manager->configure($environment, $onlyMissing);
    io()->success("Configuration '{$environment}' sauvegardée.");
}

// ---------------------------------------------------------------------------
// build:all — full build
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'build',
    name: 'all',
    description: 'Build complet : composer, config, htaccess et assets',
)]
function build_all(
    string $environment    = 'development',
    bool   $ignoreAssets   = false,
    bool   $ignoreComposer = false,
    string $themeSlug      = '',
): void {
    wcc_builder()->buildAll(
        environment:    $environment,
        ignoreAssets:   $ignoreAssets,
        ignoreComposer: $ignoreComposer,
        assetsBuilder:  !$ignoreAssets ? wcc_assets_builder($themeSlug) : null,
    );
}

// ---------------------------------------------------------------------------
// build:composer
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'build',
    name: 'composer',
    description: 'Installe les dépendances Composer',
)]
function build_composer(string $environment = 'development'): void
{
    wcc_builder()->buildComposer($environment);
}

// ---------------------------------------------------------------------------
// build:config
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'build',
    name: 'config',
    description: 'Génère les fichiers de configuration',
)]
function build_config(string $environment = 'development'): void
{
    wcc_builder()->buildConfig($environment);
}

// ---------------------------------------------------------------------------
// build:htaccess
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'build',
    name: 'htaccess',
    description: 'Assemble le .htaccess depuis les fragments de config/htaccess/',
)]
function build_htaccess(string $environment = 'development'): void
{
    wcc_builder()->buildHtaccess($environment);
}

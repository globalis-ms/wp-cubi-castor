<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use function Castor\io;

// ---------------------------------------------------------------------------
// wp:generate-salt-keys
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'generate-salt-keys',
    description: 'Génère de nouvelles salt keys WordPress depuis l\'API officielle',
)]
function wp_generate_salt_keys(): void
{
    wcc_wp()->generateSaltKeys();
}

// ---------------------------------------------------------------------------
// wp:language:install
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'language:install',
    description: 'Installe et active une langue WordPress (core + plugins + thèmes)',
)]
function wp_language_install(?string $language = null, bool $activate = true): void
{
    wcc_wp()->installLanguage($language, $activate);
}

// ---------------------------------------------------------------------------
// wp:language:update
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'language:update',
    description: 'Met à jour les traductions WordPress (core + plugins + thèmes)',
)]
function wp_language_update(?string $language = null): void
{
    wcc_wp()->updateLanguage($language);
}

// ---------------------------------------------------------------------------
// wp:update-timezone
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'update-timezone',
    description: 'Configure la timezone WordPress de façon interactive',
)]
function wp_update_timezone(): void
{
    wcc_wp()->updateTimezone();
}

// ---------------------------------------------------------------------------
// wp:install-acf-pro
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'install-acf-pro',
    description: 'Installe ACF PRO via le dépôt Composer privé',
)]
function wp_install_acf_pro(string $username = '', string $password = ''): void
{
    wcc_wp()->installAcfPro($username, $password);
}

// ---------------------------------------------------------------------------
// wp:show-available-patch
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'show-available-patch',
    description: 'Affiche si une mise à jour patch WordPress core est disponible',
)]
function wp_show_available_patch(): void
{
    $version = wcc_wp()->getAvailablePatch();

    if ($version !== null) {
        io()->warning("Patch WordPress disponible : {$version}");
        io()->text('Appliquer avec : castor wp:apply-available-patch');
    } else {
        io()->success('WordPress core est à jour (aucun patch disponible).');
    }
}

// ---------------------------------------------------------------------------
// wp:apply-available-patch
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'wp',
    name: 'apply-available-patch',
    description: 'Applique la dernière mise à jour patch de WordPress core via Composer',
)]
function wp_apply_available_patch(): void
{
    wcc_wp()->applyAvailablePatch();
}

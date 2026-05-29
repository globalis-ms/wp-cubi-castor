<?php

declare(strict_types=1);

use WpCubiCastor\Build\AssetsBuilder;
use WpCubiCastor\Build\Builder;
use WpCubiCastor\Config\ConfigManager;
use WpCubiCastor\Deploy\Deployer;
use WpCubiCastor\Deploy\MediaSync;
use WpCubiCastor\Git\GitFlowManager;
use WpCubiCastor\Git\SemanticVersion;
use WpCubiCastor\Infrastructure\RsyncRunner;
use WpCubiCastor\Infrastructure\WpCli;
use WpCubiCastor\WordPress\WordPressManager;

/**
 * Returns the project root (where castor is invoked).
 */
function wcc_root(): string
{
    return getcwd();
}

/**
 * Shared factories — each call returns the same instance (lazy singleton).
 */
function wcc_config(): ConfigManager
{
    static $instance = null;
    return $instance ??= new ConfigManager(wcc_root());
}

function wcc_wp_cli(): WpCli
{
    static $instance = null;
    return $instance ??= new WpCli(wcc_root());
}

function wcc_rsync(): RsyncRunner
{
    static $instance = null;
    return $instance ??= new RsyncRunner();
}

function wcc_semver(): SemanticVersion
{
    return SemanticVersion::fromGitTags();
}

function wcc_git_flow(): GitFlowManager
{
    static $instance = null;
    return $instance ??= new GitFlowManager();
}

function wcc_builder(): Builder
{
    static $instance = null;
    return $instance ??= new Builder(wcc_config(), wcc_root());
}

function wcc_assets_builder(string $themeSlug = ''): AssetsBuilder
{
    static $instances = [];
    $instances[$themeSlug] ??= new AssetsBuilder(wcc_root(), $themeSlug);
    return $instances[$themeSlug];
}

function wcc_wp(): WordPressManager
{
    static $instance = null;
    return $instance ??= new WordPressManager(wcc_wp_cli(), wcc_config(), wcc_root());
}

function wcc_deployer(): Deployer
{
    static $instance = null;
    return $instance ??= new Deployer(wcc_config(), wcc_builder(), wcc_rsync(), wcc_wp(), wcc_root());
}

function wcc_media(): MediaSync
{
    static $instance = null;
    return $instance ??= new MediaSync(wcc_config(), wcc_rsync(), wcc_root());
}

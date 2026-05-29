<?php

declare(strict_types=1);

namespace WpCubiCastor\Build;

use WpCubiCastor\Config\ConfigManager;
use function Castor\io;
use function Castor\run;

/**
 * Orchestrates the project build (composer, config, htaccess).
 */
final class Builder
{
    public function __construct(
        private readonly ConfigManager $config,
        private readonly string        $projectRoot,
    ) {}

    /**
     * Full build: composer + config + htaccess + assets.
     */
    public function buildAll(
        string        $environment    = 'development',
        bool          $ignoreAssets   = false,
        bool          $ignoreComposer = false,
        ?AssetsBuilder $assetsBuilder = null,
        ?AssetsOptions $assetsOptions = null,
    ): void {
        if (!$ignoreComposer) {
            $this->buildComposer($environment);
        }

        $this->buildConfig($environment);
        $this->buildHtaccess($environment);

        if (!$ignoreAssets && $assetsBuilder !== null) {
            $assetsBuilder->buildAll($environment, $assetsOptions ?? new AssetsOptions());
        }
    }

    /**
     * Installs Composer dependencies.
     */
    public function buildComposer(string $environment = 'development'): void
    {
        $cmd = "composer install --working-dir={$this->projectRoot} --prefer-dist";

        if ($environment !== 'development') {
            $cmd .= ' --no-dev --optimize-autoloader';
        }

        run($cmd);
    }

    /**
     * Runs the configuration wizard and generates config files.
     */
    public function buildConfig(string $environment = 'development'): void
    {
        $configFile = $this->config->getConfigPath($environment);

        if (!file_exists($configFile)) {
            io()->section('CONFIGURATION DE L\'ENVIRONNEMENT');
            io()->text("Configuration pour l'environnement : {$environment}");
            io()->text("Sera sauvegardée dans : {$configFile}");
        }

        $this->config->configure($environment);

        // Copy vars.php → config/vars.php if absent
        $target = "{$this->projectRoot}/config/vars.php";
        if (!file_exists($target) && file_exists($configFile)) {
            @mkdir(dirname($target), 0755, true);
            copy($configFile, $target);
        }

        // Copy config/local.php from sample if absent
        $local  = "{$this->projectRoot}/config/local.php";
        $sample = "{$this->projectRoot}/config/local.php.sample";
        if (!file_exists($local) && file_exists($sample)) {
            copy($sample, $local);
        }
    }

    /**
     * Assembles .htaccess from fragments in config/htaccess/.
     *
     * Fragment convention in config/htaccess/:
     *   {part}-local         → takes priority
     *   {part}-{environment} → otherwise
     *   {part}               → generic fallback
     *
     * The <##VAR##> placeholders are replaced with config values.
     */
    public function buildHtaccess(
        string $environment      = 'development',
        string $startPlaceholder = '<##',
        string $endPlaceholder   = '##>',
    ): void {
        $buildPath = "{$this->projectRoot}/web/.htaccess";
        $configDir = "{$this->projectRoot}/config/htaccess";

        if (!is_dir($configDir)) {
            io()->warning("Dossier config/htaccess/ introuvable — .htaccess non généré.");
            return;
        }

        $fragments = $this->resolveHtaccessFragments($configDir, $environment);
        $htaccess  = implode(PHP_EOL . PHP_EOL, array_map('file_get_contents', $fragments));
        $htaccess  = $this->replacePlaceholders($htaccess, $environment, $startPlaceholder, $endPlaceholder);

        file_put_contents($buildPath, $htaccess);
        io()->success(".htaccess généré dans {$buildPath}");
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return list<string> Fragment paths in order */
    private function resolveHtaccessFragments(string $configDir, string $environment): array
    {
        $parts = array_values(array_filter(
            scandir($configDir) ?: [],
            fn(string $f) => $f[0] !== '.' && !str_contains($f, '-')
        ));

        $fragments = [];

        foreach ($parts as $part) {
            if (file_exists("{$configDir}/{$part}-local")) {
                $fragments[] = "{$configDir}/{$part}-local";
            } elseif (file_exists("{$configDir}/{$part}-{$environment}")) {
                $fragments[] = "{$configDir}/{$part}-{$environment}";
            } elseif (file_exists("{$configDir}/{$part}")) {
                $fragments[] = "{$configDir}/{$part}";
            }
        }

        return $fragments;
    }

    private function replacePlaceholders(
        string $content,
        string $environment,
        string $start,
        string $end,
    ): string {
        $config = $this->config->get($environment);

        if (!is_array($config)) {
            return $content;
        }

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $content = str_replace("{$start}{$key}{$end}", $value, $content);
            }
        }

        return $content;
    }
}

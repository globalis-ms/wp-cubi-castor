<?php

declare(strict_types=1);

namespace WpCubiCastor\Config;

use function Castor\io;

/**
 * Manages environment configuration.
 *
 * File convention:
 *   .castor/properties.php         — questions for the dev env
 *   .castor/properties-remote.php  — additional questions for remote envs
 *   .castor/vars.php               — generated config for dev
 *   .castor/vars-{env}.php         — generated config for {env}
 *
 * Example .castor/properties.php:
 *   return [
 *       'WEB_DOMAIN'  => ['question' => 'Domain (e.g. mysite.local)', 'default' => 'mysite.local'],
 *       'DB_NAME'     => ['question' => 'Database name', 'default' => 'wordpress'],
 *       'DB_PASSWORD' => ['question' => 'Database password', 'default' => '', 'hidden' => true],
 *   ];
 */
final class ConfigManager
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(private readonly string $projectRoot) {}

    /**
     * Returns the config file path for the given environment.
     */
    public function getConfigPath(string $environment = 'development'): string
    {
        return $environment === 'development'
            ? "{$this->projectRoot}/.castor/vars.php"
            : "{$this->projectRoot}/.castor/vars-{$environment}.php";
    }

    /**
     * Runs the interactive wizard and saves the result.
     *
     * @param bool $onlyMissing If true, only asks for missing values.
     * @return array<string, mixed> The full configuration.
     */
    public function configure(string $environment = 'development', bool $onlyMissing = false): array
    {
        $configFile = $this->getConfigPath($environment);
        $existing   = $this->loadFile($configFile);
        $properties = $this->loadProperties($environment);

        $config = $existing;

        foreach ($properties as $key => $prop) {
            if ($onlyMissing && array_key_exists($key, $config)) {
                continue;
            }

            $default    = $config[$key] ?? ($prop['default'] ?? '');
            $question   = $prop['question'] ?? $key;
            $isHidden   = (bool) ($prop['hidden'] ?? false);

            $config[$key] = $isHidden
                ? io()->askHidden($question)
                : io()->ask($question, is_string($default) ? $default : (string) $default);
        }

        $this->saveFile($configFile, $config);
        $this->cache[$environment] = $config;

        return $config;
    }

    /**
     * Returns the config for an environment (lazy + cached).
     * Runs interactive configuration if values are missing.
     *
     * @return array<string, mixed>|mixed
     */
    public function get(string $environment = 'development', ?string $key = null): mixed
    {
        if (!isset($this->cache[$environment])) {
            $this->cache[$environment] = $this->configure($environment, onlyMissing: true);
        }

        return $key !== null
            ? ($this->cache[$environment][$key] ?? null)
            : $this->cache[$environment];
    }

    /**
     * Clears the cache to force a reload.
     */
    public function invalidate(string $environment): void
    {
        unset($this->cache[$environment]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function loadFile(string $path): array
    {
        return file_exists($path) ? (require $path) : [];
    }

    /**
     * Loads the properties (questions) for the given environment.
     * @return array<string, array<string, mixed>>
     */
    private function loadProperties(string $environment): array
    {
        $devFile    = "{$this->projectRoot}/.castor/properties.php";
        $remoteFile = "{$this->projectRoot}/.castor/properties-remote.php";

        $properties = file_exists($devFile) ? (require $devFile) : [];

        if ($environment !== 'development' && file_exists($remoteFile)) {
            $properties = array_merge($properties, require $remoteFile);
        }

        return $properties;
    }

    /** @param array<string, mixed> $config */
    private function saveFile(string $path, array $config): void
    {
        @mkdir(dirname($path), 0755, true);
        file_put_contents(
            $path,
            '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL
        );
    }
}

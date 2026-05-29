<?php

declare(strict_types=1);

namespace WpCubiCastor\Build;

use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use function Castor\io;
use function Castor\watch;

/**
 * Compiles WordPress theme assets (SCSS, JS, images, fonts).
 *
 * - SCSS: scssphp + php-autoprefixer (no Node dependency)
 * - JS:   concatenation via _.map files + JShrink minification
 * - Images / Fonts: recursive copy to dist/
 */
final class AssetsBuilder
{
    private ?string $assetsVersion = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $themeSlug,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Compiles all assets.
     */
    public function buildAll(string $environment = 'development', AssetsOptions $options = new AssetsOptions()): void
    {
        if (!$options->skipStyles)  { $this->buildStyles($options->getFormat(), $environment); }
        if (!$options->skipScripts) { $this->buildScripts($options->getFormat()); }
        if (!$options->skipImages)  { $this->buildImages(); }
        if (!$options->skipFonts)   { $this->buildFonts(); }

        $this->writeVersionFile();
    }

    /**
     * Watches source files and recompiles on change.
     */
    public function watch(string $environment = 'development', AssetsOptions $options = new AssetsOptions()): void
    {
        $this->buildAll($environment, $options);

        $paths = $this->getWatchPaths($options);

        io()->text('Surveillance active. Ctrl+C pour arrêter.');

        watch($paths, function (string $changedPath) use ($environment, $options): void {
            io()->text("Modification : {$changedPath}");

            $map = [
                $this->getSrcDir('styles')  => fn() => $this->buildStyles($options->getFormat(), $environment),
                $this->getSrcDir('scripts') => fn() => $this->buildScripts($options->getFormat()),
                $this->getSrcDir('images')  => fn() => $this->buildImages(),
                $this->getSrcDir('fonts')   => fn() => $this->buildFonts(),
            ];

            foreach ($map as $prefix => $build) {
                if (str_starts_with($changedPath, $prefix)) {
                    ($build)();
                    break;
                }
            }

            $this->writeVersionFile();
        });
    }

    // -------------------------------------------------------------------------
    // SCSS
    // -------------------------------------------------------------------------

    public function buildStyles(string $format = 'minified', string $environment = 'development'): void
    {
        $src  = $this->getSrcDir('styles');
        $dest = $this->getDestDir('styles');

        $this->cleanDir($dest);

        $entries = array_filter(
            glob("{$src}/*.scss") ?: [],
            fn(string $f) => !str_starts_with(basename($f), '_')
        );

        if (empty($entries)) {
            io()->note("Aucun fichier SCSS d'entrée dans {$src}");
            return;
        }

        $outputStyle = $format === 'minified' ? OutputStyle::COMPRESSED : OutputStyle::EXPANDED;

        foreach ($entries as $scssFile) {
            $cssName  = basename($scssFile, '.scss') . '.css';
            $destFile = "{$dest}/{$cssName}";

            $scss = new Compiler();
            $scss->setImportPaths([$src]);
            $scss->setOutputStyle($outputStyle);

            if ($environment === 'development') {
                $scss->setSourceMap(Compiler::SOURCE_MAP_INLINE);
                $scss->setSourceMapOptions([
                    'sourceMapWriteTo'  => "{$dest}/" . str_replace('/', '_', $cssName) . '.map',
                    'sourceMapURL'      => str_replace('/', '_', $cssName) . '.map',
                    'sourceMapFilename' => $destFile,
                    'sourceMapBasepath' => $src,
                    'sourceRoot'        => '../../assets/styles/',
                ]);
            }

            // Inject the assets version variable into SCSS
            $code = sprintf('$assets-version: "%s";%s%s', $this->getAssetsVersion(), PHP_EOL, file_get_contents($scssFile));
            $css  = ($scss->compileString($code))->getCss();

            // Autoprefixer
            $css = (new Autoprefixer($css))->compile($format !== 'minified');

            file_put_contents($destFile, $css);
            io()->text("  SCSS → {$cssName}");
        }
    }

    // -------------------------------------------------------------------------
    // JavaScript
    // -------------------------------------------------------------------------

    public function buildScripts(string $format = 'minified'): void
    {
        $src  = $this->getSrcDir('scripts');
        $dest = $this->getDestDir('scripts');

        $this->cleanDir($dest);

        $maps = glob("{$src}/_*.map") ?: [];

        if (empty($maps)) {
            io()->note("Aucun fichier .map dans {$src} — scripts non compilés.");
            return;
        }

        foreach ($maps as $mapFile) {
            $bundleName = substr(basename($mapFile, '.map'), 1); // _main.map → main
            $destFile   = "{$dest}/{$bundleName}.js";

            preg_match_all('/[\w\-\/.]+\.js/', file_get_contents($mapFile) ?: '', $matches);
            $files = array_filter(
                $matches[0] ?? [],
                fn(string $f) => file_exists("{$src}/{$f}")
            );

            if (empty($files)) {
                continue;
            }

            $js = implode(PHP_EOL, array_map(
                fn(string $f) => file_get_contents("{$src}/{$f}"),
                $files
            ));

            if ($format === 'minified') {
                $js = \JShrink\Minifier::minify($js);
            }

            file_put_contents($destFile, $js);
            io()->text("  JS → {$bundleName}.js (" . count($files) . " fichiers)");
        }
    }

    // -------------------------------------------------------------------------
    // Images & Fonts
    // -------------------------------------------------------------------------

    public function buildImages(): void
    {
        $src  = $this->getSrcDir('images');
        $dest = $this->getDestDir('images');

        $this->cleanDir($dest);
        $this->copyDir($src, $dest);
        io()->text('  Images → dist/images/');
    }

    public function buildFonts(): void
    {
        $src  = $this->getSrcDir('fonts');
        $dest = $this->getDestDir('fonts');

        $this->cleanDir($dest);
        $this->copyDir($src, $dest);
        io()->text('  Fonts → dist/fonts/');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getThemeDir(): string
    {
        return "{$this->projectRoot}/web/app/themes/{$this->themeSlug}";
    }

    private function getSrcDir(string $type): string
    {
        return $this->getThemeDir() . '/assets/' . $type;
    }

    private function getDestDir(string $type): string
    {
        return $this->getThemeDir() . '/dist/' . $type;
    }

    /** @return list<string> */
    private function getWatchPaths(AssetsOptions $options): array
    {
        $paths = [];
        if (!$options->skipStyles)  { $paths[] = $this->getSrcDir('styles'); }
        if (!$options->skipScripts) { $paths[] = $this->getSrcDir('scripts'); }
        if (!$options->skipImages)  { $paths[] = $this->getSrcDir('images'); }
        if (!$options->skipFonts)   { $paths[] = $this->getSrcDir('fonts'); }
        return $paths;
    }

    private function getAssetsVersion(): string
    {
        return $this->assetsVersion ??= date('YmdHis');
    }

    private function writeVersionFile(): void
    {
        $destBase = $this->getThemeDir() . '/dist';
        @mkdir($destBase, 0755, true);
        file_put_contents("{$destBase}/version", $this->getAssetsVersion());
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }

    private function copyDir(string $src, string $dest): void
    {
        if (!is_dir($src)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            $target = $dest . DIRECTORY_SEPARATOR . $it->getSubPathname();
            $item->isDir() ? @mkdir($target, 0755, true) : copy($item->getPathname(), $target);
        }
    }
}

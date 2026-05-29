<?php

declare(strict_types=1);

namespace WpCubiCastor\Git;

use function Castor\run;

/**
 * Represents a semantic version (MAJOR.MINOR.PATCH).
 */
final class SemanticVersion implements \Stringable
{
    public const REGEX = '/^\d+\.\d+\.\d+$/';

    private function __construct(
        private readonly int $major,
        private readonly int $minor,
        private readonly int $patch,
    ) {}

    public static function fromString(string $version): self
    {
        if (!preg_match(self::REGEX, $version)) {
            throw new \InvalidArgumentException("Version invalide : {$version}. Format attendu : MAJOR.MINOR.PATCH");
        }

        [$major, $minor, $patch] = array_map('intval', explode('.', $version));
        return new self($major, $minor, $patch);
    }

    /**
     * Resolves the current version from Git tags (highest semver tag).
     * Returns 0.0.0 if no semver tag exists.
     */
    public static function fromGitTags(): self
    {
        $process = run('git tag', quiet: true, allowFailure: true);
        $tags    = array_filter(
            explode(PHP_EOL, trim($process->getOutput())),
            fn(string $tag) => (bool) preg_match(self::REGEX, $tag)
        );

        if (empty($tags)) {
            return new self(0, 0, 0);
        }

        usort($tags, 'version_compare');
        return self::fromString(end($tags));
    }

    /**
     * Returns a new instance with the given component incremented.
     *
     * @param 'major'|'minor'|'patch' $type
     */
    public function bump(string $type = 'patch'): self
    {
        return match ($type) {
            'major' => new self($this->major + 1, 0, 0),
            'minor' => new self($this->major, $this->minor + 1, 0),
            'patch' => new self($this->major, $this->minor, $this->patch + 1),
            default => throw new \InvalidArgumentException("Type d'incrément invalide : {$type}. Valeurs acceptées : major, minor, patch"),
        };
    }

    public function getMajor(): int { return $this->major; }
    public function getMinor(): int { return $this->minor; }
    public function getPatch(): int { return $this->patch; }

    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }
}

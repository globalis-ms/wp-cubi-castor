<?php

declare(strict_types=1);

namespace WpCubiCastor\Infrastructure;

/**
 * Immutable value object describing an rsync operation.
 */
readonly class RsyncOptions
{
    public function __construct(
        public ?string $fromHost,
        public ?string $fromUser,
        public string  $fromPath,
        public ?string $toHost,
        public ?string $toUser,
        public string  $toPath,
        public int     $port          = 22,
        public bool    $delete        = false,
        public ?string $chmod         = null,
        public ?string $excludeFrom   = null,
        public array   $extraExcludes = [],
        public bool    $dryRun        = false,
        public bool    $verbose       = true,
    ) {}

    public function withDryRun(bool $dryRun): self
    {
        return new self(
            fromHost:      $this->fromHost,
            fromUser:      $this->fromUser,
            fromPath:      $this->fromPath,
            toHost:        $this->toHost,
            toUser:        $this->toUser,
            toPath:        $this->toPath,
            port:          $this->port,
            delete:        $this->delete,
            chmod:         $this->chmod,
            excludeFrom:   $this->excludeFrom,
            extraExcludes: $this->extraExcludes,
            dryRun:        $dryRun,
            verbose:       $this->verbose,
        );
    }
}

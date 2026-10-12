<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter\Exporter;

use Vasilyevmichail\MmpConverter\MmpParser;

interface ExporterInterface
{
    /**
     * Export parsed project to a string.
     */
    public function exportToString(MmpParser $parser): string;

    /**
     * Export parsed project to a file.
     */
    public function exportToFile(MmpParser $parser, string $path): void;
}
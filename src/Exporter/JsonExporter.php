<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter\Exporter;

use Vasilyevmichail\MmpConverter\Exception\MmpException;
use Vasilyevmichail\MmpConverter\MmpParser;

final class JsonExporter implements ExporterInterface
{
    public function __construct(
        private readonly bool $prettyPrint = true,
        private readonly bool $unescapedUnicode = true,
        private readonly bool $unescapedSlashes = true,
    ) {
    }

    public function exportToString(MmpParser $parser): string
    {
        $tracks = [];
        foreach ($parser->getTracks() as $track) {
            $tracks[] = $track->toArray();
        }

        $result = [
            'source_file' => $parser->getSourceFile(),
            'parsed_at'   => date('c'),
            'track_count' => $parser->getTrackCount(),
            'tracks'      => $tracks,
        ];

        $flags = JSON_THROW_ON_ERROR;
        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        if ($this->unescapedUnicode) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }
        if ($this->unescapedSlashes) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        try {
            return json_encode($result, $flags);
        } catch (\JsonException $e) {
            throw new MmpException('JSON encoding error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function exportToFile(MmpParser $parser, string $path): void
    {
        $json = $this->exportToString($parser);

        if (file_put_contents($path, $json) === false) {
            throw new MmpException("Cannot write file: {$path}");
        }
    }
}
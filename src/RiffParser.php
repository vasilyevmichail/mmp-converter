<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter;

/**
 * Low-level RIFF chunk parser.
 */
final class RiffParser
{
    /**
     * Parse RIFF chunks in a binary buffer.
     *
     * @return array<int, array{id: string, data: string, children: ?array, listType?: string}>
     */
    public static function parse(string $data, int $offset = 0, ?int $limit = null): array
    {
        $chunks = [];
        $pos = $offset;
        $end = $limit ?? strlen($data);

        while ($pos + 8 <= $end) {
            $id = substr($data, $pos, 4);
            $size = unpack('V', substr($data, $pos + 4, 4))[1];
            $chunkData = substr($data, $pos + 8, $size);

            $chunk = ['id' => $id, 'data' => $chunkData, 'children' => null];

            if ($id === 'LIST' || $id === 'RIFF') {
                $listType = substr($chunkData, 0, 4);
                $chunk['listType'] = $listType;
                $chunk['children'] = self::parse($chunkData, 4, strlen($chunkData));
                if ($id === 'LIST') {
                    $chunk['id'] = $listType;
                }
            }

            $chunks[] = $chunk;
            $pos += 8 + $size + ($size % 2);
        }

        return $chunks;
    }

    /**
     * Recursively collect all chunks (flat list).
     *
     * @param array<int, array> $chunks
     * @param array<int, array> $out
     */
    public static function collectAll(array $chunks, array &$out): void
    {
        foreach ($chunks as $c) {
            $out[] = $c;
            if (!empty($c['children'])) {
                self::collectAll($c['children'], $out);
            }
        }
    }

    /**
     * Collect all chunks with given id.
     *
     * @param array<int, array> $chunks
     * @param array<int, array> $out
     */
    public static function collectById(array $chunks, string $id, array &$out): void
    {
        foreach ($chunks as $c) {
            if ($c['id'] === $id) {
                $out[] = $c;
            }
            if (!empty($c['children'])) {
                self::collectById($c['children'], $id, $out);
            }
        }
    }
}
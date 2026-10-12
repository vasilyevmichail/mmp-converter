<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter\Exporter;

use Vasilyevmichail\MmpConverter\Exception\MmpException;
use Vasilyevmichail\MmpConverter\MmpParser;
use Vasilyevmichail\MmpConverter\MmpTrack;

final class CsvExporter implements ExporterInterface
{
    private const HEADERS = [
        '#', 'track_number', 'file_name', 'file_path',
        'full_length', 'intro_anchor_in_mp3', 'outro_anchor_in_mp3',
        'trim_in', 'trim_out', 'fragment_length',
        'start_time', 'intro_anchor_timeline', 'outro_anchor_timeline',
        'full_length_sec', 'intro_anchor_in_mp3_sec', 'outro_anchor_in_mp3_sec',
        'trim_in_sec', 'trim_out_sec', 'fragment_sec',
        'start_time_sec', 'intro_anchor_timeline_sec', 'outro_anchor_timeline_sec',
    ];

    public function __construct(
        private readonly string $delimiter = ';',
        private readonly bool $writeBom = true,
    ) {
        if (strlen($this->delimiter) !== 1) {
            throw new \InvalidArgumentException('Delimiter must be a single character.');
        }
    }

    public function exportToString(MmpParser $parser): string
    {
        $fh = fopen('php://temp', 'r+b');
        if ($fh === false) {
            throw new MmpException('Cannot create temporary stream.');
        }

        if ($this->writeBom) {
            fwrite($fh, "\xEF\xBB\xBF");
        }

        fputcsv($fh, self::HEADERS, $this->delimiter);

        foreach ($parser->getTracks() as $i => $track) {
            fputcsv($fh, $this->trackRow($i + 1, $track), $this->delimiter);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }

    public function exportToFile(MmpParser $parser, string $path): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new MmpException("Cannot open file for writing: {$path}");
        }

        try {
            if ($this->writeBom) {
                fwrite($fh, "\xEF\xBB\xBF");
            }

            fputcsv($fh, self::HEADERS, $this->delimiter);

            foreach ($parser->getTracks() as $i => $track) {
                fputcsv($fh, $this->trackRow($i + 1, $track), $this->delimiter);
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function trackRow(int $index, MmpTrack $t): array
    {
        return [
            $index,
            $t->trackNumber,
            $t->fileName,
            $t->filePath,
            $t->fullLengthHms(),
            $t->introAnchorInMp3Hms(),
            $t->outroAnchorInMp3Hms(),
            $t->trimInHms(),
            $t->trimOutHms(),
            $t->fragmentHms(),
            $t->startTimeHms(),
            $t->introAnchorTimelineHms(),
            $t->outroAnchorTimelineHms(),
            round($t->fullLengthSec(), 3),
            round($t->introAnchorInMp3Sec(), 3),
            round($t->outroAnchorInMp3Sec(), 3),
            round($t->trimInSec(), 3),
            round($t->trimOutSec(), 3),
            round($t->fragmentSec(), 3),
            round($t->startTimeSec, 3),
            round($t->introAnchorTimelineSec, 3),
            round($t->outroAnchorTimelineSec, 3),
        ];
    }
}
<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter;

use Vasilyevmichail\MmpConverter\Exception\MmpException;
use Vasilyevmichail\MmpConverter\Util\TimeUtil;

/**
 * Main parser for MixMeister Fusion .mmp files.
 */
final class MmpParser
{
    /** @var array<int, array> */
    private array $topChunks = [];

    /** @var MmpTrack[] */
    private array $tracks = [];

    private string $sourceFile = '';

    /**
     * Parse a .mmp file from disk.
     *
     * @throws MmpException
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new MmpException("File not found: {$path}");
        }

        $data = file_get_contents($path);
        if ($data === false) {
            throw new MmpException("Cannot read file: {$path}");
        }

        $parser = new self();
        $parser->sourceFile = $path;
        $parser->parse($data);

        return $parser;
    }

    /**
     * Parse a .mmp file from a binary string.
     *
     * @throws MmpException
     */
    public static function fromString(string $data, string $sourceFile = ''): self
    {
        $parser = new self();
        $parser->sourceFile = $sourceFile;
        $parser->parse($data);

        return $parser;
    }

    /**
     * @throws MmpException
     */
    private function parse(string $data): void
    {
        $this->topChunks = RiffParser::parse($data, 0, strlen($data));

        $trkiList = [];
        RiffParser::collectById($this->topChunks, 'TRKI', $trkiList);

        if (empty($trkiList)) {
            throw new MmpException('No TRKI chunks found — is this a valid .mmp file?');
        }

        foreach ($trkiList as $i => $trki) {
            $this->tracks[] = $this->parseTrack($trki, $i + 1);
        }

        $this->computeTimeline();
    }

    private function parseTrack(array $trki, int $trackNumber): MmpTrack
    {
        $desc = [];
        RiffParser::collectAll($trki['children'] ?? [], $desc);

        $track = new MmpTrack();
        $track->trackNumber = $trackNumber;

        // --- TRKF: full path to mp3 ---
        foreach ($desc as $c) {
            if ($c['id'] === 'TRKF') {
                $track->filePath = TimeUtil::utf16leToUtf8($c['data']);
                break;
            }
        }
        $track->fileName = $track->filePath !== ''
            ? basename(str_replace('\\', '/', $track->filePath))
            : '';

        // --- TRKS: full length & trim in/out ---
        $trksAll = [];
        foreach ($desc as $c) {
            if ($c['id'] === 'TRKS') {
                $trksAll[] = $c;
            }
        }

        $track->fullLengthUs = isset($trksAll[0])
            ? $this->extractFullLength($trksAll[0]['data'])
            : 0;

        // --- TKLY: timeline layer (Intro/Outro anchors + trim in/out) ---
        $tklyList = [];
        foreach ($desc as $c) {
            if ($c['id'] === 'TKLY') {
                $tklyList[] = $c;
            }
        }

        $trimInUs = 0;
        $trimOutUs = 0;
        $introAnchorUs = 0;
        $outroAnchorUs = 0;

        foreach ($tklyList as $tkly) {
            $tklyDesc = [];
            RiffParser::collectAll($tkly['children'] ?? [], $tklyDesc);

            // Anchors from TRKM
            foreach ($tklyDesc as $c) {
                if ($c['id'] !== 'TRKM') {
                    continue;
                }

                $le = unpack('v', substr($c['data'], 4, 2))[1];
                $be = unpack('n', substr($c['data'], 4, 2))[1];

                if ($le === 1 || $be === 1) {
                    if ($introAnchorUs === 0) {
                        $introAnchorUs = unpack('V', substr($c['data'], 8, 4))[1];
                    }
                } elseif ($le === 2 || $be === 2) {
                    if ($outroAnchorUs === 0) {
                        $outroAnchorUs = unpack('V', substr($c['data'], 8, 4))[1];
                    }
                }
            }

            // Trim in/out from TRKS inside TKLY
            $tklyTrks = [];
            foreach ($tklyDesc as $c) {
                if ($c['id'] === 'TRKS') {
                    $tklyTrks[] = $c;
                }
            }

            if (isset($tklyTrks[0])) {
                $d = $tklyTrks[0]['data'];
                if (strlen($d) >= 12) {
                    $trimInUs = unpack('V', substr($d, 8, 4))[1];
                }
                if (strlen($d) >= 20) {
                    $trimOutUs = unpack('V', substr($d, 16, 4))[1];
                }
            }
        }

        // Fallback: try to get trim out from second TRKS
        if ($trimOutUs === 0 && isset($trksAll[1])) {
            $trimOutUs = $this->extractFullLength($trksAll[1]['data']);
        }

        $track->introAnchorInMp3Us = $introAnchorUs;
        $track->outroAnchorInMp3Us = $outroAnchorUs;
        $track->trimInUs = $trimInUs;
        $track->trimOutUs = $trimOutUs;

        return $track;
    }

    /**
     * Extract the largest plausible length value (µs) from a TRKS chunk.
     */
    private function extractFullLength(string $data): int
    {
        $best = 0;
        $len = strlen($data);

        for ($off = 0; $off + 4 <= $len; $off += 4) {
            $v = unpack('V', substr($data, $off, 4))[1];
            $sec = $v / TimeUtil::MICROSECONDS_PER_SECOND;

            if ($sec > 30 && $sec < 3 * 3600 && $v > $best) {
                $best = $v;
            }
        }

        return $best;
    }

    /**
     * Compute timeline positions for all tracks.
     *
     * Rules:
     *   - intro_anchor_timeline(track 1) = 0
     *   - intro_anchor_timeline(track N) = outro_anchor_timeline(track N-1)
     *   - start_time = intro_anchor_timeline - (intro_in_mp3 - trim_in)
     *   - outro_anchor_timeline = start_time + (outro_in_mp3 - trim_in)
     */
    private function computeTimeline(): void
    {
        $prevOutroTimeline = 0.0;

        foreach ($this->tracks as $i => $t) {
            $introInMp3 = $t->introAnchorInMp3Sec();
            $outroInMp3 = $t->outroAnchorInMp3Sec();
            $trimIn = $t->trimInSec();

            $introAnchorTimeline = ($i === 0) ? 0.0 : $prevOutroTimeline;

            $startTime = $introAnchorTimeline - ($introInMp3 - $trimIn);
            if ($startTime < 0) {
                $startTime = 0.0;
            }

            $outroAnchorTimeline = $startTime + ($outroInMp3 - $trimIn);

            $t->introAnchorTimelineSec = $introAnchorTimeline;
            $t->outroAnchorTimelineSec = $outroAnchorTimeline;
            $t->startTimeSec = $startTime;

            $prevOutroTimeline = $outroAnchorTimeline;
        }
    }

    /**
     * @return MmpTrack[]
     */
    public function getTracks(): array
    {
        return $this->tracks;
    }

    public function getTrack(int $index): ?MmpTrack
    {
        return $this->tracks[$index] ?? null;
    }

    public function getTrackCount(): int
    {
        return count($this->tracks);
    }

    public function getSourceFile(): string
    {
        return $this->sourceFile;
    }

    /**
     * @return array<int, array>
     */
    public function getTopChunks(): array
    {
        return $this->topChunks;
    }
}
<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter;

use Vasilyevmichail\MmpConverter\Util\TimeUtil;

/**
 * Represents a single parsed track from a MixMeister project.
 */
final class MmpTrack
{
    public int $trackNumber = 0;

    public string $filePath = '';
    public string $fileName = '';

    public int $fullLengthUs = 0;

    public int $introAnchorInMp3Us = 0;
    public int $outroAnchorInMp3Us = 0;

    public int $trimInUs = 0;
    public int $trimOutUs = 0;

    // Computed timeline values
    public float $introAnchorTimelineSec = 0.0;
    public float $outroAnchorTimelineSec = 0.0;
    public float $startTimeSec = 0.0;

    public function fullLengthSec(): float
    {
        return TimeUtil::usToSec($this->fullLengthUs);
    }

    public function introAnchorInMp3Sec(): float
    {
        return TimeUtil::usToSec($this->introAnchorInMp3Us);
    }

    public function outroAnchorInMp3Sec(): float
    {
        return TimeUtil::usToSec($this->outroAnchorInMp3Us);
    }

    public function trimInSec(): float
    {
        return TimeUtil::usToSec($this->trimInUs);
    }

    public function trimOutSec(): float
    {
        return TimeUtil::usToSec($this->trimOutUs);
    }

    public function fragmentSec(): float
    {
        return max(0.0, $this->trimOutSec() - $this->trimInSec());
    }

    public function fullLengthHms(): string
    {
        return TimeUtil::secToHms($this->fullLengthSec());
    }

    public function introAnchorInMp3Hms(): string
    {
        return TimeUtil::secToHms($this->introAnchorInMp3Sec());
    }

    public function outroAnchorInMp3Hms(): string
    {
        return TimeUtil::secToHms($this->outroAnchorInMp3Sec());
    }

    public function trimInHms(): string
    {
        return TimeUtil::secToHms($this->trimInSec());
    }

    public function trimOutHms(): string
    {
        return TimeUtil::secToHms($this->trimOutSec());
    }

    public function fragmentHms(): string
    {
        return TimeUtil::secToHms($this->fragmentSec());
    }

    public function introAnchorTimelineHms(): string
    {
        return TimeUtil::secToHms($this->introAnchorTimelineSec);
    }

    public function outroAnchorTimelineHms(): string
    {
        return TimeUtil::secToHms($this->outroAnchorTimelineSec);
    }

    public function startTimeHms(): string
    {
        return TimeUtil::secToHms($this->startTimeSec);
    }

    /**
     * Convert track to associative array (used by exporters).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'track_number'              => $this->trackNumber,
            'file_path'                 => $this->filePath,
            'file_name'                 => $this->fileName,
            'full_length_us'            => $this->fullLengthUs,
            'full_length_sec'           => round($this->fullLengthSec(), 3),
            'full_length_hms'           => $this->fullLengthHms(),
            'intro_anchor_in_mp3_us'    => $this->introAnchorInMp3Us,
            'intro_anchor_in_mp3_sec'   => round($this->introAnchorInMp3Sec(), 3),
            'intro_anchor_in_mp3_hms'   => $this->introAnchorInMp3Hms(),
            'outro_anchor_in_mp3_us'    => $this->outroAnchorInMp3Us,
            'outro_anchor_in_mp3_sec'   => round($this->outroAnchorInMp3Sec(), 3),
            'outro_anchor_in_mp3_hms'   => $this->outroAnchorInMp3Hms(),
            'trim_in_us'                => $this->trimInUs,
            'trim_in_sec'               => round($this->trimInSec(), 3),
            'trim_in_hms'               => $this->trimInHms(),
            'trim_out_us'               => $this->trimOutUs,
            'trim_out_sec'              => round($this->trimOutSec(), 3),
            'trim_out_hms'              => $this->trimOutHms(),
            'fragment_sec'              => round($this->fragmentSec(), 3),
            'fragment_hms'              => $this->fragmentHms(),
            'intro_anchor_timeline_sec' => round($this->introAnchorTimelineSec, 3),
            'intro_anchor_timeline_hms' => $this->introAnchorTimelineHms(),
            'outro_anchor_timeline_sec' => round($this->outroAnchorTimelineSec, 3),
            'outro_anchor_timeline_hms' => $this->outroAnchorTimelineHms(),
            'start_time_sec'            => round($this->startTimeSec, 3),
            'start_time_hms'            => $this->startTimeHms(),
        ];
    }
}
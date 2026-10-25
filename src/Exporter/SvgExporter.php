<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter\Exporter;

use Vasilyevmichail\MmpConverter\Exception\MmpException;
use Vasilyevmichail\MmpConverter\MmpParser;
use Vasilyevmichail\MmpConverter\MmpTrack;
use Vasilyevmichail\MmpConverter\Util\TimeUtil;

/**
 * Renders a visual timeline of the mix as an SVG image.
 */
final class SvgExporter implements ExporterInterface
{
    private const COLORS = [
        '#4e79a7', '#f28e2b', '#e15759', '#76b7b2',
        '#59a14f', '#edc948', '#b07aa1', '#ff9da7',
        '#9c755f', '#bab0ac',
    ];

    public function __construct(
        private readonly int $rowHeight = 90,
        private readonly int $rowGap = 20,
        private readonly int $leftMargin = 120,
        private readonly int $rightMargin = 40,
        private readonly int $topMargin = 60,
        private readonly int $bottomMargin = 120,
        private readonly float $pxPerSec = 60.0,
        private readonly int $maxWidth = 2000,
    ) {
    }

    public function exportToString(MmpParser $parser): string
    {
        $tracks = $parser->getTracks();
        if (empty($tracks)) {
            throw new MmpException('No tracks to render.');
        }

        // --- Compute layout ---
        $totalEnd = 0.0;
        foreach ($tracks as $t) {
            $end = $t->startTimeSec + $t->fragmentSec();
            if ($end > $totalEnd) {
                $totalEnd = $end;
            }
        }
        if ($totalEnd <= 0) {
            $totalEnd = 1.0;
        }

        $pxPerSec = $this->pxPerSec;
        if ($totalEnd * $pxPerSec > $this->maxWidth) {
            $pxPerSec = $this->maxWidth / $totalEnd;
        }

        $timelineWidth = $totalEnd * $pxPerSec;
        $svgWidth = (int) ceil($this->leftMargin + $timelineWidth + $this->rightMargin);
        $svgHeight = $this->topMargin
            + count($tracks) * ($this->rowHeight + $this->rowGap)
            + $this->bottomMargin;

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">' . "\n",
            $svgWidth, $svgHeight, $svgWidth, $svgHeight
        );
        $svg .= '<style>
            .title { font: bold 16px sans-serif; fill: #222; }
            .track-label { font: 12px sans-serif; fill: #222; }
            .track-name { font: bold 12px sans-serif; fill: #fff; }
            .track-meta { font: normal 10px sans-serif; fill: #fff; }
            .tick-label { font: 10px sans-serif; fill: #666; }
            .legend { font: 12px sans-serif; fill: #222; }
        </style>' . "\n";

        $svg .= sprintf('<rect x="0" y="0" width="%d" height="%d" fill="#fafafa"/>' . "\n", $svgWidth, $svgHeight);

        $svg .= sprintf(
            '<text x="%d" y="25" class="title">Timeline: %d tracks, total %.3f sec</text>' . "\n",
            $this->leftMargin, count($tracks), $totalEnd
        );

        // --- Time grid ---
        $stepCandidates = [1, 2, 5, 10, 15, 30, 60, 120, 300];
        $step = 60;
        foreach ($stepCandidates as $c) {
            if ($totalEnd / $c <= 20) {
                $step = $c;
                break;
            }
        }

        for ($t = 0.0; $t <= $totalEnd; $t += $step) {
            $x = $this->leftMargin + $t * $pxPerSec;
            $svg .= sprintf(
                '<line x1="%.2f" y1="%d" x2="%.2f" y2="%d" stroke="#ddd" stroke-width="1"/>' . "\n",
                $x, $this->topMargin - 10, $x, $svgHeight - $this->bottomMargin + 10
            );
            $svg .= sprintf(
                '<text x="%.2f" y="%d" class="tick-label" text-anchor="middle">%s</text>' . "\n",
                $x, $this->topMargin - 15, TimeUtil::secToHms((float) $t)
            );
        }

        // --- Track rows ---
        $y = $this->topMargin;
        foreach ($tracks as $i => $t) {
            $color = self::COLORS[$i % count(self::COLORS)];

            $svg .= sprintf(
                '<text x="10" y="%d" class="track-label">#%d</text>' . "\n",
                (int) ($y + $this->rowHeight / 2 + 4), $t->trackNumber
            );

            $x1 = $this->leftMargin + $t->startTimeSec * $pxPerSec;
            $x2 = $this->leftMargin + ($t->startTimeSec + $t->fragmentSec()) * $pxPerSec;
            $w = max(1.0, $x2 - $x1);

            $svg .= sprintf(
                '<rect x="%.2f" y="%d" width="%.2f" height="%d" fill="%s" rx="4" ry="4"/>' . "\n",
                $x1, $y, $w, $this->rowHeight, $color
            );

            $name = $t->fileName;
            if (mb_strlen($name) > 40) {
                $name = mb_substr($name, 0, 37) . '...';
            }
            $svg .= sprintf(
                '<text x="%.2f" y="%d" class="track-name">%s</text>' . "\n",
                $x1 + 6, $y + 18,
                htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8')
            );

            // --- Start / End ---
            $svg .= sprintf(
                '<text x="%.2f" y="%d" class="track-meta">start=%s  end=%s</text>' . "\n",
                $x1 + 6, $y + 36,
                TimeUtil::secToHms($t->startTimeSec),
                TimeUtil::secToHms($t->startTimeSec + $t->fragmentSec())
            );

            // --- Trim In / Trim Out на отдельной строке ---
            $svg .= sprintf(
                '<text x="%.2f" y="%d" class="track-meta">trim_in=%s  trim_out=%s</text>' . "\n",
                $x1 + 6, $y + 54,
                $t->trimInHms(),
                $t->trimOutHms()
            );

            // Intro anchor
            $introX = $this->leftMargin + $t->introAnchorTimelineSec * $pxPerSec;
            $svg .= sprintf(
                '<line x1="%.2f" y1="%d" x2="%.2f" y2="%d" stroke="#1565c0" stroke-width="2"/>' . "\n",
                $introX, $y - 5, $introX, $y + $this->rowHeight + 5
            );

            // Outro anchor
            $outroX = $this->leftMargin + $t->outroAnchorTimelineSec * $pxPerSec;
            $svg .= sprintf(
                '<line x1="%.2f" y1="%d" x2="%.2f" y2="%d" stroke="#c62828" stroke-width="2"/>' . "\n",
                $outroX, $y - 5, $outroX, $y + $this->rowHeight + 5
            );

            // Trim in
            $trimInX = $this->leftMargin + $t->startTimeSec * $pxPerSec;
            $svg .= sprintf(
                '<line x1="%.2f" y1="%d" x2="%.2f" y2="%d" stroke="#2e7d32" stroke-width="1.5" stroke-dasharray="4,3"/>' . "\n",
                $trimInX, $y + $this->rowHeight - 15, $trimInX, $y + $this->rowHeight
            );

            // Trim out
            $trimOutX = $this->leftMargin + ($t->startTimeSec + $t->fragmentSec()) * $pxPerSec;
            $svg .= sprintf(
                '<line x1="%.2f" y1="%d" x2="%.2f" y2="%d" stroke="#ef6c00" stroke-width="1.5" stroke-dasharray="4,3"/>' . "\n",
                $trimOutX, $y + $this->rowHeight - 15, $trimOutX, $y + $this->rowHeight
            );

            $y += $this->rowHeight + $this->rowGap;
        }

        // --- Legend ---
        $legendY = $svgHeight - $this->bottomMargin + 20;
        $legendItems = [
            ['color' => '#1565c0', 'label' => 'Intro Anchor (timeline)'],
            ['color' => '#c62828', 'label' => 'Outro Anchor (timeline)'],
            ['color' => '#2e7d32', 'label' => 'Trim In (start of fragment)'],
            ['color' => '#ef6c00', 'label' => 'Trim Out (end of fragment)'],
        ];

        $lx = $this->leftMargin;
        foreach ($legendItems as $item) {
            $svg .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="3"/>' . "\n",
                $lx, $legendY, $lx + 30, $legendY, $item['color']
            );
            $svg .= sprintf(
                '<text x="%d" y="%d" class="legend">%s</text>' . "\n",
                $lx + 40, $legendY + 4,
                htmlspecialchars($item['label'], ENT_QUOTES | ENT_XML1, 'UTF-8')
            );
            $lx += 260;
        }

        $svg .= '</svg>' . "\n";

        return $svg;
    }

    public function exportToFile(MmpParser $parser, string $path): void
    {
        $svg = $this->exportToString($parser);

        if (file_put_contents($path, $svg) === false) {
            throw new MmpException("Cannot write SVG file: {$path}");
        }
    }
}
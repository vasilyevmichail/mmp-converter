
# MmpConverter

[![PHP Version](<https://img.shields.io/badge/php-%3E%3D8.2-blue.svg>)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A PHP library for parsing  **MixMeister Fusion** project files (`.mmp`) and converting them to  **JSON**, **CSV** или **SVG** (a visual mix timeline)).

The `.mmp` — format is a proprietary RIFF container that contains no audio data: only references to mp3 files and mix parameters. The structure was reverse-engineered experimentally on MixMeister Fusion version **7.4.4**.

---

## Возможности

- Parsing of the .mmp RIFF structure (nested LIST chunks, TRKI, TKLY, TRKS, TRKM)
- Extraction for each track of:

  - the full path to the mp3 (TRKF, UTF-16LE → UTF-8)
  - the full mp3 length
  - Intro Anchor / Outro Anchor within the mp3
  - Trim In / Trim Out
  - the fragment length
- Computation of timeline fields:
  - Intro Anchor / Outro Anchor on the timeline
  - Start Time on the timeline
- Export to JSON, CSV, and SVG (a visual layout of the tracks)
- mmp2json CLI utility
- PSR-4, strict_types=1, PHP 8.2+

---

## Requirements

- PHP **8.2+**
- `mbstring`, `json`

---

## Installation

```bash
composer require vasilyevmichail/mmp-converter
```

Or locally from source:

```bash
git clone https://github.com/vasilyevmichail/MmpConverter.git
cd MmpConverter
composer install
```

---

## Usage

### CLI

```bash
# JSON рядом с .mmp
vendor/bin/mmp2json mix.mmp

# JSON в указанный файл
vendor/bin/mmp2json mix.mmp out.json

# CSV с разделителем ;
vendor/bin/mmp2json mix.mmp --format=csv

# CSV с кастомным разделителем
vendor/bin/mmp2json mix.mmp out.csv --format=csv --delimiter=,

# SVG таймлайн
vendor/bin/mmp2json mix.mmp timeline.svg --format=svg

# Подавить вывод сводки
vendor/bin/mmp2json mix.mmp --quiet
```

### Library

```php
use Vasilyevmichail\MmpConverter\MmpParser;
use Vasilyevmichail\MmpConverter\Exporter\JsonExporter;
use Vasilyevmichail\MmpConverter\Exporter\CsvExporter;
use Vasilyevmichail\MmpConverter\Exporter\SvgExporter;

$parser = MmpParser::fromFile('/path/to/mix.mmp');

// JSON
(new JsonExporter())->exportToFile($parser, 'mix.json');

// CSV
(new CsvExporter(delimiter: ';'))->exportToFile($parser, 'mix.csv');

// SVG
(new SvgExporter())->exportToFile($parser, 'timeline.svg');

// Доступ к трекам
foreach ($parser->getTracks() as $track) {
    printf(
        "#%d  %s  start=%s  frag=%s\n",
        $track->trackNumber,
        $track->fileName,
        $track->startTimeHms(),
        $track->fragmentHms()
    );
}
```

You can also parse a string already loaded into memory:

```php
$data = file_get_contents('mix.mmp');
$parser = MmpParser::fromString($data, 'mix.mmp');
```

---

## JSON Structure

```json
{
  "source_file": "/path/to/mix.mmp",
  "parsed_at": "2024-01-01T12:00:00+00:00",
  "track_count": 3,
  "tracks": [
    {
      "track_number": 1,
      "file_path": "C:\\Music\\track1.mp3",
      "file_name": "track1.mp3",
      "full_length_us": 229057000,
      "full_length_sec": 229.057,
      "full_length_hms": "0:03:49.057",
      "intro_anchor_in_mp3_sec": 0.962,
      "intro_anchor_in_mp3_hms": "0:00:00.962",
      "outro_anchor_in_mp3_sec": 27.093,
      "outro_anchor_in_mp3_hms": "0:00:27.093",
      "trim_in_sec": 0.0,
      "trim_in_hms": "0:00:00.000",
      "trim_out_sec": 60.151,
      "trim_out_hms": "0:01:00.151",
      "fragment_sec": 60.151,
      "fragment_hms": "0:01:00.151",
      "intro_anchor_timeline_sec": 0.0,
      "intro_anchor_timeline_hms": "0:00:00.000",
      "outro_anchor_timeline_sec": 27.093,
      "outro_anchor_timeline_hms": "0:00:27.093",
      "start_time_sec": 0.0,
      "start_time_hms": "0:00:00.000"
    }
  ]
}
```

---

## Формулы

Дано (из `.mmp`):

- `full_length` —  the full mp3 length
- `intro_anchor_in_mp3` — Intro Anchor within the mp3
- `outro_anchor_in_mp3` — Outro Anchor within the mp3
- `trim_in` — the playback start point
- `trim_out` — the playback end point

Computed:

```
fragment_length = trim_out - trim_in

For the first track:
    intro_anchor_timeline = 0

For track N > 1:
    intro_anchor_timeline = outro_anchor_timeline(N-1)

In general:
    start_time            = intro_anchor_timeline - (intro_anchor_in_mp3 - trim_in)
    outro_anchor_timeline = start_time + (outro_anchor_in_mp3 - trim_in)
    end_time              = start_time + fragment_length
```

If `start_time` turns out negative, it is clamped to `0`.

---

## Structure `.mmp`

```
RIFF
└── MXMPLIST
    ├── TRKLLIST
    │   ├── TRKI (track 1)
    │   │   ├── TRKH (header)
    │   │   ├── TRKF (path to mp3, UTF-16LE)
    │   │   ├── TRSI / TRSO (Beatmix strings)
    │   │   ├── TKTMTKTH (LIST: tempo map)
    │   │   ├── TKEL (LIST, usually empty)
    │   │   └── TKLY (LIST: Intro/Outro Anchor + Trim In/Out)
    │   ├── TRKI (track 2)
    │   └── ...
    ├── GLBL
    └── MMVR (version MixMeister)
```

All numbers are little-endian; times are in microseconds (`1 sec = 1_000_000`).

---

## Limitations

- The .mmp format is not officially documented — the structure was obtained experimentally.
- There may be differences between MixMeister Fusion versions.
- BPM and layers are not stored in plain form in the .mmp — they are not extracted.
- Start Time is not stored in the .mmp — it is computed by formula; discrepancies with the program are within 1–2 seconds.
- The byte order of the 0x8000 flag in TRKM may differ (LE/BE) — the parser checks both variants.
- The library does not check whether the source mp3 files exist on disk.

---

## Testing

```bash
composer test
```

---

## License

MIT. См. [LICENSE](LICENSE).

---

## Автор

**Vasilyev Mikhail** — [github.com/vasilyevmichail](https://github.com/vasilyevmichail)

The `.mmp` format is owned by MixMeister. All conclusions about the structure were obtained by analyzing hex dumps.

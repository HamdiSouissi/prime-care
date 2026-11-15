<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure PHP server-side ISO/IEC 18004 QR Code SVG generator (Byte mode, ECC Level L, Versions 1..6).
 * Generates an inline SVG QR code with zero external dependencies, zero CDN, and zero inline styles.
 */
final class QrCodeService
{
    /**
     * Capacities in bytes for ECC Level L (Versions 1..6) and ECC codeword counts.
     *
     * @var array<int, array{size: int, data_bytes: int, ec_bytes: int, format_bits: int}>
     */
    private const VERSIONS = [
        1 => ['size' => 21, 'data_bytes' => 19,  'ec_bytes' => 7,  'format_bits' => 0x77C4],
        2 => ['size' => 25, 'data_bytes' => 34,  'ec_bytes' => 10, 'format_bits' => 0x77C4],
        3 => ['size' => 29, 'data_bytes' => 55,  'ec_bytes' => 15, 'format_bits' => 0x77C4],
        4 => ['size' => 33, 'data_bytes' => 80,  'ec_bytes' => 20, 'format_bits' => 0x77C4],
        5 => ['size' => 37, 'data_bytes' => 108, 'ec_bytes' => 26, 'format_bits' => 0x77C4],
        6 => ['size' => 41, 'data_bytes' => 136, 'ec_bytes' => 36, 'format_bits' => 0x77C4],
    ];

    /**
     * Alignment pattern center coordinates for Versions 2..6.
     *
     * @var array<int, array<int, int>>
     */
    private const ALIGNMENT_POS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
    ];

    /**
     * Generate a clean, accessible SVG QR code for the given URL or text.
     */
    public static function renderSvg(string $text, int $pixelSize = 220): string
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len = count($bytes);

        $version = 4;
        foreach (self::VERSIONS as $v => $cfg) {
            // Byte mode overhead: 4 bits mode + 8 bits length + 4 bits terminator = 2 bytes
            if ($len + 2 <= $cfg['data_bytes']) {
                $version = $v;
                break;
            }
        }
        if ($len + 2 > self::VERSIONS[$version]['data_bytes']) {
            $version = 6;
            $bytes = array_slice($bytes, 0, self::VERSIONS[6]['data_bytes'] - 2);
            $len = count($bytes);
        }

        $cfg = self::VERSIONS[$version];
        $size = $cfg['size'];

        // 1. Build bit stream: mode 0100 (byte), 8-bit length, payload, 0000 terminator, pad bytes 0xEC/0x11
        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $maxBits = $cfg['data_bytes'] * 8;
        $bits .= str_repeat('0', min(4, max(0, $maxBits - strlen($bits))));
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }

        $dataCodewords = [];
        for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }
        $padByteToggle = true;
        while (count($dataCodewords) < $cfg['data_bytes']) {
            $dataCodewords[] = $padByteToggle ? 0xEC : 0x11;
            $padByteToggle = !$padByteToggle;
        }

        // 2. Reed-Solomon ECC codewords
        $ecCodewords = self::computeReedSolomon($dataCodewords, $cfg['ec_bytes']);
        $allCodewords = array_merge($dataCodewords, $ecCodewords);

        // 3. Initialize matrix & reserved mask
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns + separators
        self::placeFinderPattern($matrix, $reserved, 0, 0, $size);
        self::placeFinderPattern($matrix, $reserved, $size - 7, 0, $size);
        self::placeFinderPattern($matrix, $reserved, 0, $size - 7, $size);

        // Alignment patterns
        $alignCenters = self::ALIGNMENT_POS[$version];
        foreach ($alignCenters as $cy) {
            foreach ($alignCenters as $cx) {
                if ($reserved[$cy][$cx]) {
                    continue;
                }
                self::placeAlignmentPattern($matrix, $reserved, $cx, $cy);
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $bit;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $bit;
                $reserved[$i][6] = true;
            }
        }

        // Dark module & reserve format info areas
        $matrix[$size - 8][8] = 1;
        $reserved[$size - 8][8] = true;
        for ($i = 0; $i < 9; $i++) {
            if ($i < $size) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // 4. Place data bits in upward/downward 2-column zig-zag with Mask 0 ((r + c) % 2 === 0)
        $bitStream = '';
        foreach ($allCodewords as $cw) {
            $bitStream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $bitIdx = 0;
        $bitTotal = strlen($bitStream);
        $upward = true;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? ($size - 1 - $step) : $step;
                for ($dx = 0; $dx < 2; $dx++) {
                    $c = $col - $dx;
                    if (!$reserved[$row][$c]) {
                        $bit = $bitIdx < $bitTotal ? (int) $bitStream[$bitIdx++] : 0;
                        if (($row + $c) % 2 === 0) {
                            $bit ^= 1;
                        }
                        $matrix[$row][$c] = $bit;
                    }
                }
            }
            $upward = !$upward;
        }

        // 5. Place Format Information for ECC Level L (01) + Mask 0 (000) -> 0x77C4
        $formatBits = $cfg['format_bits'];
        $fCoords1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        $fCoords2 = [
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8],
            [$size - 5, 8], [$size - 6, 8], [$size - 7, 8],
            [8, $size - 8], [8, $size - 7], [8, $size - 6], [8, $size - 5],
            [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1],
        ];
        for ($i = 0; $i < 15; $i++) {
            $b = ($formatBits >> $i) & 1;
            [$r1, $c1] = $fCoords1[$i];
            [$r2, $c2] = $fCoords2[$i];
            $matrix[$r1][$c1] = $b;
            $matrix[$r2][$c2] = $b;
        }

        // 6. Build single SVG path with quiet zone = 4 modules
        $quiet = 3;
        $totalModules = $size + $quiet * 2;
        $pathD = '';
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $quiet;
                    $y = $r + $quiet;
                    $pathD .= "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        return sprintf(
            '<svg class="pc-qr-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="QR code d’installation Prime Care">'
            . '<rect width="%d" height="%d" rx="3" fill="#FFFFFF"/>'
            . '<path d="%s" fill="#0A1633"/>'
            . '</svg>',
            $totalModules,
            $totalModules,
            $pixelSize,
            $pixelSize,
            $totalModules,
            $totalModules,
            $pathD
        );
    }

    /**
     * @param array<int, array<int, int>> $matrix
     * @param array<int, array<int, bool>> $reserved
     */
    private static function placeFinderPattern(array &$matrix, array &$reserved, int $ox, int $oy, int $size): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $x = $ox + $dx;
                $y = $oy + $dy;
                if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
                    continue;
                }
                $inOuter = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6)
                    && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6);
                $inInner = ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
                $matrix[$y][$x] = ($inOuter || $inInner) ? 1 : 0;
                $reserved[$y][$x] = true;
            }
        }
    }

    /**
     * @param array<int, array<int, int>> $matrix
     * @param array<int, array<int, bool>> $reserved
     */
    private static function placeAlignmentPattern(array &$matrix, array &$reserved, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                $isBorder = abs($dx) === 2 || abs($dy) === 2;
                $isCenter = $dx === 0 && $dy === 0;
                $matrix[$y][$x] = ($isBorder || $isCenter) ? 1 : 0;
                $reserved[$y][$x] = true;
            }
        }
    }

    /**
     * Compute Reed-Solomon error correction codewords over GF(256) with primitive polynomial 0x11D.
     *
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private static function computeReedSolomon(array $data, int $ecCount): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        $gfMul = static function (int $a, int $b) use ($exp, $log): int {
            if ($a === 0 || $b === 0) {
                return 0;
            }
            return $exp[$log[$a] + $log[$b]];
        };

        // Build generator polynomial
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0, $n = count($gen); $j < $n; $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j + 1] ^= $gfMul($gen[$j], $exp[$i]);
            }
            $gen = $next;
        }

        $rem = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                for ($j = 0; $j < $ecCount; $j++) {
                    $rem[$j] ^= $gfMul($gen[$j + 1], $factor);
                }
            }
        }

        return $rem;
    }
}

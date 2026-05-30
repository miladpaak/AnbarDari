<?php
final class Barcode
{
    private const CODES = [
        '0' => 'bwbWBwBwb', '1' => 'BwbWbwbwB', '2' => 'bwBWbwbwB', '3' => 'BwBWbwbwb',
        '4' => 'bwbWBwbwB', '5' => 'BwbWBwbwb', '6' => 'bwBWBwbwb', '7' => 'bwbWbwBwB',
        '8' => 'BwbWbwBwb', '9' => 'bwBWbwBwb', 'A' => 'BwbwbWbwB', 'B' => 'bwBwbWbwB',
        'C' => 'BwBwbWbwb', 'D' => 'bwbwBWbwB', 'E' => 'BwbwBWbwb', 'F' => 'bwBwBWbwb',
        'G' => 'bwbwbWBwB', 'H' => 'BwbwbWBwb', 'I' => 'bwBwbWBwb', 'J' => 'bwbwBWBwb',
        'K' => 'BwbwbwbWB', 'L' => 'bwBwbwbWB', 'M' => 'BwBwbwbWb', 'N' => 'bwbwBwbWB',
        'O' => 'BwbwBwbWb', 'P' => 'bwBwBwbWb', 'Q' => 'bwbwbwBWB', 'R' => 'BwbwbwBWb',
        'S' => 'bwBwbwBWb', 'T' => 'bwbwBwBWb', 'U' => 'BWbwbwbwB', 'V' => 'bWBwbwbwB',
        'W' => 'BWBwbwbwb', 'X' => 'bWbwBwbwB', 'Y' => 'BWbwBwbwb', 'Z' => 'bWBwBwbwb',
        '-' => 'bWbwbwBwB', '.' => 'BWbwbwBwb', ' ' => 'bWBwbwBwb', '$' => 'bWbWbWbwb',
        '/' => 'bWbWbwbWb', '+' => 'bWbwbWbWb', '%' => 'bwbWbWbWb', '*' => 'bWbwBwBwb',
    ];

    public static function svg(string $text, int $height = 70): string
    {
        $text = strtoupper(preg_replace('/[^A-Z0-9\-\. \$\/\+%]/', '-', $text));
        $encoded = '*' . $text . '*';
        $x = 10;
        $bars = '';
        foreach (str_split($encoded) as $char) {
            $pattern = self::CODES[$char] ?? self::CODES['-'];
            foreach (str_split($pattern) as $i => $part) {
                $wide = strtoupper($part) === $part;
                $width = $wide ? 3 : 1;
                if ($i % 2 === 0) {
                    $bars .= '<rect x="' . $x . '" y="10" width="' . $width . '" height="' . $height . '" />';
                }
                $x += $width;
            }
            $x += 1;
        }
        $total = $x + 10;
        return '<svg class="barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . ($height + 38) . '" role="img" aria-label="' . e($text) . '"><g fill="#111">' . $bars . '</g><text x="50%" y="' . ($height + 28) . '" text-anchor="middle" font-family="monospace" font-size="14">' . e($text) . '</text></svg>';
    }
}

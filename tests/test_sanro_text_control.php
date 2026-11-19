<?php
// Standalone regression: multiline text is allowed, control bytes are not.
$pattern = '~[\x00-\x08\x0B\x0C\x0E-\x1F]~';
$cases = [
    'normal' => ['Halo dunia', 0],
    'multiline' => ["Halo\nDunia", 0],
    'tab' => ["Halo\tDunia", 0],
    'nul' => ["Halo\0Dunia", 1],
    'unit_separator' => ["Halo\x1fDunia", 1],
    'literal_x' => ['xHello', 0],
];
$count = 0;
foreach ($cases as $name => [$text, $expected]) {
    if (preg_match($pattern, $text) !== $expected) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $count++;
}
echo "SANRO text control bytes: {$count} assertions PASS, 0 FAIL\n";

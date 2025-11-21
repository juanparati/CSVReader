<?php
/**
 * Benchmarks preparation.
 *
 * Download CSV files required for benchmarks.
 */

echo "Downloading sample CSV file...\n";
$fileName = __DIR__ . '/tmp/sample.csv';
@unlink($fileName);

$fpSrc = fopen('https://drive.usercontent.google.com/download?id=18BLAZDeH74Ll3b4GsMNY3s-YVnNmWblC&export=download&authuser=1&confirm=t&uuid=04f2a295-306a-44bd-b825-53dce40000c8&at=ALWLOp4uF4hUNFO-d5OFxI5tiIR2%3A1763451843535', 'r');
$fpDst = fopen($fileName, 'w');

if (!$fpSrc) {
    die("Unable to download sample CSV file.\n");
}

while (!feof($fpSrc)) {
    fwrite($fpDst, fread($fpSrc, 10240));
}

@fclose($fpSrc);
@fclose($fpDst);

echo "Done.\n";

<?php

if (isset($_GET['getRomListing']) && $_GET['getRomListing'] == 'true') {

    $files = json_encode(array_diff(scandir('roms',1),['..','.']));
    header("content-type: application/json");
    die($files);

} elseif (isset($_GET['rom'])) {

    $filename = 'roms/' . $_GET['rom'];

    if (!file_exists($filename)) {
        die("Error: ROM file {$filename} does not exist.");
    }

    $data = file_get_contents($filename);

    if (empty($data)) {
        die("Error: ROM file is empty");
    }

    $binary = unpack("C*",$data);

    $binaryString = implode(",",$binary);

    $returnData = json_encode([
        'romName' => $_GET['ROM'],
        'romData' => $binaryString
    ]);

    header('content-type: application/json');

    die($returnData);
} else {
    require('emulator.html');
}

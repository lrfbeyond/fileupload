<?php 
require "../src/Upload/File.php";

use \Upload\File;

// $filename = 'test.txt';
// $file = new File($filename);
// print_r($file->test());

$upfile = $_FILES["image"];
$file = new File($upfile);

$file->validate = [
    'size' => 500*1024,
    'ext' => 'jpg,png,gif'
];

$info = $file->upload(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
print_r($info);

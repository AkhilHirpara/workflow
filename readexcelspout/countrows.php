<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

//Load laravel
require '../vendor/autoload.php';
$root_path = $_SERVER['DOCUMENT_ROOT'];
$envpath = rtrim($root_path, '/www');
$dotenv = Dotenv\Dotenv::createImmutable($envpath);
$dotenv->load();

$conn = new mysqli(env('DB_HOST'), env('DB_USERNAME'), env('DB_PASSWORD'), env('DB_DATABASE'));

// Check connection
if ($conn->connect_errno) {
    echo "Failed to connect to MySQL: " . $conn->connect_error;
    exit();
} else {
    // echo "connected";
}


// $excelfile_folder = $_SERVER['DOCUMENT_ROOT'].'/api/public' . env('IMPORT_FILESPATH');
$excelfile_folder = '/Websites/www/html/stagingqflow.quadringroup.com/www/api/readexcelspout/';
$filename = 'small_excel.xlsx';
// $filename = 'large_excel.xlsx';
$file_id = 11;
$file_path = $excelfile_folder . $filename;
$columg_dbtable = 'import_columns_phpexcel';
$data_dbtable = 'import_data_phpexcel';
$return_data = array();

$header_value = array();
$sql = "SELECT * FROM $columg_dbtable WHERE `file_id`=$file_id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($sqlrow = $result->fetch_assoc()) {
        $header_value[] = $sqlrow['column_heading'];
    }
}



require 'vendor/autoload.php';


use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

$reader = ReaderEntityFactory::createReaderFromFile($filename);
$reader->open($file_path);
$row_count = 1;
// print_r($reader);
// print_r($reader->getSheetIterator());
foreach ($reader->getSheetIterator() as $sheet) {
    // print_r($sheet->getRowIterator());
    // exit;

    $it = $sheet->getRowIterator();
    $row = $it->key(8);
    print_r($row);
    print_r($row);
    exit;
    foreach ($sheet->getRowIterator() as $row) {
        //     print_r($row);
        // exit;

        $row_count++;
    }
}
echo $row_count;

// print_r($header_value);
$reader->close();
$conn->close();

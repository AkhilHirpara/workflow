<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


// $excelfile_folder = $_SERVER['DOCUMENT_ROOT'].'/api/public' . env('PROJECT_EXCEL_FILESPATH');
$excelfile_folder = '/Websites/www/html/stagingqflow.quadringroup.com/www/api/readexcel/';
$filename = 'small_excel.xlsx';
// $filename = 'large_excel.xlsx';
$file_id = 11;
$file_path = $excelfile_folder . $filename;

$filename = 'large_excel.xlsx';
// $filename = 'small_excel.xlsx';
$file_path = $filename;


$filetype = 'Xlsx';
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($filetype);
$woksheetdata = $reader->listWorksheetInfo($file_path);
if (empty($woksheetdata)) {
    $return_data['status'] = 0;
    $return_data['message'] = 'Unable to sheet details';
    $return_data['error'] = 'Error';
} else {
    $total_rows = $woksheetdata[0]['totalRows'];
    echo $total_rows;

}
exit;


// $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
// $reader->setReadDataOnly(TRUE);
// $spreadsheet = $reader->load("small_excel.xlsx");
// $spreadsheet = $reader->load("large_excel.xlsx");

// $worksheet = $spreadsheet->getActiveSheet();
// // Get the highest row and column numbers referenced in the worksheet
// $highestRow = $worksheet->getHighestRow(); // e.g. 10
// $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
// $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5

// $highestRow = 50;
// echo '<table>' . "\n";
// for ($row = 1; $row <= $highestRow; ++$row) {
//     echo '<tr>' . PHP_EOL;
//     for ($col = 1; $col <= $highestColumnIndex; ++$col) {
//         $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
//         echo '<td>' . $value . '</td>' . PHP_EOL;
//     }
//     echo '</tr>' . PHP_EOL;
// }
// echo '</table>' . PHP_EOL;
// echo "DONE";

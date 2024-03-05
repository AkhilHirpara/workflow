<?php

	require 'vendor/autoload.php';

	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

	$spreadsheet = new Spreadsheet();

	$spreadsheet->getActiveSheet()->setCellValue('A1', 'Sr. No');
	$spreadsheet->getActiveSheet()->setCellValue('B1', 'Name');
	$spreadsheet->getActiveSheet()->setCellValue('C1', 'Email');

	$spreadsheet->getActiveSheet()->setCellValue('A2', '1');
	$spreadsheet->getActiveSheet()->setCellValue('B2', 'Rishi');
	$spreadsheet->getActiveSheet()->setCellValue('C2', 'rishi@gmail.com');

	$spreadsheet->getActiveSheet()->setCellValue('A3', '2');
	$spreadsheet->getActiveSheet()->setCellValue('B3', 'Ezhava');
	$spreadsheet->getActiveSheet()->setCellValue('C3', 'ezhava@gmail.com');

	$spreadsheet->getActiveSheet()->setCellValue('A4', '3');
	$spreadsheet->getActiveSheet()->setCellValue('B4', 'Rishi Ezhava');
	$spreadsheet->getActiveSheet()->setCellValue('C4', 'rishiezhava@gmail.com');	

	$spreadsheet->getActiveSheet()->setAutoFilter('A1:C1');

	$writer = new Xlsx($spreadsheet);
	$writer->save('testing.xlsx');

?>
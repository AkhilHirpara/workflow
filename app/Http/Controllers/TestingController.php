<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Files;
use App\Models\ImportColumns;


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Models\Questions;


class TestingController extends Controller
{
    public function readsheet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileid' => 'required|numeric|min:1|exists:App\Models\Files,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $fileid = $request->fileid;
        // $projectid = $request->projectid;
        $find_file = Files::find($fileid);
        if (empty($find_file)) {
            return error(Lang::get('validation.custom.file_not_found'));
        } else {
            $sheet_headers = array();
            $all_columns = ImportColumns::where('file_id', $find_file->id)->get()->toArray();
            $filedirpath = public_path() . env('IMPORT_FILESPATH') . $find_file->filename;

//             $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
// /**  Load $inputFileName to a Spreadsheet Object  **/
// $spreadsheet = $reader->load($inputFileName);

            $spreadsheet = IOFactory::load($filedirpath);
            return "asdadas";
            $sheet = $spreadsheet->getActiveSheet();
            // $row_limit    = $sheet->getHighestDataRow();
            $column_limit = $sheet->getHighestDataColumn();
            $column_chars = array();
            $letter = 'A';
            while ($letter !== $column_limit) {
                $column_chars[] = $letter++;
            }
            $header_row = array();
            foreach ($column_chars as $char) {
                $header_row[] =  str_replace(' ', '_', $sheet->getCell($char . '1')->getValue());
            }

            // return $sheet->getCell( 'FN1' )->getValue();

            return $header_row;
            // foreach ( $row_range as $row ) {
            //     $data[] = [
            //         'CustomerName' =>$sheet->getCell( 'A' . $row )->getValue(),
            //         'Gender' => $sheet->getCell( 'B' . $row )->getValue(),
            //         'Address' => $sheet->getCell( 'C' . $row )->getValue(),
            //         'City' => $sheet->getCell( 'D' . $row )->getValue(),
            //         'PostalCode' => $sheet->getCell( 'E' . $row )->getValue(),
            //         'Country' =>$sheet->getCell( 'F' . $row )->getValue(),
            //     ];
            //     $startcount++;
            // }


            // $row_range    = range( 2, $row_limit );
            // $column_range = range( 'F', $column_limit );
            // $startcount = 2;
            // $data = array();
            // foreach ( $row_range as $row ) {
            //     $data[] = [
            //         'CustomerName' =>$sheet->getCell( 'A' . $row )->getValue(),
            //         'Gender' => $sheet->getCell( 'B' . $row )->getValue(),
            //         'Address' => $sheet->getCell( 'C' . $row )->getValue(),
            //         'City' => $sheet->getCell( 'D' . $row )->getValue(),
            //         'PostalCode' => $sheet->getCell( 'E' . $row )->getValue(),
            //         'Country' =>$sheet->getCell( 'F' . $row )->getValue(),
            //     ];
            //     $startcount++;
            // }
            // return $data;
        }
    }
    public function deletejunkdata(Request $request){
        
    }


}



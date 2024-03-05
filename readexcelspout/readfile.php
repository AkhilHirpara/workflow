<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

if (isset($_REQUEST['fileid']) && trim($_REQUEST['fileid']) != '') {




    //Load laravel
    require '../vendor/autoload.php';
    $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    if (strpos($actual_link, 'localhost') !== false || strpos($actual_link, '127.0.0.1:8000') !== false) {
        // Localhost
        $root_path = $_SERVER['DOCUMENT_ROOT'];
        $envpath = $root_path . '/workflow-laravel/';
        $dotenv = Dotenv\Dotenv::createImmutable($envpath);
    } else if (strpos($actual_link, 'stagingqflow') !== false) {
        // Staging - http://stagingqflow.quadringroup.com/
        $root_path = $_SERVER['DOCUMENT_ROOT'];
        $envpath = rtrim($root_path, '/www');
        $dotenv = Dotenv\Dotenv::createImmutable($envpath);
    } else {
        // Live - http://qflow.quadringroup.com/
        $root_path = $_SERVER['DOCUMENT_ROOT'];
        $envpath = rtrim($root_path, '/html');
        $dotenv = Dotenv\Dotenv::createImmutable($envpath);
    }
    $dotenv->load();

    $conn = new mysqli(env('DB_HOST'), env('DB_USERNAME'), env('DB_PASSWORD'), env('DB_DATABASE'));

    // Check connection
    if ($conn->connect_errno) {
        $return_data['status'] = 0;
        $return_data['message'] = 'Unable to connect to database';
        $return_data['error'] = "Failed to connect to MySQL: " . $conn->connect_error;
        echo json_encode($return_data);
        exit;
    } else {
        // echo "connected";
    }

    $columg_dbtable = 'import_columns';
    $data_dbtable = 'import_data';
    $file_dbtable = 'files';
    $current_time = currenthumantime();
    $alread_imported = '';

    $excelfile_folder = env('COMPLETE_PROJECT_IMPORT_FILESPATH');
    // $excelfile_folder = '/Websites/www/html/stagingqflow.quadringroup.com/www/api/readexcelspout/';
    // $filename = 'small_excel.xlsx';
    // $filename = 'large_excel.xlsx';
    // $filename = 'Test excel file_1649401051_1650634696.xlsx';

    $filename = '';
    $file_id = $_REQUEST['fileid'];
    $sql = "SELECT * FROM $file_dbtable WHERE `id`=$file_id AND import_status=2";
    $result = $conn->query($sql);
    if (isset($result->num_rows) && $result->num_rows > 0) {
        while ($sqlrow = $result->fetch_assoc()) {
            $filename = $sqlrow['filename'];
            $alread_imported = $sqlrow['imported_rows'];
        }
    }

    if ($filename != '') {

        $file_path = $excelfile_folder . $filename;


        // $columg_dbtable = 'import_columns_phpexcel';
        // $data_dbtable = 'import_data_phpexcel';



        $header_value = array();
        $sql = "SELECT * FROM $columg_dbtable WHERE `file_id`=$file_id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($sqlrow = $result->fetch_assoc()) {
                $header_value[] = $sqlrow['column_heading'];
            }
        }

        $sql = "UPDATE $file_dbtable SET `import_start_time`='" . $current_time . "' WHERE id=$file_id";
        if ($conn->multi_query($sql) !== TRUE) {
            $return_data['status'] = 0;
            $return_data['message'] = 'Unable to update import start time in files table';
            $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
            echo json_encode($return_data);
            exit;
        }




        require 'vendor/autoload.php';


        $reader = ReaderEntityFactory::createReaderFromFile($filename);
        $reader->open($file_path);
        $row_count = 0;
        $total_skipped = 0;
        $sheet_count = 1;
        $import_bulk = array();
        $chunk_length = 1000;



        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet_count == 1) {
                foreach ($sheet->getRowIterator() as $row) {
                    $row_count++;

                    if ($alread_imported != NULL && $alread_imported > 0 && $row_count <= $alread_imported) {
                        continue;
                    }

                    $current_time = currenthumantime();

                    $cells = $row->getCells();
                    if ($row_count == 1) {
                        if (empty($header_value)) {
                            $sql_values = '';
                            foreach ($cells as $scell) {
                                $svalue = str_replace(' ', '_', $scell->getValue());
                                $header_value[] = $svalue;
                                $sql_values .= '(' . $file_id . ',NULL,"' . $conn->real_escape_string($svalue) . '",1,"' . $current_time . '","' . $current_time . '"),';
                            }
                            if (!empty($header_value)) {
                                $sql_values = rtrim($sql_values, ',') . ';';
                                $sql = "INSERT INTO $columg_dbtable (`file_id`, `project_id`, `column_heading`,`status`,`created_at`,`updated_at`) VALUES $sql_values";
                                if ($conn->multi_query($sql) === TRUE) {
                                    // echo "New records created successfully";
                                } else {
                                    $return_data['status'] = 0;
                                    $return_data['message'] = 'Unable to insert header columns';
                                    $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
                                }
                            }
                        }
                    }

                    if (empty($header_value)) {
                        $return_data['status'] = 0;
                        $return_data['message'] = 'Empty header columns ';
                        $return_data['error'] = 'Error';
                    }

                    if ($row_count != 1 && empty($return_data)) {
                        $row_value = array();
                        foreach ($cells as $scell) {
                            $svalue = $scell->getValue();
                            if (is_a($svalue, 'DateTime')) {
                                $svalue = $svalue->format('Y-m-d H:i:s');
                            }
                            $row_value[] = $svalue;
                        }
                        // $final_array = array_combine($header_value, $row_value);         //Not working if last cols are empty
                        $final_array = array();
                        foreach ($header_value as $key => $hcol) {
                            if (isset($row_value[$key])) {
                                $final_array[$hcol] = $row_value[$key];
                            } else {
                                $final_array[$hcol] = '';
                            }
                        }

                        //skip row if Account_Reference,Current_Balance,Delinquency_Flag are empty
                        if (trim($final_array['Account_Reference']) == '' || trim($final_array['Current_Balance']) == '' || trim($final_array['Delinquency_Flag']) == '') {
                            $total_skipped++;
                            $row_count--;
                            continue;
                        }
                        if (!empty($final_array)) {
                            $data_json = json_encode($final_array);
                            $import_bulk[] = array('jsondata' => $data_json);

                            if ((sizeof($import_bulk) % $chunk_length) == 0) {
                                $sql_datavalues = '';
                                foreach ($import_bulk as $schunk) {
                                    $data_json = $conn->real_escape_string($schunk['jsondata']);
                                    $sql_datavalues .= '(' . $file_id . ',NULL,"' . $data_json . '",0,"' . $current_time . '","' . $current_time . '"),';
                                }
                                $sql_datavalues = rtrim($sql_datavalues, ',') . ';';
                                $sql = "INSERT INTO $data_dbtable (`file_id`, `project_id`, `row_details`,`task_status`,`created_at`,`updated_at`) VALUES $sql_datavalues";
                                if ($conn->multi_query($sql) === TRUE) {
                                    // echo "New records created successfully";
                                    $sql1 = "UPDATE $file_dbtable SET `imported_rows`= " . $row_count . " WHERE id=$file_id";
                                    if ($conn->multi_query($sql1) !== TRUE) {
                                        $return_data['status'] = 0;
                                        $return_data['message'] = 'Unable to update imported rows in files table';
                                        $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
                                    }
                                } else {
                                    $return_data['status'] = 0;
                                    $return_data['message'] = 'Unable to insert excel data';
                                    $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
                                }
                                $import_bulk = array();
                            }
                        } else {
                        }
                    }
                }
            }
            if ($sheet_count != 1) {
                $import_bulk = array();
            }
            $sheet_count++;
        }

        if (!empty($import_bulk)) {
            $sql_datavalues = '';
            foreach ($import_bulk as $schunk) {
                $data_json = $conn->real_escape_string($schunk['jsondata']);
                $sql_datavalues .= '(' . $file_id . ',NULL,"' . $data_json . '",0,"' . $current_time . '","' . $current_time . '"),';
            }
            $sql_datavalues = rtrim($sql_datavalues, ',') . ';';
            $sql = "INSERT INTO $data_dbtable (`file_id`, `project_id`, `row_details`,`task_status`,`created_at`,`updated_at`) VALUES $sql_datavalues";
            if ($conn->multi_query($sql) === TRUE) {
                // echo "New records created successfully";
                $sql1 = "UPDATE $file_dbtable SET `imported_rows`= " . $row_count . " WHERE id=$file_id";
                if ($conn->multi_query($sql1) !== TRUE) {
                    $return_data['status'] = 0;
                    $return_data['message'] = 'Unable to update imported rows in files table';
                    $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
                }
            } else {
                $return_data['status'] = 0;
                $return_data['message'] = 'Unable to insert excel data';
                $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
            }
            $import_bulk = array();
        }

        $reader->close();

        if (empty($return_data)) {
            $current_time = currenthumantime();
            $sql = "UPDATE $file_dbtable SET `import_status`=1,`import_end_time`='" . $current_time . "' WHERE id=$file_id";
            if ($conn->multi_query($sql) === TRUE) {
                $return_data['status'] = 1;
                $return_data['message'] = 'Success';
                $return_data['error'] = '';
            } else {
                $return_data['status'] = 0;
                $return_data['message'] = 'Unable to update import status and import end time in files table';
                $return_data['error'] = 'Error: ' . $sql . '<br>' . $conn->error;
            }
        }

        $conn->close();
    } else {
        $return_data['status'] = 0;
        $return_data['message'] = 'Invalid file id';
        $return_data['error'] = 'Error';
    }
} else {
    $return_data['status'] = 0;
    $return_data['message'] = 'Invalid Request';
    $return_data['error'] = 'Error';
}
echo json_encode($return_data);
exit;

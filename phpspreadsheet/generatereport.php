<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (isset($_REQUEST['projectid']) && trim($_REQUEST['projectid']) != '') {
    //Load laravel
    require '../vendor/autoload.php';
    $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    // echo $actual_link;die;
    if (strpos($actual_link, 'localhost') !== false || strpos($actual_link, '127.0.0.1:8000') !== false) {
        // Localhost
        $root_path = $_SERVER['DOCUMENT_ROOT'];
        $envpath = $root_path . '/workflow-laravel/workflow-laravel/';
        // echo $envpath;die;
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
    $integrity_dbtable = 'integrity';
    $task_dbtable = 'task';
    $users_dbtable = 'users';
    $projects_dbtable = 'projects';
    $questions_dbtable = 'questions';
    $file_dbtable = 'files';
    $current_time = currenthumantime();
    $alread_imported = '';
    $excelfile_folder = env('COMPLETE_PROJECT_EXPORT_FILESPATH');
    $review_sheet_name = 'Review';
    $integrity_sheet_name = 'Integrity';
    $integrity_summary_sheet_name = 'Integrity Summary';

    $filename = '';
    $project_id = $_REQUEST['projectid'];
    $project_details = $file_details = $all_users = $integrity_cols = array();
    $export_filename = $template_id = '';
    $continue_report = 1;
    $all_questions = array();
    $sql = "SELECT * FROM $projects_dbtable WHERE `id`=$project_id LIMIT 1";
    $result = $conn->query($sql);
    $only_integrity = $only_review = '0';
    if (isset($result->num_rows) && $result->num_rows > 0) {
        while ($sqlrow = $result->fetch_assoc()) {
            $project_details = $sqlrow;
            $template_id = $project_details['template_id'];
            $project_name_identifier = $sqlrow['project_name'].' '.$sqlrow['identifier'];
            $string = str_replace(' ', '_', $project_name_identifier);
            $currentDateTime = date('Y-m-d H:i:s');
            $export_filename = preg_replace('/[^A-Za-z0-9\_]/', '', $string) . '_Export_' . $currentDateTime . '.xlsx';
            if($project_details['only_integrity'])
                $only_integrity = '1';
            if($project_details['only_review'])
                $only_review = '1';
        }

        $sql = "SELECT * FROM $file_dbtable WHERE `project_id`=$project_id";
        $result = $conn->query($sql);
        if (isset($result->num_rows) && $result->num_rows > 0) {
            while ($sqlrow = $result->fetch_assoc()) {
                $file_details = $sqlrow;
            }
        }
    }

    $sql = "SELECT id,column_heading FROM $columg_dbtable WHERE `project_id`=$project_id AND status=1";
    
    // $sql = "SELECT id,column_heading FROM $columg_dbtable WHERE `project_id`=$project_id AND `column_heading` !='Account_Reference' AND `column_heading` !='Current_Balance' AND `column_heading` !='Delinquency_Flag' AND status=1";
    $result = $conn->query($sql);
    if (isset($result->num_rows) && $result->num_rows > 0) {
        while ($sqlrow = $result->fetch_assoc()) {
            $integrity_cols[$sqlrow['id']] = $sqlrow['column_heading'];
        }
    }


    $sql = "SELECT id,firstname,lastname FROM $users_dbtable";
    $result = $conn->query($sql);
    if (isset($result->num_rows) && $result->num_rows > 0) {
        while ($sqlrow = $result->fetch_assoc()) {
            $all_users[$sqlrow['id']] = $sqlrow['firstname'] . ' ' . $sqlrow['lastname'];
        }
    }
    if ($template_id != '') {
        $sql = "SELECT * FROM $questions_dbtable WHERE `template_id`=$template_id AND `status` = '1' ";
        $result = $conn->query($sql);
        if (isset($result->num_rows) && $result->num_rows > 0) {
            while ($sqlrow = $result->fetch_assoc()) {
                $all_questions[$sqlrow['id']] = array(
                    'export_heading' => $sqlrow['export_heading'],
                    'comment_required' => $sqlrow['comment_required'],
                    'question' => $sqlrow['question'],
                );

            }
        }
    }
    // if (empty($project_details) || empty($all_questions) || $export_filename == '' || $template_id == '') {
    if (empty($project_details) || $export_filename == '') {
        $continue_report = 0;
    }
    if ($continue_report == 1) {

        $full_exportfile_path = $excelfile_folder . $export_filename;
        $link_exportfile_path = $excelfile_folder . $export_filename;
        $spreadsheet = new Spreadsheet();

        $integrity_sheet = $review_sheet = '';
        if($only_integrity == '1'){
            $integrity_sheet = $spreadsheet->getActiveSheet();
        }
        else{
            $review_sheet = $spreadsheet->getActiveSheet();
            $review_sheet->setTitle('Review');
            if(!$only_review)
                $integrity_sheet = $spreadsheet->createSheet();
        }
        if(!$only_review){
            $integrity_sheet->setTitle('Integrity');
            $integrity_summary_sheet = $spreadsheet->createSheet();
            $integrity_summary_sheet->setTitle('Integrity Summary'); 
        }
        

        // ======================= Review sheet process
        if($only_integrity != '1'){

            $spreadsheet->getSheetByName($review_sheet_name);
            $review_header = array('Account_Reference', 'Current_Balance', 'Delinquency_Flag',' ');
            #
            $review_sheet->fromArray([$review_header], NULL, 'A1');
            #
            foreach ($all_questions as $qid => $sq) {
                // array_push($review_header, $sq['export_heading']);
                $colName = $review_sheet->getHighestDataColumn($review_sheet->getHighestRow()).($review_sheet->getHighestRow());
                $tempArr = array($sq['export_heading'],' ');
                $review_sheet->fromArray([$tempArr], NULL, $colName);
                $review_sheet->getComment($colName)->getText()->createTextRun($sq['question']);
            }
            // array_push($review_header, ...array('Quadrin Grade', 'Comments', 'Exceptions', 'Status', 'Date Completed', 'Owner', 'Last Modified By'));
            // $review_sheet->fromArray([$review_header], NULL, 'A1');
            $tempArr = array('Quadrin Grade', 'Comments', 'Exceptions', 'Status', 'Date Completed', 'Owner', 'Last Modified Owner');
            $colName = $review_sheet->getHighestDataColumn($review_sheet->getHighestRow()).($review_sheet->getHighestRow());
            $review_sheet->fromArray([$tempArr], NULL, $colName);

            
            $highestColumn = $review_sheet->getHighestColumn();
            $review_sheet->getStyle('A1:' . $highestColumn . '1' )->getFont()->setBold(true);
            $review_sheet->setAutoFilter('A1:' . $highestColumn . '1' );
            foreach (range('A', $highestColumn) as $col) {
                $review_sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // ======================= Integrity sheet process
        if(!$only_review){
            $integrity_header1 = array('', '');
            $integrity_header2 = array('Account_Reference', 'Current_Balance');
            $integrity_sheet->fromArray([$integrity_header1], NULL, 'A1');
            $integrity_sheet->fromArray([$integrity_header2], NULL, 'A2');
            $integrityHeaderCols = array();
            foreach ($integrity_cols as $cid => $colhead) {
                array_push($integrityHeaderCols,$colhead);
                array_push($integrityHeaderCols,'');
                array_push($integrityHeaderCols,'');
            }
            $integrity_sheet->fromArray([$integrityHeaderCols], NULL, 'C1');
            $integrityheader2_cells = array();
            foreach($integrity_cols as $each_i_col){
                array_push($integrityheader2_cells,'Data');
                array_push($integrityheader2_cells, 'Match');
                array_push($integrityheader2_cells, 'System');
            }
            array_push($integrityheader2_cells, 'Exceptions');
            array_push($integrityheader2_cells, 'Status');
            array_push($integrityheader2_cells, 'Date Completed');
            array_push($integrityheader2_cells, 'Owner');
            array_push($integrityheader2_cells, 'Last Modified Owner');
            $integrity_sheet->fromArray([$integrityheader2_cells], NULL, 'C2');

            $highestColumn = $integrity_sheet->getHighestColumn();
            $integrity_sheet->getStyle('A1:' . $highestColumn . '1' )->getFont()->setBold(true);
            $integrity_sheet->getStyle('A2:' . $highestColumn . '2' )->getFont()->setBold(true);
            $integrity_sheet->setAutoFilter('A2:' . $highestColumn . '2');
            foreach (range('A', $highestColumn) as $col) {
                $integrity_sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // ======================= Integrity summary sheet process
            // $integrity_summary_sheet->mergeCells('A1:E1');
            $integritysummaryheader1_cells = array('','Differences');
            $integrity_summary_sheet->fromArray([$integritysummaryheader1_cells], NULL, 'A1');
             
            $integritysummaryheader2_cells = array('Field','#','%','$','%');
            $integrity_summary_sheet->fromArray([$integritysummaryheader2_cells], NULL, 'A2');

            $highestColumn = $integrity_summary_sheet->getHighestColumn();
            $integrity_summary_sheet->getStyle('A1:' . $highestColumn . '1' )->getFont()->setBold(true);
            $integrity_summary_sheet->getStyle('A2:' . $highestColumn . '2' )->getFont()->setBold(true);
            foreach (range('A', $highestColumn) as $col) {
                $integrity_summary_sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // =============== common for import data
            
            $intergrity_summary_counter = array();
        	
        
            foreach($integrity_cols as $each_col){
                $intergrity_summary_counter[$each_col]['mismatch'] = 0;
                $intergrity_summary_counter[$each_col]['mismatch_percent'] = 0;
                $intergrity_summary_counter[$each_col]['total_mismatch'] = 0;
                $intergrity_summary_counter[$each_col]['total'] = 0;
                $intergrity_summary_counter[$each_col]['total_percent'] = 0;
            }
            $intergrity_summary_counter['total_rows'] = 0;
            $intergrity_summary_counter['integrity_total_rows'] = 0;
            // $intergrity_summary_counter['integrity_total_current_balance'] = 0;
            $intergrity_summary_counter['total_reviewed_rows'] = 0;
            $intergrity_summary_counter['exceptions'] = 0;
            $intergrity_summary_counter['exceptions_balance'] = 0;
            $intergrity_summary_counter['exceptions_total_balance'] = 0;
        }

        $max_rows = $file_details['imported_rows'];
        $exit_flag = '0';
        $chunk_length = 1000;
        for ($i = 1; $i <= $max_rows; $i += $chunk_length) {
            $multipleReviewRows = $multipleIntegrityRows = $multipleIntegritySummaryRows = array();
            $start_limit = $i - 1;
            $end_limit = $i + $chunk_length - 1;
            $sql = "SELECT * FROM $data_dbtable WHERE `project_id`=$project_id ORDER BY id ASC LIMIT $chunk_length OFFSET $start_limit";
            $result = $conn->query($sql);
        	
            if ($result->num_rows > 0) {
         
           
                while ($sqlrow = $result->fetch_assoc()) {
                    if(!$only_review)
                        $intergrity_summary_counter['integrity_total_rows']++;
                    $allQuestionComments = '';
                    $rowdetails = json_decode($sqlrow['row_details'], true);
                    $Account_Reference = $rowdetails['Account_Reference'];
                    $Current_Balance = $rowdetails['Current_Balance'];
                    $Delinquency_Flag = ($rowdetails['Delinquency_Flag'] != '')?$rowdetails['Delinquency_Flag'] : '0';
                    $exit_flag++;
                    $review_status = ($sqlrow['task_status'] == 1) ? 'In Progress' : (($sqlrow['task_status'] == 2) ? 'Complete' : 'Pending');
                    $integrity_status = ($sqlrow['integrity_status'] == 1) ? 'In Progress' : (($sqlrow['integrity_status'] == 2) ? 'Complete' : 'Pending');
                    $r_grade = $r_comment = $r_date_completed = $r_owner = $r_last_modified_by = '';
                    $r_exception = 'False';
                    $r_answers = array();
                    $rowid = $sqlrow['id'];
                    $sql1 = "SELECT * FROM $task_dbtable WHERE `row_id`=$rowid LIMIT 1";
                    $result1 = $conn->query($sql1);
                 
                
                    if ($result1->num_rows > 0) {
                        while ($gettask = $result1->fetch_assoc()) {
                            if($gettask['status'] == 1){
                                $review_status = 'In Progress';
                            }
                            else if($gettask['status'] == 2){
                                $review_status = 'Complete';
                            }
                            else if($gettask['status'] == 3){
                                $review_status = 'Incomplete';
                            }
                            else{
                                $review_status = 'Pending';   
                            }

                            $r_grade = $gettask['grade'];
                            if($gettask['comment'] != '')
                                $allQuestionComments.= 'Grade Comment: '.$gettask['comment'].";\n";
                            $r_date_completed = ($gettask['worked_date'] != '') ? date("m-d-Y H:i:s", strtotime($gettask['worked_date'])) : '';
                            $r_owner = isset($all_users[$gettask['record_owner']]) ? $all_users[$gettask['record_owner']] : '';
                            $r_last_modified_by = isset($all_users[$gettask['last_modified_by']]) ? $all_users[$gettask['last_modified_by']] : '';
                            $r_answers = json_decode($gettask['answers'], true);
                        }
                    }

                    /* ----------------------- trial ------------------------------ */

                    
                    if($only_integrity != '1'){
                        $Review_cells = array($Account_Reference, $Current_Balance, $Delinquency_Flag,' ');
                        $reviewHighestColumn = $review_sheet->getHighestRow()+1;
                        $review_sheet->fromArray([$Review_cells], NULL, 'A'.$reviewHighestColumn);
                    }
                    
                    
                    /* ----------------------- trial ------------------------------ */
                    // $Review_cells = array($Account_Reference, $Current_Balance, $Delinquency_Flag);

                    if (!empty($r_answers)) {
                        foreach ($all_questions as $qid => $sq) {
                            $question_value = $question_comment = '';
                            foreach ($r_answers as $ra) {
                                if ($ra['questionid'] == $qid) {
                                    $question_value = $ra['selected_choice'];
                                    $question_comment = $ra['comment'];
                                }
                            }
                            if ($question_comment != '') {
                                $questionTitleSql = "SELECT `export_heading`, `choices` FROM $questions_dbtable WHERE `id`=$qid LIMIT 1";
                                $questionTitleSqlresult = $conn->query($questionTitleSql)->fetch_assoc();
                                $allQuestionComments.= $questionTitleSqlresult['export_heading'].":".$question_comment.";\n";
                                $choicesDecoded = json_decode($questionTitleSqlresult['choices']);
                                $commentRequired = '0';
                                foreach($choicesDecoded as $eachDecodedChoice){
                                    if($eachDecodedChoice->choice == $question_value && $eachDecodedChoice->status == '1'){
                                        $commentRequired = '1';
                                    }
                                }

                                $colName = $review_sheet->getHighestDataColumn($review_sheet->getHighestRow()).$review_sheet->getHighestRow();

                                $tempArr = array($question_value,' ');
                                $review_sheet->fromArray([$tempArr], NULL, $colName);
                                if($commentRequired){
                                    $review_sheet->getStyle($colName)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ff0000');
                                    $review_sheet->getStyle($colName)->getFont()->setBold(true);
                                    // array_push($Review_cells, $question_value);
                                }
                                // else{
                                //     // array_push($Review_cells, $question_value);
                                // }
                                $r_exception = 'True';
                            } else {
                                $colName = $review_sheet->getHighestDataColumn($review_sheet->getHighestRow()).($review_sheet->getHighestRow());
                                $tempArr = array($question_value,' ');
                                $review_sheet->fromArray([$tempArr], NULL, $colName);
                                
                            }
                        }
                    } 
                    else {
                        foreach ($all_questions as $qid => $sq) {
                            $colName = $review_sheet->getHighestDataColumn($review_sheet->getHighestRow()).$review_sheet->getHighestRow();
                            $tempArr = array(' ',' ');
                            $review_sheet->fromArray([$tempArr], NULL, $colName);
                        }
                    }
                    if($only_integrity != '1'){
                        $tempArr = array();
                        array_push($tempArr, ...array($r_grade, $allQuestionComments, $r_exception, $review_status, $r_date_completed, $r_owner,$r_last_modified_by));
                        $colName = $review_sheet->getHighestDataColumn($review_sheet->getHighestRow()).$review_sheet->getHighestRow();
                        $review_sheet->fromArray([$tempArr], NULL, $colName);
                    }
                    
                    
                    // integrity
                    if(!$only_review){
                        // $sql2 = "SELECT * FROM $integrity_dbtable WHERE `row_id`=$rowid and status = '2' LIMIT 1";
                        $sql2 = "SELECT * FROM $integrity_dbtable WHERE `row_id`=$rowid LIMIT 1";
                        $result2 = $conn->query($sql2);
                        $i_answers = array();
                    
                        // if ($result2->num_rows > 0 && $result2->fetch_assoc()['status'] == '2') {
                        if ($result2->num_rows > 0) {
                            // echo '1<br>';
                            
                            
                            while ($gettask2 = $result2->fetch_assoc()) {
                                // echo '2<br>';
                                if($gettask2['status'] == '2'){
                                    // echo '1<br>';
                                    $integrity_cells = array($Account_Reference,$Current_Balance,' ');
                                    $integrityHighestColumn = $integrity_sheet->getHighestRow()+1;
                                    $integrity_sheet->fromArray([$integrity_cells], NULL, 'A'.$integrityHighestColumn);
                                    $intergrity_summary_counter['total_rows']++;
                                    $i_answers = json_decode($gettask2['answers']);
                                    $do_exceptions_exist = 0;
                                    $exception ='FALSE';
                                    $i_date_completed = $i_owner = '';

                                    if($gettask2['status'] == '2')
                                        $intergrity_summary_counter['total_reviewed_rows']++;
                                    if(!is_null($i_answers)){
                                        // echo '2<br>';
                                        foreach($i_answers as $iid=> $ia) {
                                        if (!isset($intergrity_summary_counter[$ia->column_name])) {
					    					$intergrity_summary_counter[$ia->column_name] = ['mismatch' => 0,'total_mismatch'=>0,'total'=>0];
                                        }
                                            $column_name = '';
                                            $i_key_answer_sql = "SELECT row_details FROM $data_dbtable WHERE `id`=$rowid and `project_id` = $project_id LIMIT 1";
                                            $key_node = $conn->query($i_key_answer_sql);
                                            if ($key_node->num_rows > 0) {
                                                $res = $key_node->fetch_assoc();
                                                $each_key_node_res = json_decode($res['row_details']);
                                                $column_name = $ia->column_name;
                                            }
                                            $status = 'FALSE'; 
                                            $comment = ' ';
                                            if($ia->status == 'Match'){
                                                $status = 'TRUE';
                                            }
                                            else{
                        // if (!isset($intergrity_summary_counter[$ia->column_name])) {
                        // $intergrity_summary_counter[$ia->column_name]['mismatch'] ++;
                        // $intergrity_summary_counter[$ia->column_name]['total_mismatch'] = $intergrity_summary_counter[$ia->column_name]['total_mismatch'] +$Current_Balance;                                                    
                        // } else {
                        // $intergrity_summary_counter[$ia->column_name] = ['mismatch' => 1,'total_mismatch'=>1,'total'=>0];
                        // }

                                            
                                               $intergrity_summary_counter[$ia->column_name]['mismatch'] ++;
                                               $intergrity_summary_counter[$ia->column_name]['total_mismatch'] = $intergrity_summary_counter[$ia->column_name]['total_mismatch'] +$Current_Balance;
                                            }
					    //$intergrity_summary_counter = isset($intergrity_summary_counter[$ia->column_name]['total']) ? $intergrity_summary_counter[$ia->column_name]['total'] : 0;
//                                            $intergrity_summary_counter[$ia->column_name]['total'] = $intergrity_summary_counter+$Current_Balance;

						//Existing
//					    $intergrity_summary_counter[$ia->column_name]['total'] = $intergrity_summary_counter[$ia->column_name]['total'] +$Current_Balance;
					    if (isset($intergrity_summary_counter[$ia->column_name])) {
                        
                                                $intergrity_summary_counter[$ia->column_name]['total'] += $Current_Balance;
                        						// $intergrity_summary_counter[$ia->column_name]['total'] = $intergrity_summary_counter[$ia->column_name]['total'] +$Current_Balance;
                                            } 
                                        	// else {
                                        	// $intergrity_summary_counter[$ia->column_name] = ['total' => $Current_Balance];
                                        	// }

                                            
                                            if(!is_null($ia->comment)){
                                                $comment = $ia->comment;
                                                $do_exceptions_exist = '1';
                                            }
                                            
                                            $colName = $integrity_sheet->getHighestDataColumn($integrity_sheet->getHighestRow()).($integrity_sheet->getHighestRow());
                                            $tempArr = array($each_key_node_res->$column_name.' ',' ');
                                        
                                        	
                                     
                                        
                                            $integrity_sheet->fromArray([$tempArr], NULL, $colName);
                                            if(!is_null($ia->comment)){
                                                $integrity_sheet->getStyle($colName)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ff0000');
                                                $integrity_sheet->getStyle($colName)->getFont()->setBold(true);
                                            }

                                            $colName = $integrity_sheet->getHighestDataColumn($integrity_sheet->getHighestRow()).($integrity_sheet->getHighestRow());
                                            $tempArr = array($status,' ');
                                            $integrity_sheet->fromArray([$tempArr], NULL, $colName);
                                            if(!is_null($ia->comment)){
                                                $integrity_sheet->getStyle($colName)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ff0000');
                                                $integrity_sheet->getStyle($colName)->getFont()->setBold(true);
                                            }

                                            $colName = $integrity_sheet->getHighestDataColumn($integrity_sheet->getHighestRow()).($integrity_sheet->getHighestRow());
                                            $tempArr = array($comment,' ');
                                            $integrity_sheet->fromArray([$tempArr], NULL, $colName);
                                            if(!is_null($ia->comment)){
                                                $integrity_sheet->getStyle($colName)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ff0000');
                                                $integrity_sheet->getStyle($colName)->getFont()->setBold(true);
                                            }
                                        }
                                    }
                                    if($do_exceptions_exist){
                                        $exception = 'TRUE';
                                        $intergrity_summary_counter['exceptions']++;
                                        $intergrity_summary_counter['exceptions_balance'] += $Current_Balance;
                                    }

                                    if($gettask2['status'] == '2')
                                        $intergrity_summary_counter['exceptions_total_balance'] += $Current_Balance;
                                    
                                    $integrity_status = ($gettask2['status'] == 0) ? 'Pending' : (($gettask2['status'] == 1) ? 'In Progress' : 'Completed');
                                    $i_date_completed = ($gettask2['worked_date'] != '') ? date("m-d-Y H:i:s", strtotime($gettask2['worked_date'])) : '';
                                    $i_owner = isset($all_users[$gettask2['record_owner']]) ? $all_users[$gettask2['record_owner']] : '';
                                    $i_last_modified_by = isset($all_users[$gettask2['last_modified_by']]) ? $all_users[$gettask2['last_modified_by']] : '';

                                    if(!is_null($i_answers)){
                                        $tempArr = array();
                                        array_push($tempArr, ...array($exception, $integrity_status, $i_date_completed, $i_owner, $i_last_modified_by));
                                        $colName = $integrity_sheet->getHighestDataColumn($integrity_sheet->getHighestRow()).$integrity_sheet->getHighestRow();
                                        $integrity_sheet->fromArray([$tempArr], NULL, $colName);
                                    }
                                }
                                else if($gettask2['status'] == '0' || $gettask2['status'] == '1' || $gettask2['status'] == '3'){
                                    // echo '3<br>';
                                    $integrityDetails = json_decode($sqlrow['row_details']);
                                    $tempArr = [$integrityDetails->Account_Reference, $integrityDetails->Current_Balance];
                                    // $tempArr = [];
                                    $tempCounter = (count($integrity_cols)*3);
                                    for($i=0;$i<$tempCounter;$i++){
                                        array_push($tempArr, ' ');
                                    }
                                    $tempArr[]='FALSE';
                                    if($sqlrow['integrity_status'] == '0')
                                        $tempArr[] = 'Pending';
                                    else if($sqlrow['integrity_status'] == '1')
                                        $tempArr[] = 'In Progress';
                                    else
                                        $tempArr[] = 'Incomplete';

                                    $colName = 'A'.($integrity_sheet->getHighestRow()+1);
                                    $integrity_sheet->fromArray([$tempArr], NULL, $colName);
                                }
                            }
                        }
                        else{
                            // echo '4<br>';
                            $integrityDetails = json_decode($sqlrow['row_details']);
                            $tempArr = [$integrityDetails->Account_Reference, $integrityDetails->Current_Balance];
                            $tempCounter = (count($integrity_cols)*3);
                            for($i=0;$i<$tempCounter;$i++){
                                array_push($tempArr, ' ');
                            }
                            $tempArr[]='FALSE';
                            if($sqlrow['integrity_status'] == '0')
                                $tempArr[] = 'Pending';
                            else if($sqlrow['integrity_status'] == '1')
                                $tempArr[] = 'In Progress';
                            else
                                $tempArr[] = 'Incomplete';

                            $colName = 'A'.($integrity_sheet->getHighestRow()+1);
                            $integrity_sheet->fromArray([$tempArr], NULL, $colName);
                        }
                    }
                }
            }
            // die;
            if($exit_flag != 0 && (!$only_review)){
           
                $total['total'] = 'Total';
                $total['grand_total_mismatch'] = $total['grand_total_mismtach_percent'] = $total['grand_total_of_mismtach_percent'] = $total['grand_total_of_total_mismtach_percent'] = 0;
                foreach($integrity_cols as $each_integrity_col){
                    $integrity_summary_cells=array();
                    $grand_total_mismtach_percent = ($intergrity_summary_counter[$each_integrity_col]['mismatch'] != '0'?$intergrity_summary_counter[$each_integrity_col]['mismatch']/$intergrity_summary_counter['integrity_total_rows'] * 100 : '0');
                    $grand_total_of_mismatch_percent = ($intergrity_summary_counter[$each_integrity_col]['total'] != '0' ? $intergrity_summary_counter[$each_integrity_col]['total_mismatch']/$intergrity_summary_counter[$each_integrity_col]['total'] * 100 : '0');

                    $total['grand_total_mismatch'] = $total['grand_total_mismatch'] + $intergrity_summary_counter[$each_integrity_col]['mismatch'];
                    $total['grand_total_mismtach_percent'] = $total['grand_total_mismtach_percent'] + $grand_total_mismtach_percent;
                    $total['grand_total_of_mismtach_percent'] = $total['grand_total_of_mismtach_percent'] + $intergrity_summary_counter[$each_integrity_col]['total_mismatch'];
                    $total['grand_total_of_total_mismtach_percent'] = $total['grand_total_of_total_mismtach_percent'] + $grand_total_of_mismatch_percent;

                    array_push($integrity_summary_cells, ...array($each_integrity_col,$intergrity_summary_counter[$each_integrity_col]['mismatch'],$grand_total_mismtach_percent,$intergrity_summary_counter[$each_integrity_col]['total_mismatch'],$grand_total_of_mismatch_percent));
                    $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                    $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);
                }
                
                $totalIntegrityMismatchPercentage = '0';
                if(count($integrity_cols) > 0 && $intergrity_summary_counter['total_reviewed_rows'] > 0)
                    $totalIntegrityMismatchPercentage = ($total['grand_total_mismatch'] / (count($integrity_cols) * $intergrity_summary_counter['total_reviewed_rows']))*100; 

                $integrity_summary_cells = array($total['total'],$total['grand_total_mismatch'],$totalIntegrityMismatchPercentage);
                $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);

                
                // Summary of integrity summary
                $integrity_summary_cells = array('','Summary');
                $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);

                $integrityColsCount = count($integrity_cols);
                $integrity_summary_sheet->getStyle('B'.($integrityColsCount+4).':' . $highestColumn . ($integrityColsCount+4) )->getFont()->setBold(true);
                
                $integrity_summary_cells = array('Field','#','%','$','%');
                $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);
                $integrity_summary_sheet->getStyle('A'.($integrityColsCount+5).':' . $highestColumn . ($integrityColsCount+5) )->getFont()->setBold(true);
                
                $exception_percentage = ($intergrity_summary_counter['total_reviewed_rows'] != '0' ?($intergrity_summary_counter['exceptions']/$intergrity_summary_counter['total_reviewed_rows'])*100 : '0');
                $exception_balance_percentage = ($intergrity_summary_counter['exceptions_total_balance'] != '0'? ($intergrity_summary_counter['exceptions_balance']/$intergrity_summary_counter['exceptions_total_balance'])*100: '0');

                $integrity_summary_cells = array('Total number of loans with differences',$intergrity_summary_counter['exceptions'],$exception_percentage,$intergrity_summary_counter['exceptions_balance'],$exception_balance_percentage);

                $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);
                
                $integrity_summary_cells = array('Total number of loans with no differences',$intergrity_summary_counter['total_reviewed_rows'] - $intergrity_summary_counter['exceptions'],100-$exception_percentage,$intergrity_summary_counter['exceptions_total_balance'] - $intergrity_summary_counter['exceptions_balance'],100-$exception_balance_percentage);
                $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);
                
                $integrity_summary_cells = array('Total Number of Reviewed Loans',$intergrity_summary_counter['total_reviewed_rows'],100,$intergrity_summary_counter['exceptions_total_balance'],100);
                $integritySummaryHighestColumn = $integrity_summary_sheet->getHighestRow()+1;
                $integrity_summary_sheet->fromArray([$integrity_summary_cells], NULL, 'A'.$integritySummaryHighestColumn, true);
            }
        }


        /* ******************************************************** Final Stage ************************************************************ */
        $writer = new Xlsx($spreadsheet);
        $fullFilePath = env('LARAVEL_SITE_URL').'/public/files/exports/'.$export_filename;
	    $writer->save($full_exportfile_path);
        echo env('LARAVEL_SITE_URL').'/public/files/exports/'.$export_filename;
    }
    exit;

} else {
    $return_data['status'] = 0;
    $return_data['message'] = 'Invalid Request';
    $return_data['error'] = 'Error';
}
echo json_encode($return_data);
exit;

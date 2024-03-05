<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

use Box\Spout\Common\Entity\Row;
use Box\Spout\Common\Entity\Style\Color;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

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
    // $excelfile_folder = '/Websites/www/html/stagingqflow.quadringroup.com/www/api/readexcelspout/';
    // $filename = 'small_excel.xlsx';
    // $filename = 'large_excel.xlsx';
    // $filename = 'Test excel file_1649401051_1650634696.xlsx';

    $filename = '';
    $project_id = $_REQUEST['projectid'];
    $project_details = $file_details = $all_users = $integrity_cols = array();
    $export_filename = $template_id = '';
    $continue_report = 1;
    $all_questions = array();
    $sql = "SELECT * FROM $projects_dbtable WHERE `id`=$project_id LIMIT 1";
    $result = $conn->query($sql);
    $only_integrity = '0';
    if (isset($result->num_rows) && $result->num_rows > 0) {
        while ($sqlrow = $result->fetch_assoc()) {
            $project_details = $sqlrow;
            $template_id = $project_details['template_id'];
            $string = str_replace(' ', '_', $sqlrow['project_name']);
            $export_filename = preg_replace('/[^A-Za-z0-9\_]/', '', $string) . '_Export_' . time() . '.xlsx';
            if($project_details['only_integrity'])
                $only_integrity = '1';
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
        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToFile($full_exportfile_path);

        $integrity_sheet = $review_sheet = '';
        if($only_integrity == '1'){
            $integrity_sheet = $writer->getCurrentSheet();
        }
        else{
            $review_sheet = $writer->getCurrentSheet();
            $review_sheet->setName('Review');
            $integrity_sheet = $writer->addNewSheetAndMakeItCurrent();
        }

        // $review_sheet = $writer->getCurrentSheet();
        // $review_sheet->setName('Review');

        // $integrity_sheet = $writer->addNewSheetAndMakeItCurrent();
        $integrity_sheet->setName('Integrity');

        $integrity_summary_sheet = $writer->addNewSheetAndMakeItCurrent();
        $integrity_summary_sheet->setName('Integrity Summary');

        // ======================= Review sheet process
        if($only_integrity != '1'){

            $writer->setCurrentSheet($review_sheet);
            $review_header = array('Account_Reference', 'Current_Balance', 'Delinquency_Flag');

            foreach ($all_questions as $qid => $sq) {
                array_push($review_header, $sq['export_heading']);
                // if ($sq['comment_required'] == 1) {
                    // array_push($review_header, 'Exception Comment For-' . $sq['export_heading']);
                // }
            }
            array_push($review_header, ...array('Quadrin Grade', 'Comments', 'Exceptions', 'Status', 'Date Completed', 'Owner', 'Last Modified By'));
            $head_style = (new StyleBuilder())->setFontBold()->build();
            $add_review_header = WriterEntityFactory::createRowFromArray($review_header, $head_style);
            $writer->addRow($add_review_header);
        }

        // ======================= Integrity sheet process
        $writer->setCurrentSheet($integrity_sheet);
        $integrityheader1_cells = array(
            WriterEntityFactory::createCell(''),
            WriterEntityFactory::createCell(''),
        );

        $integrity_header1 = array('', '');
        $integrity_header2 = array('Account_Reference', 'Current_Balance');
        $head_style_bold = (new StyleBuilder())->setFontBold()->build();
        foreach ($integrity_cols as $cid => $colhead) {
            array_push($integrityheader1_cells, WriterEntityFactory::createCell($colhead));   
            array_push($integrityheader1_cells, WriterEntityFactory::createCell(''));   
            array_push($integrityheader1_cells, WriterEntityFactory::createCell(''));   
        }
        $add_integrity_header1 = WriterEntityFactory::createRow($integrityheader1_cells,$head_style_bold);
        $writer->addRow($add_integrity_header1);

        $integrityheader2_cells = array();
        foreach($integrity_header2 as $i_each_header){
            array_push($integrityheader2_cells, $i_each_header); 
        }
        foreach($integrity_cols as $each_i_col){
            array_push($integrityheader2_cells,'Data');
            array_push($integrityheader2_cells, 'Match');
            array_push($integrityheader2_cells, 'System');
        }
        array_push($integrityheader2_cells, 'Exceptions');
        array_push($integrityheader2_cells, 'Status');
        array_push($integrityheader2_cells, 'Date Completed');
        array_push($integrityheader2_cells, 'Owner');
        array_push($integrityheader2_cells, 'Last Modified By');

        $head_style = (new StyleBuilder())->setFontBold()->build();
        $add_integrity_header2 = WriterEntityFactory::createRowFromArray($integrityheader2_cells, $head_style);
        $writer->addRow($add_integrity_header2);

        // ======================= Integrity summary sheet process
        $writer->setCurrentSheet($integrity_summary_sheet);
        $integritysummaryheader1_cells = array('','Differences');
        $integritysummaryheader1 = WriterEntityFactory::createRowFromArray($integritysummaryheader1_cells, $head_style);
        $writer->addRow($integritysummaryheader1);

        $integritysummaryheader2_cells = array('Field','#','%','$','%');
        $integritysummaryheader2 = WriterEntityFactory::createRowFromArray($integritysummaryheader2_cells, $head_style);
        $writer->addRow($integritysummaryheader2);


        // =============== common for import data
        $max_rows = $file_details['imported_rows'];
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

        // echo "<pre>";print_r($intergrity_summary_counter);die;
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
                    $intergrity_summary_counter['integrity_total_rows']++;
                    $allQuestionComments = '';
                    $rowdetails = json_decode($sqlrow['row_details'], true);
                    $Account_Reference = $rowdetails['Account_Reference'];
                    $Current_Balance = $rowdetails['Current_Balance'];
                    // $intergrity_summary_counter['integrity_total_current_balance']+= $Current_Balance;
                    $Delinquency_Flag = $rowdetails['Delinquency_Flag'];
                    // if($Account_Reference =='' || $Current_Balance == '' || $Delinquency_Flag == ''){
                    // if($Account_Reference =='' || $Delinquency_Flag == ''){
                    //     continue;
                    // }
                    $exit_flag++;
                    $review_status = ($sqlrow['task_status'] == 1) ? 'In Progress' : (($sqlrow['task_status'] == 2) ? 'Complete' : 'Pending');
                    $integrity_status = ($sqlrow['integrity_status'] == 1) ? 'In Progress' : (($sqlrow['integrity_status'] == 2) ? 'Complete' : 'Pending');
                    $r_grade = $r_comment = $r_date_completed = $r_owner = $r_last_modified_by = '';
                    $r_exception = 0;
                    $r_answers = array();
                    $rowid = $sqlrow['id'];
                    $sql1 = "SELECT * FROM $task_dbtable WHERE `row_id`=$rowid LIMIT 1";
                    $result1 = $conn->query($sql1);
                    if ($result1->num_rows > 0) {
                        while ($gettask = $result1->fetch_assoc()) {
                            // $review_status = ($gettask['status'] == 1) ? 'In Progress' : (($gettask['status'] == 2) ? 'Complete' : 'Pending');
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
                            // $r_comment = $gettask['comment'];
                            if($gettask['comment'] != '')
                                $allQuestionComments.= 'Grade Comment: '.$gettask['comment'].";  ";
                            $r_date_completed = ($gettask['worked_date'] != '') ? date("m-d-Y H:i:s", strtotime($gettask['worked_date'])) : '';
                            $r_owner = isset($all_users[$gettask['record_owner']]) ? $all_users[$gettask['record_owner']] : '';
                            $r_last_modified_by = isset($all_users[$gettask['last_modified_by']]) ? $all_users[$gettask['last_modified_by']] : '';
                            $r_answers = json_decode($gettask['answers'], true);
                        }
                    }
                    $Review_cells = array(
                        WriterEntityFactory::createCell($Account_Reference),
                        WriterEntityFactory::createCell($Current_Balance),
                        WriterEntityFactory::createCell($Delinquency_Flag),
                    );

                    if (!empty($r_answers)) {
                        // echo "<pre>";print_r($all_questions);die;
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
                                $allQuestionComments.= $questionTitleSqlresult['export_heading'].":".$question_comment.";  ";
                                $choicesDecoded = json_decode($questionTitleSqlresult['choices']);
                                $commentRequired = '0';
                                foreach($choicesDecoded as $eachDecodedChoice){
                                    if($eachDecodedChoice->choice == $question_value && $eachDecodedChoice->status == '1'){
                                        $commentRequired = '1';
                                    }
                                }

                                $red_style = (new StyleBuilder())->setBackgroundColor(Color::RED)->setFontBold()->build();
                                if($commentRequired)
                                    array_push($Review_cells, WriterEntityFactory::createCell($question_value, $red_style));
                                else
                                    array_push($Review_cells, WriterEntityFactory::createCell($question_value));
                                        
                                // if ($sq['comment_required'] == 1) {
                                    // array_push($Review_cells, WriterEntityFactory::createCell($question_comment, $red_style));
                                // }
                                $r_exception = 1;
                            } else {
                                array_push($Review_cells, WriterEntityFactory::createCell($question_value));
                                // if ($sq['comment_required'] == 1) {
                                    // array_push($Review_cells, WriterEntityFactory::createCell($question_comment));
                                // }

                            }
                        }
                    } 
                    else {
                        foreach ($all_questions as $qid => $sq) {
                            array_push($Review_cells, WriterEntityFactory::createCell(''));
                            // if ($sq['comment_required'] == 1) {
                            //     array_push($Review_cells, WriterEntityFactory::createCell(''));
                            // }
                        }
                    }

                    // array_push($Review_cells, ...array(WriterEntityFactory::createCell($r_grade), WriterEntityFactory::createCell($r_comment), WriterEntityFactory::createCell($r_exception), WriterEntityFactory::createCell($review_status), WriterEntityFactory::createCell($r_date_completed), WriterEntityFactory::createCell($r_owner)));
                    array_push($Review_cells, ...array(WriterEntityFactory::createCell($r_grade), WriterEntityFactory::createCell($allQuestionComments), WriterEntityFactory::createCell($r_exception), WriterEntityFactory::createCell($review_status), WriterEntityFactory::createCell($r_date_completed), WriterEntityFactory::createCell($r_owner), WriterEntityFactory::createCell($r_last_modified_by)));

                    // $add_row = WriterEntityFactory::createRow($cells);
                    // $writer->addRow($add_row);
                    $multipleReviewRows[] = WriterEntityFactory::createRow($Review_cells);

                    // integrity
                    $sql2 = "SELECT * FROM $integrity_dbtable WHERE `row_id`=$rowid and status = '2' LIMIT 1";
                    
                    $result2 = $conn->query($sql2);
                    // echo "<pre>";print_r($result2->fetch_assoc());die;
                    $i_answers = array();
                    if ($result2->num_rows > 0) {
                        $integrity_cells = array(
                            WriterEntityFactory::createCell($Account_Reference),
                            WriterEntityFactory::createCell($Current_Balance),
                        );
                        while ($gettask2 = $result2->fetch_assoc()) {
                            $intergrity_summary_counter['total_rows']++;
                            $i_answers = json_decode($gettask2['answers']);
                            $do_exceptions_exist = 0;
                            $exception ='FALSE';
                            $i_date_completed = $i_owner = '';

                            if($gettask2['status'] == '2')
                                $intergrity_summary_counter['total_reviewed_rows']++;
                            if(!is_null($i_answers)){
                                foreach($i_answers as $iid=> $ia){
                                    $column_name = '';
                                    $i_key_answer_sql = "SELECT row_details FROM $data_dbtable WHERE `id`=$rowid and `project_id` = $project_id LIMIT 1";
                                    $key_node = $conn->query($i_key_answer_sql);
                                    if ($key_node->num_rows > 0) {
                                        $res = $key_node->fetch_assoc();
                                        $each_key_node_res = json_decode($res['row_details']);
                                        $column_name = $ia->column_name;
                                        
                                    }
                                    // array_push($integrity_cells, WriterEntityFactory::createCell($ia->column_name));
                                    
                                    $status = 'FALSE'; 
                                    $comment = '';
                                    if($ia->status == 'Match'){
                                        $status = 'TRUE';
                                    }
                                    else{
                                        $intergrity_summary_counter[$ia->column_name]['mismatch'] ++;
                                        // if(is_numeric($each_key_node_res->$column_name))
                                        //     $intergrity_summary_counter[$ia->column_name]['total_mismatch'] = $intergrity_summary_counter[$ia->column_name]['total_mismatch'] +$each_key_node_res->$column_name;

                                        $intergrity_summary_counter[$ia->column_name]['total_mismatch'] = $intergrity_summary_counter[$ia->column_name]['total_mismatch'] +$Current_Balance;
                                    }
                                    
                                    // if(is_numeric($each_key_node_res->$column_name))
                                    //         $intergrity_summary_counter[$ia->column_name]['total'] = $intergrity_summary_counter[$ia->column_name]['total'] +$each_key_node_res->$column_name;

                                    $intergrity_summary_counter[$ia->column_name]['total'] = $intergrity_summary_counter[$ia->column_name]['total'] +$Current_Balance;
                                    
                                    // if(!$status){
                                    //     // $red_bg = (new StyleBuilder())->setBackgroundColor(Color::RED)->build();
                                    //     $font_style = (new StyleBuilder())->setBackgroundColor(Color::RED)->setFontBold()->build();
                                    //     array_push($integrity_cells, WriterEntityFactory::createCell($status,$font_style));
                                    // }
                                    // else{
                                    //     array_push($integrity_cells, WriterEntityFactory::createCell($status));
                                    // }

                                    if(!is_null($ia->comment)){
                                        $comment = $ia->comment;
                                        $do_exceptions_exist = '1';
                                        $font_style = (new StyleBuilder())->setBackgroundColor(Color::RED)->setFontBold()->build();
                                    }
                                    else{
                                        $font_style = (new StyleBuilder())->build();
                                    }
                                    array_push($integrity_cells, WriterEntityFactory::createCell($each_key_node_res->$column_name,$font_style));
                                    WriterEntityFactory::createCell($ia->column_name);
                                    array_push($integrity_cells, WriterEntityFactory::createCell($status,$font_style));
                                    array_push($integrity_cells, WriterEntityFactory::createCell($comment,$font_style));
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
                                array_push($integrity_cells, ...array(WriterEntityFactory::createCell($exception), WriterEntityFactory::createCell($integrity_status), WriterEntityFactory::createCell($i_date_completed), WriterEntityFactory::createCell($i_owner), WriterEntityFactory::createCell($i_last_modified_by)));
                                $multipleIntegrityRows[] = WriterEntityFactory::createRow($integrity_cells);
                            }
                        }
                    }   
                }
            }
            // echo $exit_flag;die;
            //integrity summary sheet

            // echo "<pre>";print_r($intergrity_summary_counter);die;
            if($exit_flag != 0){
           
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

                    array_push($integrity_summary_cells, ...array(WriterEntityFactory::createCell($each_integrity_col), 
                        WriterEntityFactory::createCell($intergrity_summary_counter[$each_integrity_col]['mismatch']), 
                        WriterEntityFactory::createCell($grand_total_mismtach_percent), 
                        WriterEntityFactory::createCell($intergrity_summary_counter[$each_integrity_col]['total_mismatch']),
                        WriterEntityFactory::createCell($grand_total_of_mismatch_percent))
                    );
                    $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells);                    
                }
                // echo "<pre>";print_r($intergrity_summary_counter);die;
                // row - total of integrity summary
                $totalIntegrityMismatchPercentage = '0';
                if(count($integrity_cols) > 0 && $intergrity_summary_counter['total_reviewed_rows'] > 0)
                    $totalIntegrityMismatchPercentage = ($total['grand_total_mismatch'] / (count($integrity_cols) * $intergrity_summary_counter['total_reviewed_rows']))*100; 

                $integrity_summary_cells = array(
                    WriterEntityFactory::createCell($total['total']),
                    WriterEntityFactory::createCell($total['grand_total_mismatch']),
                    // WriterEntityFactory::createCell($total['grand_total_mismtach_percent']),
                    WriterEntityFactory::createCell($totalIntegrityMismatchPercentage),
                    // WriterEntityFactory::createCell($total['grand_total_of_mismtach_percent']),
                    // WriterEntityFactory::createCell($total['grand_total_of_total_mismtach_percent']),
                );
                $head_style2 = (new StyleBuilder())->setFontBold()->build();
                $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells); 
                
                // Summary of integrity summary
                $integrity_summary_cells = array(
                    WriterEntityFactory::createCell(''),
                    WriterEntityFactory::createCell('Summary'),
                );
                $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells, $head_style2); 
                $integrity_summary_cells = array(
                    WriterEntityFactory::createCell('Field'),
                    WriterEntityFactory::createCell('#'),
                    WriterEntityFactory::createCell('%'),
                    WriterEntityFactory::createCell('$'),
                    WriterEntityFactory::createCell('%'),
                );

                $exception_percentage = ($intergrity_summary_counter['total_reviewed_rows'] != '0' ?($intergrity_summary_counter['exceptions']/$intergrity_summary_counter['total_reviewed_rows'])*100 : '0');
                $exception_balance_percentage = ($intergrity_summary_counter['exceptions_total_balance'] != '0'? ($intergrity_summary_counter['exceptions_balance']/$intergrity_summary_counter['exceptions_total_balance'])*100: '0');
                $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells, $head_style2);
                // $totalIntegrityCurrentBalance = ($intergrity_summary_counter['exceptions_total_balance'] != '0'? ($intergrity_summary_counter['exceptions_total_balance']/$intergrity_summary_counter['integrity_total_current_balance'])*100: '0');
                $integrity_summary_cells = array(
                    WriterEntityFactory::createCell('Total number of loans with differences'),
                    WriterEntityFactory::createCell($intergrity_summary_counter['exceptions']),
                    WriterEntityFactory::createCell($exception_percentage),
                    WriterEntityFactory::createCell($intergrity_summary_counter['exceptions_balance']),
                    WriterEntityFactory::createCell($exception_balance_percentage),
                );
                $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells); 
                $integrity_summary_cells = array(
                    WriterEntityFactory::createCell('Total number of loans with no differences'),
                    WriterEntityFactory::createCell($intergrity_summary_counter['total_reviewed_rows'] - $intergrity_summary_counter['exceptions']),
                    WriterEntityFactory::createCell(100-$exception_percentage),
                    WriterEntityFactory::createCell($intergrity_summary_counter['exceptions_total_balance'] - $intergrity_summary_counter['exceptions_balance']),
                    WriterEntityFactory::createCell(100-$exception_balance_percentage),
                );
                $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells); 
                $integrity_summary_cells = array(
                    WriterEntityFactory::createCell('Total Number of Reviewed Loans'),
                    WriterEntityFactory::createCell($intergrity_summary_counter['total_reviewed_rows']),
                    // WriterEntityFactory::createCell($intergrity_summary_counter['total_rows'] != '0' ?($intergrity_summary_counter['total_reviewed_rows']/$intergrity_summary_counter['total_rows'])*100 : '0'),
                    WriterEntityFactory::createCell(100),
                    WriterEntityFactory::createCell($intergrity_summary_counter['exceptions_total_balance']),
                    WriterEntityFactory::createCell(100),
                );
                $multipleIntegritySummaryRows [] = WriterEntityFactory::createRow($integrity_summary_cells); 

                if($only_integrity == '1'){
                    $writer->setCurrentSheet($integrity_sheet);
                }
                else{
                    $writer->setCurrentSheet($review_sheet);
                    $writer->addRows($multipleReviewRows);

                    $writer->setCurrentSheet($integrity_sheet);
                }

                // $writer->setCurrentSheet($review_sheet);
                // $writer->addRows($multipleReviewRows);

                // $writer->setCurrentSheet($integrity_sheet);
                $writer->addRows($multipleIntegrityRows);

                $writer->setCurrentSheet($integrity_summary_sheet);
                $writer->addRows($multipleIntegritySummaryRows);
            }
        }

        $writer->close();
        // if($exit_flag != 0){
            echo env('LARAVEL_SITE_URL').'/public/files/exports/'.$export_filename;
        // }
        // else{
        //     echo 'Account_Reference, Current_Balance, Delinquency_Flag fields cannot have blank values';
        // }
        // echo $full_exportfile_path; EXPORT_FILESPATH
        

    }

    exit;

    $writer = WriterEntityFactory::createXLSXWriter();
    // $writer = WriterEntityFactory::createODSWriter();
    // $writer = WriterEntityFactory::createCSVWriter();

    $writer->openToFile($filePath); // write data to a file or to a PHP stream
    //$writer->openToBrowser($fileName); // stream data directly to the browser

    $cells = [
        WriterEntityFactory::createCell('Carl'),
        WriterEntityFactory::createCell('is'),
        WriterEntityFactory::createCell('great!'),
    ];

    /** add a row at a time */
    $singleRow = WriterEntityFactory::createRow($cells);
    $writer->addRow($singleRow);

    /** add multiple rows at a time */
    $multipleRows = [
        WriterEntityFactory::createRow($cells),
        WriterEntityFactory::createRow($cells),
    ];
    $writer->addRows($multipleRows);

    /** Shortcut: add a row from an array of values */
    $values = ['Carl', 'is', 'great!'];
    $rowFromValues = WriterEntityFactory::createRowFromArray($values);
    $writer->addRow($rowFromValues);

    $writer->close();

    print_r($project_details);
    exit;



} else {
    $return_data['status'] = 0;
    $return_data['message'] = 'Invalid Request';
    $return_data['error'] = 'Error';
}
echo json_encode($return_data);
exit;

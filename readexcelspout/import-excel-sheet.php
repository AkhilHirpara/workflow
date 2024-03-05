<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require '../vendor/autoload.php';
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

/*
    clients sheet - investor table
*/
$filePath = dirname(__FILE__).'/excel-import-db/export-existing-qflow.xlsx';
$reader = ReaderEntityFactory::createReaderFromFile($filePath);
$reader->open($filePath);

#tables
$investorTable = 'investors';
$platformTable = 'platforms';
$templatesTable = 'templates';

// $clientSheetHeader = array('Id','Date_Added','Date_Disabled','Name');
$clientSheetFlag = $creditorSheetFlag = $questionTypeSheetFlag = $questionsSheetFlag = $questionRequiredTemplateSheet = 0;
$questionTypeArr = array();
$questionMappingArr = array();

$conn = new mysqli('localhost', 'root', '', 'workflow-management');
// Check connection
if ($conn->connect_errno) {
    $return_data['status'] = 0;
    $return_data['message'] = 'Unable to connect to database';
    $return_data['error'] = "Failed to connect to MySQL: " . $conn->connect_error;
    echo json_encode($return_data);
    exit;
} 

foreach ($reader->getSheetIterator() as $sheet) {
    #clients tab
    if ($sheet->getName() === 'Clients') {
        $tempInvestor = array();
        foreach ($sheet->getRowIterator() as $clientSheetKey => $row) {
            if(!$clientSheetFlag) {
                $clientSheetFlag = true;
                continue;
            }
            
            $cells = $row->getCells();
            #created at
            $tempInvestor[$clientSheetKey]['created_at'] = $cells[1]->getValue()->format('Y-m-d H:i:s');
            $tempInvestor[$clientSheetKey]['name'] = $cells[3]->getValue();
            $tempInvestor[$clientSheetKey]['status'] = '1';
            $tempInvestor[$clientSheetKey]['created_by'] = '1';
        }
        if(!empty($tempInvestor)){
            $truncInvestorsSql = 'TRUNCATE TABLE '.$investorTable;
            if (!mysqli_query($conn, $truncInvestorsSql))
            {
                die('Error: ' . mysqli_error());
            }
            $investorSqlQueryArr = array();
            foreach($tempInvestor as $eachTempInvestor){
                $investorSqlQueryArr[] = '("'.$eachTempInvestor['name'].'", "'.$eachTempInvestor['status'].'", "'.$eachTempInvestor['created_by'].'", "'.$eachTempInvestor['created_at'].'")';
            }
            mysqli_query($conn, 'INSERT INTO '.$investorTable.' (name, status, created_by, created_at) VALUES '.implode(',', $investorSqlQueryArr));
        }
    }

    #Creditors tab
    if ($sheet->getName() === 'Creditors') {
        $tempPlatform = array();
        foreach ($sheet->getRowIterator() as $creditorsSheetKey => $row) {
            if(!$creditorSheetFlag) {
                $creditorSheetFlag = true;
                continue;
            }
            $cells = $row->getCells();
            #created at
            $tempPlatform[$creditorsSheetKey]['created_at'] = $cells[1]->getValue()->format('Y-m-d H:i:s');
            $tempPlatform[$creditorsSheetKey]['name'] = $cells[3]->getValue();
            $tempPlatform[$creditorsSheetKey]['status'] = '1';
            $tempPlatform[$creditorsSheetKey]['created_by'] = '1';
        }
        if(!empty($tempPlatform)){
            $truncPlatformSql = 'TRUNCATE TABLE '.$platformTable;
            if (!mysqli_query($conn, $truncPlatformSql))
            {
                die('Error: ' . mysqli_error());
            }
            $platformSqlQueryArr = array();
            foreach($tempPlatform as $eachTempPlatform){
                $platformSqlQueryArr[] = '("'.$eachTempPlatform['name'].'", "'.$eachTempPlatform['status'].'", "'.$eachTempPlatform['created_by'].'", "'.$eachTempPlatform['created_at'].'")';
            }
            mysqli_query($conn, 'INSERT INTO '.$platformTable.' (name, status, created_by, created_at) VALUES '.implode(',', $platformSqlQueryArr));
        }
    }

    #question_type tab
    if ($sheet->getName() === 'Question_Type') {
        foreach ($sheet->getRowIterator() as $questionTypeSheetKey => $row) {
            if(!$questionTypeSheetFlag) {
                $questionTypeSheetFlag = true;
                continue;
            }
            $cells = $row->getCells();
            $id = $cells[0]->getValue();
            #created at
            $questionTypeArr[$id]['created_at'] = $cells[1]->getValue()->format('Y-m-d H:i:s');
            $questionTypeArr[$id]['name'] = $cells[3]->getValue();
            $questionTypeArr[$id]['order'] = $cells[4]->getValue();
        }
    }

    #Question_required_template tab
    if ($sheet->getName() === 'Question_Required_Template') {
        $tempQRTemplates = array();
        foreach ($sheet->getRowIterator() as $questionsRequiredSheetKey => $row) {
            if(!$questionRequiredTemplateSheet) {
                $questionRequiredTemplateSheet = true;
                continue;
            }
            $cells = $row->getCells();
            $tempQRTemplates[$questionsRequiredSheetKey]['name'] = $cells[3]->getValue();
            #is active
            $isActive = 1;
            if($cells[4]->getValue() != '0')
                $isActive = 0;
            $tempQRTemplates[$questionsRequiredSheetKey]['status'] = $isActive;
            $tempQRTemplates[$questionsRequiredSheetKey]['created_by'] = '1';
            $tempQRTemplates[$questionsRequiredSheetKey]['created_at'] = $cells[1]->getValue()->format('Y-m-d H:i:s');
        }
        if(!empty($tempQRTemplates)){
            $truncTemplatesSql = 'TRUNCATE TABLE '.$templatesTable;
            if (!mysqli_query($conn, $truncTemplatesSql))
            {
                die('Error: ' . mysqli_error());
            }
            $templatesSqlQueryArr = array();
            foreach($tempQRTemplates as $eachQRTemplates){
                $templatesSqlQueryArr[] = '("'.$eachQRTemplates['name'].'", "'.$eachQRTemplates['status'].'", "'.$eachQRTemplates['created_by'].'", "'.$eachQRTemplates['created_at'].'")';
            }
            mysqli_query($conn, 'INSERT INTO '.$templatesTable.' (name, status, created_by, created_at) VALUES '.implode(',', $templatesSqlQueryArr));
        }
    }

    #questions tab
    if ($sheet->getName() === 'Questions') {
        $tempQuestions = array();
        foreach ($sheet->getRowIterator() as $questionsSheetKey => $row) {
            if(!$questionsSheetFlag) {
                $questionsSheetFlag = true;
                continue;
            }
            $cells = $row->getCells();
            $tempQuestions[$questionsSheetKey]['category'] = $cells[1]->getValue();
            $tempQuestions[$questionsSheetKey]['export_heading'] = $cells[4]->getValue();
            #comment required
            $commentRequiredFlag = 1;
            if($cells[5]->getValue() == '0')
                $commentRequiredFlag = 0;
            $tempQuestions[$questionsSheetKey]['comment_required'] = $commentRequiredFlag;
            $tempQuestions[$questionsSheetKey]['question'] = $cells[6]->getValue();
            $tempQuestions[$questionsSheetKey]['created_at'] = $cells[2]->getValue()->format('Y-m-d H:i:s');
        }
        echo "<pre>";print_r($tempQuestions);die;
    }

    #Question_Required_Template_Item tab

}

$reader->close();
?>
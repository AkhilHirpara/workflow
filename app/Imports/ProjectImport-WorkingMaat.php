<?php


namespace App\Imports;

use App\Models\ImportData;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use App\Models\Files;
use App\Models\User;
use App\Models\ImportLogs;


use Maatwebsite\Excel\Concerns\WithValidation;

use Throwable;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Facades\Lang;
use Maatwebsite\Excel\Events\BeforeImport;

HeadingRowFormatter::default('none');


class ProjectImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueue, WithEvents, SkipsOnFailure
{

    use RegistersEventListeners;
    use SkipsFailures;
    public $tries = 3;
    public $backoff = 3;    //The number of seconds to wait before retrying the job.


    public function  __construct(Files $file, User $currentuser)
    {
        $this->file = $file;
        $this->currentuser = $currentuser;
    }

    /**
     * @param Collection $collection
     */
    public function model(array $row)
    {
        if (!empty($row)) {
            return new ImportData([
                'file_id'  => $this->file->id,
                'row_details' => json_encode($row),
                'task_status' => 0,
            ]);
        }
    }

    // public function rules(): array
    // {
    //     return [
    //         0 => ['required'],
    //     ];
    // }

    public function batchSize(): int
    {
        return 3000;
    }
    public function chunkSize(): int
    {
        return 3000;
    }



    // public function registerEvents(): array
    // {
    //     return [
    //         ImportFailed::class => function (ImportFailed $event) {
    //             $find_file = Files::find(1);
    //             $find_file->import_status = 33;
    //             $find_file->save();
    //             $importer = $event->getException();
    //             DB::table('Testing_Table')->insert(
    //                 array(
    //                     'error'     =>   '33',
    //                 )
    //             );
    //         },
    //     ];
    // }


    // When import start
    public function beforeImport(BeforeImport $event)
    {
        $importer = $event->getConcernable();

        $fileid = $importer->file->id;
        $find_file = Files::find($fileid);
        $find_file->import_start_time = time();
        $totalrows = $event->getReader()->getTotalRows();
        if (!empty($totalrows)) {
            $find_file->total_rows = reset($totalrows);
        }
        $find_file->save();
        $update_logs = ImportLogs::create(['file_id' => $fileid, 'import_status' => 2]);
        if (!$update_logs) {
            addlog('Failed', 'Import', "Failed to add/update record in import_logs table ", $this->currentuser->id);
        }
    }

    // After import success
    public function afterImport(AfterImport $event)
    {
        $importer = $event->getConcernable();
        $fileid = $importer->file->id;
        $currentuserid = $importer->currentuser->id;
        $find_file = Files::find($fileid);
        $find_file->import_status = 1;
        $find_file->import_end_time = time();
        $find_file->save();
        $find_importlog = ImportLogs::where('file_id', $fileid)->first();
        $find_importlog->import_status = 1;
        if (!$find_importlog->save()) {
            addlog('Failed', 'Import', "Failed to add/update record in import_logs table ", $currentuserid);
        }
        addlog('Success', 'Import', Lang::get('validation.logs.import_queue_success', ['fileid' => $fileid]), $currentuserid);
    }

    // After import Failed action
    public function onFailure(Failure ...$f)
    {
        $find_file = Files::find($this->file->id);
        $find_file->import_status = 0;
        $find_file->save();

        $find_importlog = ImportLogs::where('file_id', $find_file->id)->first();
        $find_importlog->import_status = 0;
        $find_importlog->response = json_encode($f);
        if (!$find_importlog->save()) {
            addlog('Failed', 'Import', "Failed to add/update record in import_logs table ", $this->currentuser->id);
        }
        addlog('Failed', 'Import', Lang::get('validation.logs.import_queue_failed', ['fileid' => $this->file->id]), $this->currentuser->id);
    }

    // After import failed during chunk reading
    public function failed(Exception $e)
    {
        if (!empty($this->file)) {
            $find_file = Files::find($this->file->id);
            $find_file->import_status = 0;
            $find_file->save();

            $find_importlog = ImportLogs::where('file_id', $find_file->id)->first();
            $find_importlog->import_status = 0;
            $find_importlog->response = json_encode($e);
            if (!$find_importlog->save()) {
                addlog('Failed', 'Import', "Failed to add/update record in import_logs table ", $this->currentuser->id);
            }
            addlog('Failed', 'Import', Lang::get('validation.logs.import_queue_failed', ['fileid' => $this->file->id]), $this->currentuser->id);
        }
    }
}

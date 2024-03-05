<?php


namespace App\Imports;

use App\Models\ImportData;
use Illuminate\Support\Collection;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Files;
use App\Models\User;
use App\Models\ImportLogs;

use Illuminate\Support\Facades\Hash;
use Nikazooz\Simplesheet\Concerns\ToModel;
use Nikazooz\Simplesheet\Imports\HeadingRowFormatter;
use Nikazooz\Simplesheet\Concerns\WithEvents;
use Nikazooz\Simplesheet\Events\BeforeImport;
use Nikazooz\Simplesheet\Events\AfterImport;
use Nikazooz\Simplesheet\Events\ImportFailed;
use Nikazooz\Simplesheet\Concerns\Importable;
use Nikazooz\Simplesheet\Concerns\WithBatchInserts;
use Nikazooz\Simplesheet\Concerns\WithHeadingRow;

use Throwable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;


HeadingRowFormatter::default('none');


class ProjectImport implements ToModel, WithHeadingRow, ShouldQueue, WithBatchInserts, WithEvents
{
    // use RegistersEventListeners;
    // use SkipsFailures;
    public $tries = 3;
    public $backoff = 3;    //The number of seconds to wait before retrying the job.

    // private $rows = 0;


    
    public function  __construct(Files $file, User $currentuser)
    {
        $this->file = $file;
        $this->currentuser = $currentuser;
    }

    public function model(array $row)
    {
        if (!empty($row)) {
            // ++$this->rows;
            // $find_file = Files::find($this->file->id);
            // $find_file->imported_rows = $this->rows;
            // $find_file->save();

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
        return 1000;
    }


    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                if (!empty($this->file)) {
                    $error_msg = $event->getException();

                    $find_file = Files::find($this->file->id);
                    $find_file->import_status = 0;
                    $find_file->save();

                    $find_importlog = ImportLogs::where('file_id', $find_file->id)->first();
                    if (!empty($find_importlog)) {
                        $find_importlog->import_status = 0;
                        $find_importlog->response = json_encode($error_msg);
                        $find_importlog->save();
                    } else {
                        $update_logs = ImportLogs::create(['file_id' => $this->file->id, 'import_status' => 0, 'response' => json_encode($error_msg)]);
                    }
                    addlog('Failed', 'Import', Lang::get('validation.logs.import_queue_failed', ['fileid' => $this->file->id]), $this->currentuser->id);
                }
            },
            BeforeImport::class => function (BeforeImport $event) {
                // $importer = $event->getConcernable();
                // $fileid = $importer->file->id;
                $current_time = currenthumantime();
                $find_file = Files::find($this->file->id);
                $find_file->import_start_time = $current_time;
                // $totalrows = $event->getReader()->getTotalRows();
                // if (!empty($totalrows)) {
                //     $find_file->total_rows = reset($totalrows);
                // }
                $find_file->save();
                $update_logs = ImportLogs::create(['file_id' => $this->file->id, 'import_status' => 2]);
                // DB::table('import_data_phpexcel')->insert(
                //     array(
                //         'file_id'     =>   '11',
                //         'project_id'   =>   '11',
                //         'row_details' => json_encode($event->getConcernable()),
                //         'task_status' =>  0,
                //         'created_at' => $current_time,
                //         'updated_at' => $current_time
                //     )
                // );
            },
            AfterImport::class => function (AfterImport $event) {
                $fileid = $this->file->id;
                $currentuserid = $this->currentuser->id;
                $current_time = currenthumantime();
                $find_file = Files::find($fileid);
                $find_file->import_status = 1;
                $find_file->import_end_time = $current_time;
                $find_file->save();
                $find_importlog = ImportLogs::where('file_id', $fileid)->first();
                if (!empty($find_importlog)) {
                    $find_importlog->import_status = 1;
                    $find_importlog->response = json_encode('Success');
                    $find_importlog->save();
                } else {
                    $update_logs = ImportLogs::create(['file_id' => $this->file->id, 'import_status' => 1, 'response' => json_encode('Success')]);
                }
                addlog('Success', 'Import', Lang::get('validation.logs.import_queue_success', ['fileid' => $fileid]), $currentuserid);
            },
        ];
    }

}

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

use Throwable;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Concerns\SkipsOnError;

HeadingRowFormatter::default('none');


// class ProjectImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueue, WithEvents, SkipsOnFailure
class ProjectImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueue, WithEvents
{

    use RegistersEventListeners;
    public $tries = 3;
    public $backoff = 3;    //The number of seconds to wait before retrying the job.


    public function  __construct(Files $file)
    {
        $this->file = $file;
    }

    /**
     * @param Collection $collection
     */
    public function model(array $row)
    {
        try {
            if (!empty($row)) {
                return new ImportData([
                    'file_id'  => $this->file->id,
                    'row_details' => json_encode($row),
                ]);
            }
        } catch (\Exception $e) {
            $find_file = Files::find(1);
            $find_file->import_status = 44;
            $find_file->save();
            DB::table('Testing_Table')->insert(
                array(
                    'error'     =>   '44',
                )
            );
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
        return 5000;
    }
    public function chunkSize(): int
    {
        return 5000;
    }

    // public function registerEvents(): array
    // {
    //     return [
    //         ImportFailed::class => function (ImportFailed $event) {
    //             $find_file = Files::find(1);
    //             $find_file->import_status = 55;
    //             $find_file->save();
    //             // $importer = $event->getException();
    //             DB::table('Testing_Table')->insert(
    //                 array(
    //                     'error'     =>   '55',
    //                 )
    //             );
    //         },
    //     ];
    // }


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

    // After import success
    public function afterImport(AfterImport $event)
    {
        $importer = $event->getConcernable();
        $fileid = $importer->file->id;
        $find_file = Files::find($fileid);
        $find_file->import_status = 1;
        $find_file->save();
    }

    // public function onFailure(Failure ...$f)
    // {
    //     $find_file = Files::find(1);
    //     $find_file->import_status = 66;
    //     $find_file->save();
    //     // $importer = $e->getException();
    //     DB::table('Testing_Table')->insert(
    //         array(
    //             'error'     =>   '66',
    //         )
    //     );
    // }


}

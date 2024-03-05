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

class ProjectImportTesting implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts,ShouldQueue
{
    /**
     * @param Collection $collection
     */
    public function model(array $row)
    {
        return new ImportData([
            'project_id'  => 1,
            'row_details' => json_encode($row),
        ]);
    }
    // public function rules(): array
    // {
    //     return [
    //         0 => ['required'],
    //     ];
    // }

    public function batchSize(): int
    {
        return 10000;
    }
    public function chunkSize(): int
    {
        return 10000;
    }
}

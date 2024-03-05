<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    
    protected $commands = [

       Commands\Deletedownloadedfile::class,
       Commands\ArchieveProject::class

   ];
    
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // Custom schedule - As client said there can be 2 import at same time
        // File import 1
        $schedule->call(function () {
            $pending_imports = DB::table('files')
                ->where('import_status', '=', 2)
                ->where('type', 'Import')
                ->take(1)->get();
            if ($pending_imports->count()) {
                $pending_imports = $pending_imports->toArray();
                $file_name = $pending_imports[0]->filename;
                $file_id = $pending_imports[0]->id;
                $json = json_encode(array('fileid' => $file_id, 'filename' => $file_name));
                $siteurl = env('LARAVEL_SITE_URL');
                $url = $siteurl . '/readexcelspout/readfile.php';
                // $ch = curl_init($url);
                // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // curl_setopt($ch, CURLOPT_TIMEOUT, 200);
                // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                // curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                // $result = curl_exec($ch);
                // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                // if (curl_errno($ch)) {
                //     $error_msg = curl_error($ch);
                // }
                // curl_close($ch);
                // $final_result = json_decode($result, true);



                $ch = curl_init();
                $headers = array(
                    'Accept: application/json',
                    'Content-Type: application/json',

                );
                curl_setopt($ch, CURLOPT_URL, $url . '?fileid=' . $file_id . '&filename=' . urlencode($file_name));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (curl_errno($ch)) {
                    $error_msg = curl_error($ch);
                }
                curl_close($ch);
                $final_result = json_decode($result, true);
                if ($httpCode == 200) {
                    // echo "Success";
                    DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($final_result)]);
                    // $final_result = json_decode($result, true);
                } else {
                    // echo "Failed";
                    if (isset($error_msg)) {
                        DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($error_msg)]);
                    } else {
                        DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($result)]);
                    }
                }
                // echo $httpCode;
                // exit;
            }
        })->name('import_file1')->withoutOverlapping()->everyMinute()->when(function () {
            $to_continue = 0;
            $pending_imports = DB::table('files')
                ->where('import_status', '=', 2)
                ->where('type', 'Import')
                ->take(1)->get();
            if ($pending_imports->count()) {
                $pending_imports = $pending_imports->toArray();
                $file_name = $pending_imports[0]->filename;
                $excelfile_folder = env('COMPLETE_PROJECT_IMPORT_FILESPATH');
                $file_path = $excelfile_folder . $file_name;
                if (file_exists($file_path)) {
                    $to_continue = 1;
                } else {
                    // echo "File does not exist.";
                    DB::table('files')->where('id', $pending_imports[0]->id)->update(['import_status' => 0]);
                }
            }
            if ($to_continue == 1) {
                return true;
            }
        });

        // File import 2
        $schedule->call(function () {
            $pending_imports = DB::table('files')
                ->where('import_status', '=', 2)
                ->where('type', 'Import')
                ->skip(1)->take(1)->get();
            if ($pending_imports->count()) {
                $pending_imports = $pending_imports->toArray();
                $file_name = $pending_imports[0]->filename;
                $file_id = $pending_imports[0]->id;
                $json = json_encode(array('fileid' => $file_id, 'filename' => $file_name));
                $siteurl = env('LARAVEL_SITE_URL');
                $url = $siteurl . '/readexcelspout/readfile.php';

                $ch = curl_init();
                $headers = array(
                    'Accept: application/json',
                    'Content-Type: application/json',

                );
                curl_setopt($ch, CURLOPT_URL, $url . '?fileid=' . $file_id . '&filename=' . urlencode($file_name));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (curl_errno($ch)) {
                    $error_msg = curl_error($ch);
                }
                curl_close($ch);
                $final_result = json_decode($result, true);
                DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($result)]);

                if ($httpCode == 200) {
                    // echo "Success";
                    DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($final_result)]);
                    // $final_result = json_decode($result, true);
                } else {
                    // echo "Failed";
                    if (isset($error_msg)) {
                        DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($error_msg)]);
                    } else {
                        DB::table('curllogs')->insert(['file_id' => $file_id, 'curl_response' => json_encode($result)]);
                    }
                }
            }
        })->name('import_file2')->withoutOverlapping()->everyMinute()->when(function () {
            $to_continue = 0;
            $pending_imports = DB::table('files')
                ->where('import_status', '=', 2)
                ->where('type', 'Import')
                ->skip(1)->take(1)->get();
            if ($pending_imports->count()) {
                $pending_imports = $pending_imports->toArray();
                $file_name = $pending_imports[0]->filename;
                $excelfile_folder = env('COMPLETE_PROJECT_IMPORT_FILESPATH');
                $file_path = $excelfile_folder . $file_name;
                if (file_exists($file_path)) {
                    $to_continue = 1;
                } else {
                    // echo "File does not exist.";
                    DB::table('files')->where('id', $pending_imports[0]->id)->update(['import_status' => 0]);
                }
            }
            if ($to_continue == 1) {
                return true;
            }
        });
        // Custom schedule-End

        // Email reminder scheduler - mail to users who have downloaded the files
        $schedule->command('daily:deletedownloadedfile')->cron('0 10 5,15,25 * *');
        // Email reminder scheduler end

        //Projects are automatically archived after 6 months if not deleted
        $schedule->command('daily:archieve-project')->daily();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
        
    }
}

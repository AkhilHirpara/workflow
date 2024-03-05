<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Project;

class ArchieveProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily:archieve-project';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Projects are automatically archived after 6 months if not deleted';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {

        $sixMonthsAgo = Carbon::now()->subMonths(6);
        //Project Archive means is_archived=1
        $projects = Project::where('created_at', '<=', $sixMonthsAgo)->where('is_archived',0)->where('status','!=',0)->update(['is_archived' => 1]);
        
        return $projects;
    }
}

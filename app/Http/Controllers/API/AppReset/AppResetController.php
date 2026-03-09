<?php

namespace App\Http\Controllers\API\AppReset;

use App\Http\Controllers\Controller;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppResetController extends Controller
{
    protected array $ignoredTables;

    protected array $truncateTables;

    protected string $masterDatabaseDriver;

    public function __construct()
    {
        $this->truncateTables = ['activity_log', 'alerts']; // Will Be Truncated Only
        $this->ignoredTables = ['jobs', 'migrations']; // No Change At All
        $this->masterDatabaseDriver = 'master_db'; // Master Database Driver Name
    }

    public function reset(): JsonResponse
    {
        if (config('app.domain_name') == 'testing' || config('app.domain_name') == 'preprod') {
            try {
                $ignoreCount = 0;
                $onlyTruncatedCount = 0;
                $tableDoesNotExistCount = 0;
                $truncatedWithoutReseated = 0;
                $reseatedTableCount = 0;
                $masterTables = DB::connection($this->masterDatabaseDriver)->select('SHOW TABLES'); // Getting All The Tables From Master Database
                $masterTables = collect($masterTables)->pluck('Tables_in_'.config('database.connections.'.$this->masterDatabaseDriver.'.database'));
                Schema::disableForeignKeyConstraints();
                foreach ($masterTables as $table) {
                    if (! in_array($table, $this->ignoredTables)) { // Checking If Table Needs To Be Ignored Or Not
                        if (in_array($table, $this->truncateTables)) { // Checking If Table Needs To Be Only Truncated Or Not
                            if (Schema::hasTable($table)) { // Checking If Table Exists Or Not
                                DB::table($table)->truncate();
                                $onlyTruncatedCount += 1;
                            } else {
                                $tableDoesNotExistCount += 1;
                            }
                        } else {
                            if (Schema::hasTable($table)) { // Checking If Table Is Exists Or Not
                                DB::table($table)->truncate(); // Truncating Current Data From Current database
                                $masterColumns = Schema::connection($this->masterDatabaseDriver)->getColumnListing($table); // Getting All Columns Name
                                $select = [];
                                /* Gathering Columns For Select */
                                foreach ($masterColumns as $masterColumn) {
                                    if (Schema::hasColumn($table, $masterColumn)) {
                                        $select[] = $masterColumn;
                                    }
                                }
                                $masterTableData = DB::connection($this->masterDatabaseDriver)->table($table)->select($select)->get(); // Getting Data From Master Database
                                if (count($masterTableData) != 0) {
                                    $data = json_decode($masterTableData, true);
                                    DB::table($table)->insert($data); // Inserting Data From Master To Current Database
                                    $reseatedTableCount += 1;
                                } else {
                                    $truncatedWithoutReseated += 1;
                                }
                            } else {
                                $tableDoesNotExistCount += 1;
                            }
                        }
                    } else {
                        $ignoreCount += 1;
                    }
                }

                $response = [
                    'ignoreCount' => $ignoreCount,
                    'onlyTruncatedCount' => $onlyTruncatedCount,
                    'tableDoesNotExistCount' => $tableDoesNotExistCount,
                    'truncatedWithoutReseated' => $truncatedWithoutReseated,
                    'reseatedTableCount' => $reseatedTableCount,
                ];

                return response()->json(['success' => true, 'message' => 'Database Reseated Successfully!!', 'data' => $response]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage().' '.$e->getLine()], 500);
            }
        } else {
            abort(404);
        }
    }

    public function migrate()
    {
        $userOrgnaization = UserOrganizationHistory::whereNotNull('effective_date')->orderBy('effective_date')->get();
        foreach ($userOrgnaization as $userOrgnaizatio) {
            UserIsManagerHistory::updateOrCreate([
                'user_id' => $userOrgnaizatio->user_id,
                'effective_date' => $userOrgnaizatio->effective_date,
            ], [
                'user_id' => $userOrgnaizatio->user_id,
                'updater_id' => Auth()->user()->id,
                'effective_date' => $userOrgnaizatio->effective_date,
                'is_manager' => $userOrgnaizatio->is_manager,
                'old_is_manager' => $userOrgnaizatio->old_is_manager,
                'position_id' => $userOrgnaizatio->position_id,
                'old_position_id' => $userOrgnaizatio->old_position_id,
                'sub_position_id' => $userOrgnaizatio->sub_position_id,
                'old_sub_position_id' => $userOrgnaizatio->old_sub_position_id,
            ]);
        }

        // $userOrgnaization = UserOrganizationHistory::whereNotNull('effective_date')->orderBy('effective_date')->get();
        foreach ($userOrgnaization as $userOrgnaizatio) {
            UserManagerHistory::updateOrCreate([
                'user_id' => $userOrgnaizatio->user_id,
                'effective_date' => $userOrgnaizatio->effective_date,
            ], [
                'user_id' => $userOrgnaizatio->user_id,
                'updater_id' => Auth()->user()->id,
                'effective_date' => $userOrgnaizatio->effective_date,
                'old_manager_id' => $userOrgnaizatio->old_manager_id,
                'manager_id' => $userOrgnaizatio->manager_id,
                'team_id' => $userOrgnaizatio->team_id,
                'old_team_id' => $userOrgnaizatio->old_team_id,
                'position_id' => $userOrgnaizatio->position_id,
                'old_position_id' => $userOrgnaizatio->old_position_id,
                'sub_position_id' => $userOrgnaizatio->sub_position_id,
                'old_sub_position_id' => $userOrgnaizatio->old_sub_position_id,
            ]);
        }
    }
}

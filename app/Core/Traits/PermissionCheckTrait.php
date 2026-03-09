<?php

namespace App\Core\Traits;

use App\Models\PermissionsubModules;
use App\Models\UserPermissions;

trait PermissionCheckTrait
{
    public function checkPermission($roleId, $moduleId, $routeName)
    {

        $moduleData = UserPermissions::with('permissionSubModule')->where('position_id', $roleId)->where('module_id', $moduleId)->get();

        $permissionUser = [];
        foreach ($moduleData as $value) {
            $permissionUser[] = $value->permissionSubModule->action;

        }
        // $subModules = PermissionsubModules::where('module_id', $moduleId)->get();
        // $permission = array();
        // foreach ($subModules as $key => $value) {
        //     $permission[] = $value->id;
        // }

        if (! in_array($routeName, array_filter($permissionUser))) {
            return false;
        } else {
            return true;
        }

    }
}

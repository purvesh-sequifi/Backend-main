<?php

namespace Database\Seeders;

use App\Models\UserPermissions;
use Illuminate\Database\Seeder;

class UserPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $newValue = [

        'data' => '[{
            "position_id": 1,
            "permissions": [
                {
                    "module_id": 2,
                    "access_id":[
                        {
                            "sub_module_id":1,
                            "permission_id":[1,2,3,4,5,6,7]
                        },
                        {
                            "sub_module_id":2,
                            "permission_id":[8,9,10,11,12,13,14]
                        }
                    ]
                },
                {
                    "module_id": 3,
                    "access_id":[
                        {
                            "sub_module_id":3,
                            "permission_id":[15,16,17,18,19]
                        },
                        {
                            "sub_module_id":4,
                            "permission_id":[20,21,22,23]
                        },
                        {
                            "sub_module_id":5,
                            "permission_id":[25,26,27]
                        }
                    ]
                },
                {
                    "module_id": 4,
                    "access_id":[
                        {
                            "sub_module_id":6,
                            "permission_id":[28,29,30,31,32,33]
                        }
                    ]
                },
                {
                    "module_id": 5,
                    "access_id":[
                        {
                            "sub_module_id":7,
                            "permission_id":[34,35,36]
                        },
                        {
                            "sub_module_id":8,
                            "permission_id":[37,38,39,40,41,42]
                        },
                        {
                            "sub_module_id":9,
                            "permission_id":[43,47,49,50,51,52,53,54]
                        }

                    ]
                },
                {
                    "module_id": 7,
                    "access_id":[
                        {
                            "sub_module_id":10,
                            "permission_id":[55,56,57,58]
                        }
                    ]
                },
                {
                    "module_id": 8,
                    "access_id":[
                        {
                            "sub_module_id":11,
                            "permission_id":[59,60,61,62,63,64]
                        },
                        {
                            "sub_module_id":12,
                            "permission_id":[65,66,67,68,69,71,71]
                        },
                        {
                            "sub_module_id":13,
                            "permission_id":[72,73,74]
                        },
                        {
                            "sub_module_id":14,
                            "permission_id":[75,76,77,78,79,80,81,82]
                        }

                    ]
                },
                {
                    "module_id": 10,
                    "access_id":[
                        {
                            "sub_module_id":16,
                            "permission_id":[85,86,87,88]
                        },
                        {
                            "sub_module_id":17,
                            "permission_id":[89,90,91,92,93]
                        },
                        {
                            "sub_module_id":18,
                            "permission_id":[94,95]
                        }

                    ]
                }
            ]
        },
        {
            "position_id": 2,
            "permissions": [
                {
                    "module_id": 2,
                    "access_id":[
                        {
                            "sub_module_id":1,
                            "permission_id":[1,2,3,4,5,6,7]
                        },
                        {
                            "sub_module_id":2,
                            "permission_id":[8,9,10,12,13,14]
                        }
                    ]
                },
                {
                    "module_id": 3,
                    "access_id":[
                        {
                            "sub_module_id":3,
                            "permission_id":[17]
                        },
                        {
                            "sub_module_id":4,
                            "permission_id":[20,21,22,23]
                        }
                    ]
                },
                {
                    "module_id": 4,
                    "access_id":[
                        {
                            "sub_module_id":6,
                            "permission_id":[29,30,32,33]
                        }
                    ]
                },
                {
                    "module_id": 7,
                    "access_id":[
                        {
                            "sub_module_id":10,
                            "permission_id":[55,56,57,58]
                        }
                    ]
                },
                {
                    "module_id": 8,
                    "access_id":[
                        {
                            "sub_module_id":13,
                            "permission_id":[72,73,74]
                        },
                        {
                            "sub_module_id":14,
                            "permission_id":[75,76,77,78,79,80,81,82]
                        }

                    ]
                },
                {
                    "module_id": 10,
                    "access_id":[
                        {
                            "sub_module_id":16,
                            "permission_id":[86,87,88]
                        }

                    ]
                }
            ]
        },
        {
            "position_id": 3,
            "permissions": [
                {
                    "module_id": 2,
                    "access_id":[
                        {
                            "sub_module_id":1,
                            "permission_id":[1,2,3,4,5,7]
                        },
                        {
                            "sub_module_id":2,
                            "permission_id":[8,9,10,12,13,14]
                        }
                    ]
                },
                {
                    "module_id": 3,
                    "access_id":[
                        {
                            "sub_module_id":3,
                            "permission_id":[17]
                        },
                        {
                            "sub_module_id":4,
                            "permission_id":[20,21,23]
                        }
                    ]
                },
                {
                    "module_id": 4,
                    "access_id":[
                        {
                            "sub_module_id":6,
                            "permission_id":[29,30,32,33]
                        }
                    ]
                },
                {
                    "module_id": 7,
                    "access_id":[
                        {
                            "sub_module_id":10,
                            "permission_id":[55,56,57,58]
                        }
                    ]
                },
                {
                    "module_id": 8,
                    "access_id":[
                        {
                            "sub_module_id":13,
                            "permission_id":[72,73,74]
                        },
                        {
                            "sub_module_id":14,
                            "permission_id":[75,76,77,78,79,80,81,82]
                        }

                    ]
                },
                {
                    "module_id": 10,
                    "access_id":[
                        {
                            "sub_module_id":16,
                            "permission_id":[86,87,88]
                        }

                    ]
                }
            ]
        },
        {
            "position_id": 4,
            "permissions": [
                {
                    "module_id": 2,
                    "access_id":[
                        {
                            "sub_module_id":1,
                            "permission_id":[1,2,3,4,5,6,7]
                        },
                        {
                            "sub_module_id":2,
                            "permission_id":[8,9,10,11,12,13,14]
                        }
                    ]
                },
                {
                    "module_id": 3,
                    "access_id":[
                        {
                            "sub_module_id":3,
                            "permission_id":[15,16,17,18,19]
                        },
                        {
                            "sub_module_id":4,
                            "permission_id":[20,21,22,23]
                        },
                        {
                            "sub_module_id":5,
                            "permission_id":[24,25,26,27]
                        }
                    ]
                },
                {
                    "module_id": 4,
                    "access_id":[
                        {
                            "sub_module_id":6,
                            "permission_id":[28,29,30,31,32,33]
                        }
                    ]
                },
                {
                    "module_id": 5,
                    "access_id":[
                        {
                            "sub_module_id":7,
                            "permission_id":[34,35,36]
                        },
                        {
                            "sub_module_id":8,
                            "permission_id":[37,38,39,40,41,42]
                        },
                        {
                            "sub_module_id":9,
                            "permission_id":[43,47,49,50,51,52,53,54]
                        }

                    ]
                },
                {
                    "module_id": 7,
                    "access_id":[
                        {
                            "sub_module_id":10,
                            "permission_id":[55,56,57,58]
                        }
                    ]
                },
                {
                    "module_id": 8,
                    "access_id":[
                        {
                            "sub_module_id":11,
                            "permission_id":[59,60,61,62,63,64]
                        },
                        {
                            "sub_module_id":12,
                            "permission_id":[65,66,67,68,69,71,71]
                        },
                        {
                            "sub_module_id":13,
                            "permission_id":[72,73,74]
                        },
                        {
                            "sub_module_id":14,
                            "permission_id":[75,76,77,78,79,80,81,82]
                        },
                        {
                            "sub_module_id":15,
                            "permission_id":[83,84]
                        }

                    ]
                },
                {
                    "module_id": 10,
                    "access_id":[
                        {
                            "sub_module_id":16,
                            "permission_id":[85,86,87,88]
                        },
                        {
                            "sub_module_id":17,
                            "permission_id":[89,90,91,92,93]
                        },
                        {
                            "sub_module_id":18,
                            "permission_id":[94,95]
                        }

                    ]
                }
            ]
        },
        {
            "position_id": 5,
            "permissions": [
                {
                    "module_id": 2,
                    "access_id":[
                        {
                            "sub_module_id":1,
                            "permission_id":[1,2,3,4,5,6,7]
                        },
                        {
                            "sub_module_id":2,
                            "permission_id":[8,9,10,11,12,13,14]
                        }
                    ]
                },
                {
                    "module_id": 3,
                    "access_id":[
                        {
                            "sub_module_id":3,
                            "permission_id":[15,16,17,18,19]
                        },
                        {
                            "sub_module_id":4,
                            "permission_id":[20,21,22,23]
                        },
                        {
                            "sub_module_id":5,
                            "permission_id":[24,25,26,27]
                        }
                    ]
                },
                {
                    "module_id": 4,
                    "access_id":[
                        {
                            "sub_module_id":6,
                            "permission_id":[28,29,30,31,32,33]
                        }
                    ]
                },
                {
                    "module_id": 5,
                    "access_id":[
                        {
                            "sub_module_id":7,
                            "permission_id":[34,35,36]
                        },
                        {
                            "sub_module_id":8,
                            "permission_id":[37,38,39,40,41,42]
                        },
                        {
                            "sub_module_id":9,
                            "permission_id":[43,47,49,50,51,52,53,54]
                        }

                    ]
                },
                {
                    "module_id": 7,
                    "access_id":[
                        {
                            "sub_module_id":10,
                            "permission_id":[55,56,57,58]
                        }
                    ]
                },
                {
                    "module_id": 8,
                    "access_id":[
                        {
                            "sub_module_id":11,
                            "permission_id":[59,60,61,62,63,64]
                        },
                        {
                            "sub_module_id":12,
                            "permission_id":[65,66,67,68,69,71,71]
                        },
                        {
                            "sub_module_id":13,
                            "permission_id":[72,73,74]
                        },
                        {
                            "sub_module_id":14,
                            "permission_id":[75,76,77,78,79,80,81,82]
                        }
                    ]
                },
                {
                    "module_id": 10,
                    "access_id":[
                        {
                            "sub_module_id":16,
                            "permission_id":[85,86,87,88]
                        },
                        {
                            "sub_module_id":17,
                            "permission_id":[89,90,91,92,93]
                        },
                        {
                            "sub_module_id":18,
                            "permission_id":[94,95]
                        }

                    ]
                }
            ]
        }

        ]',

    ];

    public function run(): void
    {
        // foreach(range(1, sizeOf($this->newValue)) as $index)
        // {
        //    $permissionVal = $this->newValue;
        //    $position = json_decode($permissionVal['data']);
        //    $count = count($position);

        //    for($i=0; $i<$count; $i++)
        //    {
        //         $positionId = $position[$i]->position_id;
        //         $permissions = $position[$i]->permissions;
        //         foreach ($permissions as $key => $value)
        //         {
        //             $module_id = $value->module_id;
        //             foreach ($value->access_id as $key1 => $subModule)
        //             {
        //                 $sub_module_id = $subModule->sub_module_id;
        //                 foreach ($subModule->permission_id as $key2 => $permission_id)
        //                 {
        //                     $datas = UserPermissions::create(
        //                         [
        //                             'position_id'  => $positionId,
        //                             'module_id'    => $module_id,
        //                             'sub_module_id'       => $sub_module_id,
        //                             'parmission_id'  => $permission_id,
        //                             'status'         => 1,
        //                         ]
        //                     );
        //                 }
        //             }
        //         }
        //     }
        // }
    }
}

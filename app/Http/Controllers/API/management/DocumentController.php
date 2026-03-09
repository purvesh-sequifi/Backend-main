<?php

namespace App\Http\Controllers\API\management;

use App\Http\Controllers\Controller;
use App\Models\DocumentFiles;
use App\Models\Documents;
use App\Models\DocumentToUpdate;
use App\Models\DocumentType;
use App\Models\GroupPermissions;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Validator;

// use Input;

class DocumentController extends Controller
{
    // New code as per changes in sequidocs
    public function DocumentListBYUserId($id)
    {
        $user = User::where('id', $id)->get();
        $documenttype = documenttype::where('id', '>', 1)->get(); // not for offer letter
        $countDocumentField = DocumentToUpdate::where('field_required', 'required')->count();
        if ($documenttype) {
            $totalDocuments = $documenttype->count();
            $documenttype->transform(function ($document_type) use ($id) {
                $document_type_id = $document_type->id;
                $documentList = Documents::select(
                    'documents.id as document_id',
                    'documents.user_id',
                    'documents.document_uploaded_type',
                    'documents.document_type_id',
                    'documents.description',
                    // document_files data
                    'document_files.id',
                    'document_files.id as document_file_id',
                    // 'document_files.signed_document_id',
                    // 'document_files.signed_status',
                    'document_files.document',
                    'document_files.signed_document',
                    'document_files.updated_at',
                    'document_files.created_at'
                )
                    ->selectraw('DATE_FORMAT(document_files.created_at, "%d/%m/%Y") as document_created_at')
                    ->Join('document_files', 'documents.id', '=', 'document_files.document_id')
                    ->where('document_type_id', $document_type_id)
                    ->where('user_id', $id)
                    ->where('documents.document_uploaded_type', 'manual_doc')
                    ->orderBy('documents.id', 'desc')
                    ->get();
                $documentFileDate = '';
                $document_Data = [];
                foreach ($documentList as $value) {
                    $documentFileDate = $value->created_at->format('m/d/Y');
                    $document_Data[] = [
                        'id' => $value->id,
                        'document' => $value->document,
                        'signed_document' => $value->signed_document,
                    ];
                }

                return [
                    'id' => $document_type->id,
                    'document_type_id' => ($document_type != null) ? $document_type->id : null,
                    'field_required' => ($document_type != null) ? $document_type->field_required : null,
                    'field_name' => ($document_type != null) ? $document_type->field_name : null,
                    'field_link' => ($document_type != null) ? $document_type->field_link : null,
                    'created_at' => $document_type->created_at->format('m/d/Y'),
                    'doc_created_at' => $documentFileDate,
                    'attachments' => count($document_Data),
                    'document' => $document_Data,
                    'documentList' => $documentList,
                    'is_deleted' => $document_type->is_deleted,
                ];

            });

            return response()->json([
                'status' => true,
                'message' => 'Successfully.',
                'total_mandatory' => $countDocumentField,
                'totalDocuments' => $totalDocuments,
                'data' => $documenttype,
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Successfully.',
            'data' => [],
        ], 400);

    }

    // old
    public function DocumentListBYUserId_16102023($id)
    {

        $user = User::where('id', $id)->get();
        // if ($user == null) {
        //    return response()->json([
        //        'status' => true,
        //        'message' => 'No record.',
        //        'totalDocuments' => 0,
        //        'data' => []
        //    ], 200);
        // }
        $Documents = documenttype::get();
        $countDocumentField = DocumentToUpdate::where('field_required', 'required')->count();
        if ($Documents) {
            $totalDocuments = $Documents->count();
            $Documents->transform(function ($document) use ($id) {

                // \DB::enableQueryLog();
                $documentData = Documents::select('document_files.document', 'document_files.created_at', 'document_files.id', 'document_files.signed_document')
                    ->Join('document_files', 'documents.id', '=', 'document_files.document_id')
                    ->where('document_type_id', $document->id)
                    ->where('user_id', $id)
                    ->orderBy('documents.id', 'desc')
                    ->limit(1, 1)
                    ->get();
                //  dd(\DB::getQueryLog());
                //  die;\
                $documentFileDate = '';
                $documentList = [];
                foreach ($documentData as $value) {
                    $documentFileDate = $value->created_at->format('m/d/Y');
                    $documentList[] = [
                        'id' => $value->id,
                        'document' => $value->signed_document,
                    ];
                }

                return [
                    'id' => $document->id,
                    'document_type_id' => ($document != null) ? $document->id : null,
                    'field_required' => ($document != null) ? $document->field_required : null,
                    'field_name' => ($document != null) ? $document->field_name : null,
                    'created_at' => $document->created_at->format('m/d/Y'),
                    'doc_created_at' => $documentFileDate,
                    'attachments' => $documentData->count(),
                    // 'document' => $documentData,
                    'document' => $documentList,
                    'is_deleted' => $document->is_deleted,

                ];

            });

            return response()->json([
                'status' => true,
                'message' => 'Successfully.',
                'total_mandatory' => $countDocumentField,
                'totalDocuments' => $totalDocuments,
                'data' => $Documents,
            ], 200);
        }
    }

    public function AddDocumentBYUserId(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'image[]' => 'mimes:jpg,png,jpeg,gif,svg,pdf|max:2048',
                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
                'user_id' => 'required',
                'document_type_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = Documents::create(
            [
                'user_id' => $request->user_id,
                'description' => $request->description,
                'document_type_id' => $request->document_type_id, 'document_uploaded_type' => 'manual_doc',
            ]
        );
        if (isset($request->image) && count($request->image) >= 1 && $request->image != null && $request->image != '') {

            $getImage = ' ';
            foreach ($request->image as $files) {
                $file = $files;
                // s3 bucket
                $img_path = time().$file->getClientOriginalName();
                $img_path = str_replace(' ', '_', $img_path);
                $awsPath = config('app.domain_name').'/'.'documents/'.$img_path;
                s3_upload($awsPath, file_get_contents($file), false);
                $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.'documents/'.$img_path);
                // s3 bucket end
                $image_path = time().$file->getClientOriginalName();
                $ex = $file->getClientOriginalExtension();
                $destinationPath = 'document';
                $image_path1 = $file->move($destinationPath, $img_path);

                $docFile = DocumentFiles::create(
                    [
                        'document_id' => $data->id,
                        'document' => 'document/'.$img_path,
                    ]
                );
            }

        }

        return response()->json([
            'api' => 'Add-document-by-userId',
            'status' => true,
            'message' => 'Successfully.',
            'id' => $docFile->id,
            'document' => $destinationPath.'/'.$img_path,
            'document_s3' => $s3_document_url,
            'uploaded_date' => date('d-m-Y', strtotime($docFile->created_at)),

            // 'data' => Collect($docFile)->except(['document_id','created_at','updated_at']),
        ], 200);
    }

    public function AddDocumentBYUserIdNew(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'image[]' => 'mimes:jpg,png,jpeg,gif,svg,pdf|max:2048',
                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
                'user_id' => 'required',
                'document_type_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $data = Documents::create(
            [
                'user_id' => $request->user_id,
                'description' => $request->description,
                'document_type_id' => $request->document_type_id,
            ]
        );
        if ($request->image) {

            foreach ($request->file('image') as $files) {
                $file = $files;
                $image_path = 'document/'.time().$file->getClientOriginalName();
                \Storage::disk('s3')->put($image_path, file_get_contents($file));
                // \Storage::disk("s3_private")->put($image_path, file_get_contents($file));

                DocumentFiles::create(
                    [
                        'document_id' => $data->id,
                        'document' => $image_path,
                    ]
                );
            }
        }

        return response()->json([
            'api' => 'Add-document-by-userId',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data
        ], 200);
    }

    public function updateDocumentListBYUserId(Request $request)
    {
        // return $request;
        $data = Documents::where('id', $request->id)->first();
        if ($data) {
            // dd($data);
            $data->user_id = $request->user_id;
            $data->document_type_id = $request->document_type_id;
            $data->description = $request->description;
            $data->save();

            if ($request->image) {
                $data1 = DocumentFiles::where('document_id', $request->id)->delete();
                foreach ($request->image as $files) {
                    $file = $files;
                    $image_path = time().$file->getClientOriginalName();
                    $ex = $file->getClientOriginalExtension();
                    $destinationPath = 'document';
                    $image_path = $file->move($destinationPath, time().$file->getClientOriginalName());
                    DocumentFiles::create(
                        [
                            'document_id' => $data->id,
                            'document' => 'document/'.$image_path,
                        ]
                    );
                }
            }
        }

        return response()->json([
            'api' => 'update-document-by-documentId',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data
        ], 200);
    }

    public function deleteDocumentListBYUserId($id): JsonResponse
    {

        if (! null == $id) {

            $documents = DocumentFiles::where('id', $id)->first();
            if ($documents == null) {
                return response()->json(['status' => true, 'message' => 'Document Id  not find.'], 200);
            } else {
                $documents = Documents::find($documents->id)->delete();
                $documentFiles = DocumentFiles::find($id)->delete();

                return response()->json([
                    'ApiName' => 'delete-document-by-documentId && user_id',
                    'status' => true,
                    'message' => 'delete Successfully.',
                    // 'data' => $documents,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'delete-document-by-documentId && user_id',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }

    }

    public function documentType(): JsonResponse
    {
        $data = DocumentType::get();

        return response()->json([
            'ApiName' => 'State List',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function DocumentList(Request $request, User $user): JsonResponse
    {
        $user = auth('api')->user();
        $positionId = auth('api')->user()->position_id;
        $group_id = $user->group_id;
        // $groupPermissions = GroupPermissions::where('group_id',$group_id)->groupBy('role_id')->pluck('role_id')->toArray();

        // if (in_array(1,$groupPermissions)) {

        //     if ($request->has('filter') && !empty($request->input('filter'))) {
        //         $users = User::where('first_name', $request->filter)
        //         ->orWhere('first_name', 'like', '%' . $request->filter . '%')->get();

        //     }else{

        //         if($request->document_type_id){

        //             $user_ids = Documents::where('document_type_id',$request->document_type_id)->groupBy('user_id')->pluck('user_id')->toArray();

        //         }else{
        //             $user_ids = Documents::groupBy('user_id')->pluck('user_id')->toArray();
        //         }

        //         $users = User::whereIn('id',$user_ids)->get();
        //     }

        // }else{
        //     if($request->document_type_id){
        //         $user_ids = Documents::where('user_id',$user->id)->where('document_type_id',$request->document_type_id)->groupBy('user_id')->pluck('user_id')->toArray();
        //     }else{

        //         $user_ids = Documents::where('user_id',$user->id)->groupBy('user_id')->pluck('user_id')->toArray();

        //     }

        //     $users = User::whereIn('id',$user_ids)->get();

        // }
        // if ($users) {
        //     $data = array();
        //     foreach ($users as $user) {
        //         //echo $user->id;die;

        //         $document = Documents::where('user_id', $user->id)->pluck('document_type_id')->toArray();
        //         $document_types = DocumentType::whereIn('id', $document)->pluck('document_type')->toArray();
        //         $last_doc = Documents::where('user_id', $user->id)->orderBy('id', 'DESC')->first();
        //         $totalDocuments = sizeof($document);
        //         $data[] = array(
        //             'user_id' => $user->id,
        //             'user_name' => $user->first_name,
        //             'user_image' => $user->image,
        //             'totalDocuments' =>  $totalDocuments,
        //             'incomplete_docs' => 0,
        //             'status' =>  1,
        //             'document_types' =>  $document_types,
        //             'last_updated' =>  $last_doc->created_at->format('m/d/Y'),
        //         );
        //     }
        //     return response()->json([
        //         'status' => true,
        //         'message' => 'Successfully.',
        //         'data' => $data
        //     ], 200);
        // } else {
        //     return response()->json([
        //         'status' => true,
        //         'message' => 'No record.',
        //         'totalDocuments' => 0,
        //         'data' => []
        //     ], 200);
        // }

        if ($positionId == 1) {
            $document = Documents::pluck('document_type_id')->toArray();
            $document_types = DocumentType::whereIn('id', $document)->pluck('document_type')->toArray();
            $last_doc = Documents::where('user_id', $user->id)->orderBy('id', 'DESC')->first();
            $totalDocuments = count($document);
            $data[] = [
                'user_id' => $user->id,
                'user_name' => $user->first_name,
                'user_image' => $user->image,
                'totalDocuments' => $totalDocuments,
                'incomplete_docs' => 0,
                'status' => 1,
                'document_types' => $document_types,
                'last_updated' => $last_doc->created_at->format('m/d/Y'),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            $document = Documents::where('user_id', $user->id)->pluck('document_type_id')->toArray();
            $document_types = DocumentType::whereIn('id', $document)->pluck('document_type')->toArray();
            $last_doc = Documents::where('user_id', $user->id)->orderBy('id', 'DESC')->first();
            $totalDocuments = count($document);
            $data[] = [
                'user_id' => $user->id,
                'user_name' => $user->first_name,
                'user_image' => $user->image,
                'totalDocuments' => $totalDocuments,
                'incomplete_docs' => 0,
                'status' => 1,
                'document_types' => $document_types,
                'last_updated' => $last_doc->created_at->format('m/d/Y'),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }

    }

    public function CreateTemporary(Request $request): JsonResponse
    {
        $documents = $request->document_path;
        $getImage = Storage::disk('s3_private')->temporaryUrl($documents, now()->addMinutes(10));

        return response()->json([
            'api' => 'Create-temporary-url',
            'status' => true,
            'message' => 'Successfully.',
            'document-url' => $getImage,
        ], 200);
    }
}

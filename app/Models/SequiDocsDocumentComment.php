<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SequiDocsDocumentComment extends Model
{
    use HasFactory;

    protected $table = 'sequi_docs_document_comments';

    protected $fillable = [
        'id',
        'document_id',
        'template_id',
        'category_id',
        'document_name', // send document name like offer letter or other doc like W9
        'user_id_from', // onboarding_employees , users // default onboarding_employees
        'comment_user_id_from', // onboarding_employees , users // default users
        'document_send_to_user_id',
        'comment_by_id',
        'comment_type',
        'comment',
    ];

    public static function get_comment_list($data)
    {
        $user_id = $data['user_id'];
        $user_id_from = $data['user_id_from'];
        $get_comment_list = SequiDocsDocumentComment::where('user_id_from', $user_id_from)->where('document_send_to_user_id', $user_id)
            ->select(
                'sequi_docs_document_comments.id',
                'sequi_docs_document_comments.document_name',
                'sequi_docs_document_comments.comment',
                'sequi_docs_document_comments.comment_type',
                'sequi_docs_document_comments.user_id_from',
                'sequi_docs_document_comments.comment_user_id_from',
                'sequi_docs_document_comments.document_send_to_user_id',
                'sequi_docs_document_comments.comment_by_id',
                'sequi_docs_document_comments.created_at'
            )
            ->selectRaw('CASE WHEN sequi_docs_document_comments.comment_user_id_from = "users" THEN CONCAT(users.first_name, " ", users.last_name) ELSE CONCAT(onboarding_employees.first_name, " ", onboarding_employees.last_name) END AS comment_by_name'
            )
            ->leftJoin('users', 'users.id', '=', 'sequi_docs_document_comments.comment_by_id')
            ->leftJoin('onboarding_employees', 'onboarding_employees.id', '=', 'sequi_docs_document_comments.comment_by_id')
            ->get();

        return $get_comment_list;
    }
}

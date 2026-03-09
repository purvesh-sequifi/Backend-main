<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Column Desc.
 *
 * user_id_from:
 * Telling the table where this user is store, either onboarding tbl's user or users tbl's user and we are adding Comment On this user.
 *
 * document_send_to_user_id:
 * This is ID field [Comment On this user]
 *
 * comment_user_id_from:
 * Telling the table where this user is store, either onboarding tbl's user or users tbl's user and we are adding [Commenter, who is adding comment]
 *
 * comment_by_id:
 * ID of commentor user.
 */
class NewSequiDocsDocumentComment extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_document_comments';

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
        $get_comment_list = NewSequiDocsDocumentComment::where('user_id_from', $user_id_from)->where('document_send_to_user_id', $user_id)
            ->select(
                'new_sequi_docs_document_comments.id',
                'new_sequi_docs_document_comments.document_name',
                'new_sequi_docs_document_comments.comment',
                'new_sequi_docs_document_comments.comment_type',
                'new_sequi_docs_document_comments.user_id_from',
                'new_sequi_docs_document_comments.comment_user_id_from',
                'new_sequi_docs_document_comments.document_send_to_user_id',
                'new_sequi_docs_document_comments.comment_by_id',
                'new_sequi_docs_document_comments.created_at'
            )
            ->selectRaw('CASE WHEN new_sequi_docs_document_comments.comment_user_id_from = "users" THEN CONCAT(users.first_name, " ", users.last_name) ELSE CONCAT(onboarding_employees.first_name, " ", onboarding_employees.last_name) END AS comment_by_name'
            )
            ->leftJoin('users', 'users.id', '=', 'new_sequi_docs_document_comments.comment_by_id')
            ->leftJoin('onboarding_employees', 'onboarding_employees.id', '=', 'new_sequi_docs_document_comments.comment_by_id')
            ->get();

        return $get_comment_list;
    }
}

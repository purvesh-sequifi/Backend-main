<?php

namespace App\Http\Controllers\API\TicketSystem\Faq;

use App\Http\Controllers\API\TicketSystem\BaseResponse\BaseController;
use App\Models\TicketFaqCategory;
use Illuminate\Http\Request;

class FaqController extends BaseController
{
    private TicketFaqCategory $ticketFaqCategory;

    public function __construct(TicketFaqCategory $ticketFaqCategory)
    {
        $this->ticketFaqCategory = $ticketFaqCategory;
    }

    public function index(Request $request)
    {
        $faqs = $this->ticketFaqCategory::query()->select('id', 'name')->with(['faqs' => function ($q) use ($request) {
            $q->select('faq_category_id', 'question', 'answer');
            $q->when($request->has('search') && ! empty($request->input('search')), function ($q) {
                $q->where(function ($q) {
                    $searchTerm = \request()->input('search');
                    $q->orWhere('question', 'LIKE', '%'.$searchTerm.'%')->orWhere('answer', 'LIKE', '%'.$searchTerm.'%');
                });
            })->when(isset($request->status), function ($q) {
                $q->where('status', \request()->input('status'));
            });
        }])->when($request->has('search') && ! empty($request->input('search')), function ($q) {
            $q->whereHas('faqs', function ($q) {
                $q->where(function ($q) {
                    $searchTerm = \request()->input('search');
                    $q->orWhere('question', 'LIKE', '%'.$searchTerm.'%')->orWhere('answer', 'LIKE', '%'.$searchTerm.'%');
                });
            });
        })->when(isset($request->status), function ($q) {
            $q->whereHas('faqs', function ($q) {
                $q->where('status', \request()->input('status'));
            });
        })->orderBy('order')->get();

        $this->successResponse('Faq List Data!!', 'Faq List', $faqs);
    }
}

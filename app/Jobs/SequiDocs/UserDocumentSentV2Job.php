<?php

namespace App\Jobs\SequiDocs;

use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserDocumentSentV2Job implements ShouldQueue
{
    use Batchable, Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $request;

    public $authUser;

    public $template;

    public $categoryId;

    public $documentType;

    public $timeout = 300;

    public $type = 'test';

    public $batchUsers = [];

    public $uploadTypeDocuments;

    public function __construct($batchUsers, $documentType, $authUser, $type, $categoryId = '', $template = '', $request = '', $uploadTypeDocuments = [])
    {
        $this->type = $type;
        $this->request = $request;
        $this->template = $template;
        $this->authUser = $authUser;
        $this->batchUsers = $batchUsers;
        $this->categoryId = $categoryId;
        $this->documentType = $documentType;
        $this->uploadTypeDocuments = $uploadTypeDocuments;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $namespace = app()->getNamespace();
        $sequiDocsUserDocumentV2Controller = app()->make($namespace.\Http\Controllers\API\V2\SequiDocs\SequiDocsUserDocumentsV2Controller::class);
        if ($this->documentType == 'offer-letter') {
            $sequiDocsUserDocumentV2Controller->sendOfferLetter($this->batchUsers, $this->authUser, $this->categoryId, $this->type);
        } elseif ($this->documentType == 'single-template') {
            // $jobs[] = new SentSingleTemplateV2Job($batchUsers, $authUser, $template, 'send');
            $sequiDocsUserDocumentV2Controller->singleTemplate($this->batchUsers, $this->authUser, $this->template, $this->type);
        } elseif ($this->documentType == 'smart-text-template') {
            // $jobs[] = new SentSmartTextTemplateV2Job($batchUsers, $authUser, $template, $request->all(), $batchIndex + 1, 'send');
            $sequiDocsUserDocumentV2Controller->smartTextTemplate($this->batchUsers, $this->authUser, $this->template, $this->type, $this->request);
        } elseif ($this->documentType == 'pdf-template') {
            // $jobs[] = new SentPdfTemplateV2Job($batchUsers, $authUser, $template, $batchIndex + 1, 'send');
            $sequiDocsUserDocumentV2Controller->pdfTemplate($this->batchUsers, $this->authUser, $this->template, $this->type);
        } elseif ($this->documentType == 'upload-type-documents') {
            // $jobs[] = new SentUploadTypeDocumentsV2Job($batchUsers, $authUser, $emailTemplate, $uploadTypeDocuments, $request->all(), $batchIndex + 1, 'send');
            $sequiDocsUserDocumentV2Controller->uploadTypeDocuments($this->batchUsers, $this->authUser, $this->template, $this->type, $this->request, $this->uploadTypeDocuments);
        }
    }

    public function failed(\Throwable $e)
    {
        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this,
        ];
        \Illuminate\Support\Facades\Log::error('Failed to UserDocumentSentV2Job', $error);
    }
}

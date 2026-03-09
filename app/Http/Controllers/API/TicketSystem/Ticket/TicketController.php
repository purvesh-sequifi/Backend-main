<?php

namespace App\Http\Controllers\API\TicketSystem\Ticket;

use App\Http\Controllers\API\TicketSystem\BaseResponse\BaseController;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketModule;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Exception;

class TicketController extends BaseController
{
    private Ticket $ticket;

    private TicketAttachment $ticketAttachment;

    private TicketModule $ticketModule;

    private string $prefix;

    private string $jiraUserName;

    private string $jiraPassword;

    private string $jiraBaseURL;

    private string $jiraIssueCreateEndpoint;

    private string $jiraIssueDeleteEndpoint;

    private string $defaultStatus;

    public function __construct(Ticket $ticket, TicketAttachment $ticketAttachment, TicketModule $ticketModule)
    {
        $this->ticket = $ticket;
        $this->ticketAttachment = $ticketAttachment;
        $this->ticketModule = $ticketModule;
        $this->prefix = 'S';
        $this->jiraUserName = config('services.jira.email', '');
        $this->jiraPassword = config('services.jira.secret_key', '');
        $this->jiraBaseURL = config('services.jira.base_url', '');
        $this->jiraIssueCreateEndpoint = 'rest/api/2/issue';
        $this->jiraIssueDeleteEndpoint = 'rest/api/2/issue';
        $this->defaultStatus = $ticket::IS_JIRA_TO_DO_STATUS;
    }

    public function index(Request $request)
    {
        $tickets = $this->ticket::query()->select('*', 'created_at as raised_date')->ticketDataUserWise()
            ->when($request->has('search') && ! empty($request->input('search')), function ($q) {
                $q->where(function ($q) {
                    $searchTerm = strtolower(\request()->input('search'));
                    $q->orWhereRaw('LOWER(ticket_id) LIKE ?', ["%$searchTerm%"])->orWhereRaw('LOWER(summary) LIKE ?', ["%$searchTerm%"])->orWhereRaw('LOWER(jira_ticket_id) LIKE ?', ["%$searchTerm%"]);
                });
            })->when(isset($request->priority), function ($q) {
                $q->whereRaw('LOWER(priority) = ?', [strtolower(\request()->input('priority'))]);
            })->when(isset($request->ticket_status), function ($q) {
                $q->whereRaw('LOWER(ticket_status) = ?', [strtolower(\request()->input('ticket_status'))]);
            })->when(isset($request->module), function ($q) {
                $q->whereHas('module', function ($q) {
                    $q->where('id', \request()->input('module'));
                });
            })->when(isset($request->created_by), function ($q) {
                $q->where('created_by', \request()->input('created_by'));
            })->with('createdByUser:id,first_name,last_name,image,position_id,sub_position_id,is_super_admin,is_manager')->orderBy('id', 'DESC')
            ->paginate($request->per_page ?? config('app.paginate', 15));

        $tickets->getCollection()->transform(function ($ticket) {
            $ticket = $ticket->toArray();

            if (isset($ticket['created_by_user']['image']) && $ticket['created_by_user']['image'] != null) {
                $ticket['created_by_user']['image'] = s3_getTempUrl(config('app.domain_name').'/'.$ticket['created_by_user']['image']);
            } else {
                $ticket['created_by_user']['image'] = null;
            }

            return $ticket;
        });

        $ticketsSummary = $this->ticket::query()->ticketDataUserWise()
            ->selectRaw('COUNT(*) as total_tickets,
                 SUM(CASE WHEN ticket_status IN (?, ?) THEN 1 ELSE 0 END) as open_tickets,
                 SUM(CASE WHEN ticket_status = ? THEN 1 ELSE 0 END) as closed_tickets', ['To Do', 'In Progress', 'Done'])
            ->first();

        $totalTickets = (int) $ticketsSummary->total_tickets ?? 0;
        $openTickets = (int) $ticketsSummary->open_tickets ?? 0;
        $closedTickets = (int) $ticketsSummary->closed_tickets ?? 0;

        $tickets = $tickets->toArray();
        $tickets['totalTickets'] = $totalTickets;
        $tickets['openTickets'] = $openTickets;
        $tickets['closedTickets'] = $closedTickets;

        $this->successResponse('Ticket List Data!!', 'Ticket List', $tickets);
    }

    /**
     * @throws GuzzleException
     */
    public function store(Request $request)
    {
        $this->checkValidations($request->all(), [
            'module_id' => 'required|exists:ticket_modules,id',
            'summary' => 'required|min:3',
            'priority' => 'required|in:high,medium,low',
            'description' => 'required',
        ]);

        DB::beginTransaction();
        $lastTicket = $this->ticket::query()->latest()->first();
        if ($lastTicket) {
            $ticketId = generate_ticket_id_number($lastTicket->ticket_id, $this->prefix);
        } else {
            $ticketId = $this->prefix.'0001';
        }

        $module = $this->ticketModule::query()->where('id', $request->input('module_id'))->first();

        $ticketData = [
            'created_by' => auth()->user()->id,
            'ticket_id' => $ticketId,
            'summary' => $request->input('summary'),
            'module' => $module->jira_summary,
            'jira_module_id' => $module->jira_id,
            'priority' => $request->input('priority'),
            'description' => $request->input('description'),
            'ticket_status' => $this->defaultStatus,
        ];

        $ticket = $this->ticket::query()->create($ticketData);

        if ($ticket) {
            if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'demo' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod' && ! strpos(url(''), '127.0.0.1') && ! strpos(url(''), 'localhost')) {
                $this->createTicket($ticket);
            }
            DB::commit();
            $this->successResponse('Ticket Created Successfully And Synced!!', 'Ticket Create', $ticket);
        } else {
            DB::rollBack();
            $this->errorResponse('Something Went Wrong While Creating A Ticket!!', 'Ticket Create');
        }
    }

    /**
     * @throws GuzzleException
     */
    protected function createTicket($ticket)
    {
        try {

            $priority = '';
            if (strtolower($ticket->priority) == 'high') {
                $priority = $this->ticket::JIRA_HIGH_PRIORITY_Id;
            } elseif (strtolower($ticket->priority) == 'medium') {
                $priority = $this->ticket::JIRA_MEDIUM_PRIORITY_Id;
            } elseif (strtolower($ticket->priority) == 'low') {
                $priority = $this->ticket::JIRA_LOW_PRIORITY_Id;
            }

            $param = [
                'fields' => [
                    'summary' => $ticket->summary,
                    'description' => $ticket->description,
                    'issuetype' => [
                        'id' => $this->ticket::JIRA_ISSUE_TYPE_Id,
                    ],
                    'assignee' => [
                        'id' => $this->ticket::JIRA_ASSIGNEE_ID,
                    ],
                    'labels' => [
                        'From-'.config('app.domain_name', url('')),
                    ],
                    'project' => [
                        'id' => $this->ticket::JIRA_PROJECT_Id,
                    ],
                    'priority' => [
                        'id' => $priority,
                    ],
                    'parent' => [
                        'id' => $ticket->jira_module_id,
                    ],
                ],
            ];

            $request = $this->createTicketOnJiraCloud($param);

            //            $request = Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->withHeaders(['Accept' => 'application/json'])->post($this->jiraBaseURL . $this->jiraIssueCreateEndpoint, $param);

            if ($request->status() != 201) {
                DB::rollBack();
                $this->errorResponse('Error On Jira Create API!!', 'Ticket Create');
            }

            $issue = $request->json();

            $this->addAttachmentsToIssue($issue, $ticket);

            $this->ticket::query()->where('id', $ticket->id)->update(['jira_ticket_id' => $issue['key'], 'is_jira_created' => $this->ticket::IS_JIRA_SYNCED, 'last_jira_sync_date' => now()]);

            $this->successResponse('Ticket Created Successfully And Synced!!', 'Ticket Create', $ticket);
        } catch (Exception $e) {
            DB::rollBack();
            $this->errorResponse($e->getMessage().' '.$e->getLine(), 'Ticket Create');
        }
    }

    public function createTicketOnJiraCloud($request)
    {
        return Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->withHeaders(['Accept' => 'application/json'])->post($this->jiraBaseURL.$this->jiraIssueCreateEndpoint, $request);
    }

    /**
     * @throws GuzzleException
     */
    protected function addAttachmentsToIssue($issue, $ticket)
    {
        try {
            if (\request()->has('attachments')) {
                foreach (\request()->file('attachments') as $attachment) {
                    $imgPath = time().$attachment->getClientOriginalName();
                    $ticketPath = 'tickets/'."$ticket->ticket_id/".$imgPath;
                    $awsPath = config('app.domain_name').'/'.$ticketPath;
                    s3_upload($awsPath, file_get_contents($attachment));

                    $request = (new Client)->request('POST', $this->jiraBaseURL.'rest/api/2/issue/'.$issue['key'].'/attachments', [
                        'http_errors' => false,
                        'headers' => ['Accept' => 'application/json', 'X-Atlassian-Token' => 'no-check'],
                        'multipart' => [
                            [
                                'name' => 'file',
                                'contents' => $attachment->getContent(),
                                'filename' => $imgPath,
                                'headers' => [
                                    'Content-Type' => '<Content-type header>',
                                ],
                            ],
                        ],
                        'auth' => [$this->jiraUserName, $this->jiraPassword],
                    ]);

                    $array = [
                        'original_file_name' => $attachment->getClientOriginalName(),
                        'system_file_name' => $ticketPath,
                        'mime_type' => $attachment->getMimeType(),
                        'size' => $attachment->getSize(),
                    ];

                    if ($request->getStatusCode() == 200) {
                        $response = json_decode($request->getBody()->getContents(), true);

                        $array['jira_id'] = @$response[0]['id'];
                        $array['jira_synced'] = $this->ticketAttachment::IS_JIRA_SYNCED;
                    } else {
                        $array['jira_synced'] = $this->ticketAttachment::IS_JIRA_SYNCED;
                    }

                    $ticket->ticketAttachment()->create($array);
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorResponse($e->getMessage().' '.$e->getLine(), 'Ticket Create');
        }
    }

    public function view($id)
    {
        $this->checkValidations(['id' => $id], [
            'id' => 'required|exists:tickets,id',
        ]);

        $ticket = $this->ticket::query()->select('*', 'created_at as raised_date')->ticketDataUserWise()
            ->with('createdByUser:id,first_name,last_name,image,position_id,sub_position_id,is_super_admin,is_manager', 'ticketAttachment')->where('id', $id)->first()->toArray();

        if ($ticket) {
            if (isset($ticket['created_by_user']['image']) && $ticket['created_by_user']['image'] != null) {
                $ticket['created_by_user']['image'] = s3_getTempUrl(config('app.domain_name').'/'.$ticket['created_by_user']['image']);
            } else {
                if (isset($ticket['created_by_user'])) {
                    $ticket['created_by_user']['image'] = null;
                }
            }

            foreach ($ticket['ticket_attachment'] as $key => $attachment) {
                if (isset($attachment['system_file_name']) && ! empty($attachment['system_file_name'])) {
                    $ticket['ticket_attachment'][$key]['system_file'] = s3_getTempUrl(config('app.domain_name').'/'.$attachment['system_file_name']);
                } else {
                    $ticket['ticket_attachment'][$key]['system_file'] = null;
                }
            }

            $this->successResponse('Ticket View Data!!', 'Ticket View', $ticket);
        } else {
            $this->errorResponse('You have no rights to view this ticket!!', 'Ticket View');
        }
    }

    /**
     * @throws GuzzleException
     */
    public function delete($id)
    {
        $this->checkValidations(['id' => $id], [
            'id' => 'required|exists:tickets,id',
        ]);

        $ticket = Ticket::query()->find($id);

        if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'demo' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod' && ! strpos(url(''), '127.0.0.1') && ! strpos(url(''), 'localhost')) {
            $this->deleteJiraTicket($ticket);
        }

        $ticket->delete();

        $this->successResponse('Ticket Deleted Successfully!!', 'Ticket Delete');
    }

    protected function deleteJiraTicket($ticket)
    {
        try {
            $request = Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->delete($this->jiraBaseURL.$this->jiraIssueDeleteEndpoint.'/'.$ticket->jira_ticket_id);
            if ($request->status() != 204) {
                $this->errorResponse('Error On Jira Delete API!!', 'Ticket Delete');
            }
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage().' '.$e->getLine(), 'Ticket Delete');
        }
    }

    public function sync(): int
    {
        if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'demo' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod' && ! strpos(url(''), '127.0.0.1') && ! strpos(url(''), 'localhost')) {
            $jiraUserName = config('services.jira.email', '');
            $jiraPassword = config('services.jira.secret_key', '');
            $jiraBaseURL = config('services.jira.base_url', '');

            $tickets = Ticket::query()->where('ticket_status', '!=', Ticket::IS_JIRA_DONE_STATUS)->where('is_jira_created', Ticket::IS_JIRA_SYNCED)->whereNotNull('jira_ticket_id')->pluck('jira_ticket_id');

            if (count($tickets) != 0) {
                $implode = implode(',', $tickets->toArray());
                $jql = "key IN ($implode)";
                $request = Http::withBasicAuth($jiraUserName, $jiraPassword)->withHeaders(['Accept' => 'application/json'])
                    ->get($jiraBaseURL.'rest/api/2/search', ['jql' => $jql, 'maxResults' => count($tickets)]);

                if ($request->status() == 200) {
                    $issues = $request->json();
                    if (count($issues['issues']) != 0) {
                        foreach ($issues['issues'] as $issue) {
                            $issueStatus = $issue['fields']['status']['name'];
                            $issueEstimationTime = $issue['fields']['timeestimate'];
                            $priority = $issue['fields']['priority']['name'];
                            if (strtolower($priority) == 'highest' || strtolower($priority) == 'high') {
                                $priority = 'High';
                            } elseif (strtolower($priority) == 'low' || strtolower($priority) == 'lowest') {
                                $priority = 'Low';
                            }

                            Ticket::query()->where('jira_ticket_id', $issue['key'])->update([
                                'priority' => $priority,
                                'ticket_status' => $issueStatus,
                                'estimated_time' => $issueEstimationTime,
                                'last_jira_sync_date' => now(),
                            ]);
                        }
                    }

                    return 1;
                }

                return 0;
            }
        }

        return 0;
    }
}

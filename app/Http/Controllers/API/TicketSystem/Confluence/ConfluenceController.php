<?php

namespace App\Http\Controllers\API\TicketSystem\Confluence;

use App\Http\Controllers\API\TicketSystem\BaseResponse\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConfluenceController extends BaseController
{
    private string $jiraUserName;

    private string $jiraPassword;

    private string $jiraBaseURL;

    private string $jiraKUBASpace;

    private string $jiraGKBSpace;

    private string $jiraConfluenceListEndpoint;

    private string $jiraConfluenceListDataEndpoint;

    private string $jiraConfluenceViewHtmlPageEndpoint;

    private string $jiraConfluenceDropdownEndpoint;

    public function __construct()
    {
        $this->jiraUserName = config('services.jira.email', '');
        $this->jiraPassword = config('services.jira.secret_key', '');
        $this->jiraBaseURL = config('services.jira.base_url', '');
        $this->jiraKUBASpace = 'KUBA';
        $this->jiraGKBSpace = 'GKB';
        $this->jiraConfluenceListEndpoint = 'wiki/rest/api/space/';
        $this->jiraConfluenceListDataEndpoint = 'wiki/rest/api/search?cql=type=page&limit=1000&expand=body.dynamic';
        $this->jiraConfluenceViewHtmlPageEndpoint = 'wiki/rest/api/content/';
        $this->jiraConfluenceDropdownEndpoint = 'wiki/rest/api/search?limit=1000&&cql=space.';
    }

    public function index(Request $request)
    {
        $this->validateJiraConfiguration('Confluence List');

        if ($request->input('type') == 'administrator') {
            $url = $this->jiraBaseURL.$this->jiraConfluenceListEndpoint.$this->jiraKUBASpace;
        } else {
            $url = $this->jiraBaseURL.$this->jiraConfluenceListEndpoint.$this->jiraGKBSpace;
        }

        try {
            $api = Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->withHeaders(['Accept' => 'application/json'])->get($url.'/content?expand=body.dynamic&limit=1');

            if ($api->status() != 200) {
                $this->handleApiError($api, 'Confluence List', ['url' => $url]);

                return;
            }
        } catch (\Exception $e) {
            Log::error('Jira Confluence API Exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);
            $this->errorResponse('Failed to connect to Jira Confluence API: '.$e->getMessage(), 'Confluence List');

            return;
        }

        $confluences = $api->json();
        $contents = json_decode($confluences['page']['results'][0]['body']['dynamic']['value']);

        $response = [];
        if (@$contents->content) {
            foreach ($contents->content as $index => $inCon) {
                if (@$inCon->content) {
                    if ($inCon->type == 'layoutSection') {
                        foreach ($inCon->content as $con) {
                            if ($index > 3) {
                                $response[] = $this->contentData($con->content);
                            }
                        }
                    }
                }
            }
        }

        $this->successResponse('Confluence List', 'Confluence List', $response);
    }

    //    /**
    //     * @param $contents
    //     * @param $array
    //     * @param array $key
    //     * @param string $type
    //     * @return array
    //     */
    //    protected function performRecursion($contents, &$array, &$key = [], &$type = ''): array
    //    {
    //        if (is_array($contents)) {
    //            foreach ($contents as $inCon2) {
    //                if (empty($key) && !empty(@$inCon2->text)) {
    //                    $string = preg_replace('/\s+/', '', $inCon2->text);
    //                    if (!empty($string)) {
    //                        $key = @$inCon2->text;
    //                    }
    //                } else {
    //                    if (@$inCon2->content) {
    //                        $this->performRecursion($inCon2->content, $array, $key, $type);
    //                    } else {
    //                        if (@$inCon2->text) {
    //                            $string = preg_replace('/\s+/', '', $inCon2->text);
    //                            if (!empty($string)) {
    //                                $array[] = $inCon2->text;
    //                            }
    //                        }
    //                    }
    //                }
    //            }
    //            return ['key' => $key, 'value' => $array];
    //        }
    //    }

    public function contentData($contents, array &$array = [], string &$key = '', string &$type = '', string &$icon = ''): array
    {
        foreach ($contents as $content) {
            if ($content->type == 'heading' || $type == 'emoji') {
                if (@$content->content) {
                    foreach ($content->content as $con) {
                        $string = preg_replace('/\s+/', '', @$con->text);
                        if (! empty($string)) {
                            $key .= @$con->text;
                            $type = '';
                        }
                        if (@$con->type == 'emoji') {
                            $icon = @$con->attrs->text;
                        }
                    }
                } else {
                    if ($type == 'emoji') {
                        $string = preg_replace('/\s+/', '', @$content->text);
                        if (! empty($string)) {
                            $key .= @$content->text;
                            $type = '';
                        }
                    } else {
                        $string = preg_replace('/\s+/', '', @$content->content[0]->text);
                        if (! empty($string)) {
                            $key = @$content->content[0]->text;
                        } else {
                            $this->contentData($content->content, $array, $key, $type, $icon);
                        }
                    }
                }
            } else {
                if ($content->type == 'emoji') {
                    $type = $content->type;
                    $icon = $content->attrs->text;
                }
                if (@$content->content) {
                    $this->contentData($content->content, $array, $key, $type, $icon);
                } else {
                    if (@$content->text) {
                        $string = preg_replace('/\s+/', '', $content->text);
                        if (! empty($string)) {
                            $name = $content->text;
                            $link = '';
                            if (is_array(@$content->marks)) {
                                foreach ($content->marks as $mark) {
                                    $link = $this->getLing(@$mark->attrs->href, $mark);
                                }
                            } else {
                                if (@$content->marks) {
                                    $link = $this->getLing($content->marks[0]->attrs->href, $content->marks[0]);
                                }
                            }
                            $array[] = ['name' => $name, 'link' => $link];
                        }
                    }
                }
            }
        }

        return ['key' => @$key, 'icon' => @$icon, 'value' => @$array];
    }

    protected function getLing($href, $mark): mixed
    {
        if (@$mark->type == 'link') {
            preg_match('/\/x\/([^\/]+)$/', $href, $matches);
            if (@$matches[0]) {
                return @$matches[0];
            } else {
                preg_match('/\bdraftId=(\d+)\b/', $href, $matches);

                return @$matches[1];
            }
        }

        return '';
    }

    public function view(Request $request)
    {
        $this->validateJiraConfiguration('Confluence View');

        $this->checkValidations($request->all(), [
            'id' => 'required',
        ]);

        $contentId = $request->id;
        if (! is_numeric($request->id)) {
            $contentId = '';
            try {
                $confluences = Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->withHeaders(['Accept' => 'application/json'])->get($this->jiraBaseURL.$this->jiraConfluenceListDataEndpoint);

                if ($confluences->status() != 200) {
                    $this->handleApiError($confluences, 'Confluence View');

                    return;
                }
            } catch (\Exception $e) {
                Log::error('Jira Confluence List View API Exception', [
                    'message' => $e->getMessage(),
                ]);
                $this->errorResponse('Failed to connect to Jira Confluence API: '.$e->getMessage(), 'Confluence View');

                return;
            }

            $confluences = $confluences->json();

            foreach ($confluences['results'] as $confluence) {
                if ($confluence['content']['_links']['tinyui'] == $request->id) {
                    $contentId = $confluence['content']['id'];
                    break;
                }
            }

            if (! $contentId) {
                $this->errorResponse('Data Not Found!!', 'Confluence View');

                return;
            }
        }

        try {
            $content = Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->withHeaders(['Accept' => 'application/json'])->get($this->jiraBaseURL.$this->jiraConfluenceViewHtmlPageEndpoint.$contentId.'?expand=body.view');

            if ($content->status() != 200) {
                $this->handleApiError($content, 'Confluence View', ['contentId' => $contentId]);

                return;
            }
        } catch (\Exception $e) {
            Log::error('Jira Confluence View API Exception', [
                'message' => $e->getMessage(),
                'contentId' => $contentId,
            ]);
            $this->errorResponse('Failed to connect to Jira Confluence API: '.$e->getMessage(), 'Confluence View');

            return;
        }

        $content = $content->json();

        $trimmedHtml = str_replace("\n", '', $content['body']['view']['value']);

        if (strpos($content['_expandable']['space'], 'GKB') != true) {
            $position = strpos($trimmedHtml, 'Made by ');
            if ($position !== false) {
                $position = strpos($trimmedHtml, '</p>');
                if ($position !== false) {
                    $trimmedHtml = substr($trimmedHtml, $position);
                }
            }
        }

        $position = strpos($trimmedHtml, 'Relatedarticles');
        if ($position !== false) {
            $trimmedHtml = substr($trimmedHtml, 0, $position);

            $lastLessThanPosition = strrpos($trimmedHtml, '<');
            if ($lastLessThanPosition !== false) {
                $trimmedHtml = substr($trimmedHtml, 0, $lastLessThanPosition);
            }
        }

        $position = strpos($trimmedHtml, 'Made with Scribe');
        if ($position !== false) {
            $trimmedHtml = substr($trimmedHtml, 0, $position);

            $lastLessThanPosition = strrpos($trimmedHtml, '<h4');
            if ($lastLessThanPosition !== false) {
                $trimmedHtml = substr($trimmedHtml, 0, $lastLessThanPosition);
            }
        }

        $response['title'] = $content['title'];
        $response['html'] = $trimmedHtml;

        $this->successResponse('Confluence View Data!!', 'Confluence View', $response);
    }

    public function dropdownFilter(Request $request)
    {
        $this->validateJiraConfiguration('Confluence Dropdown');

        $param = '';
        if ($request->search) {
            $param = ' and title ~ "'.$request->search.'*"';
        }
        if ($request->input('type') == 'administrator') {
            $url = $this->jiraBaseURL.$this->jiraConfluenceDropdownEndpoint.'key='.$this->jiraKUBASpace;
        } else {
            $url = $this->jiraBaseURL.$this->jiraConfluenceDropdownEndpoint.'key='.$this->jiraGKBSpace;
        }

        try {
            $confluences = Http::withBasicAuth($this->jiraUserName, $this->jiraPassword)->withHeaders(['Accept' => 'application/json'])->get($url.$param);

            if ($confluences->status() != 200) {
                $this->handleApiError($confluences, 'Confluence Dropdown', ['url' => $url.$param]);

                return;
            }
        } catch (\Exception $e) {
            Log::error('Jira Confluence Search API Exception', [
                'message' => $e->getMessage(),
                'url' => $url.$param,
            ]);
            $this->errorResponse('Failed to connect to Jira Confluence API: '.$e->getMessage(), 'Confluence Dropdown');

            return;
        }

        $confluences = $confluences->json();

        $response = [];
        foreach ($confluences['results'] as $confluence) {
            if (@$confluence['content']) {
                $response[] = ['title' => $confluence['content']['title'], 'sub_title' => $confluence['resultGlobalContainer']['title'], 'url' => $confluence['content']['_links']['tinyui']];
            }
        }

        $this->successResponse('Confluence Dropdown Data!!', 'Confluence Dropdown', $response);
    }

    /**
     * Validate Jira configuration
     */
    private function validateJiraConfiguration(string $context): void
    {
        if (empty($this->jiraUserName) || empty($this->jiraPassword) || empty($this->jiraBaseURL)) {
            $this->errorResponse('Jira/Confluence API configuration is missing. Please check JIRA_EMAIL, JIRA_SECRET_KEY, and JIRA_API_BASE_URL environment variables.', $context);
        }
    }

    /**
     * Handle API error responses with consistent messaging
     */
    private function handleApiError($response, string $context, array $additionalData = []): void
    {
        $logData = [
            'status' => $response->status(),
            'response' => substr($response->body(), 0, 500), // First 500 chars only for security
            ...$additionalData,
        ];

        Log::error('Jira Confluence API Error', $logData);

        $errorMessage = 'Error On Jira Confluence API!! Status: '.$response->status();
        if ($response->status() == 403) {
            $errorMessage = 'Access denied to Jira Confluence. Please check user permissions for the configured JIRA_EMAIL account.';
        } elseif ($response->status() == 401) {
            $errorMessage = 'Authentication failed. Please check JIRA_EMAIL and JIRA_SECRET_KEY credentials.';
        }

        $this->errorResponse($errorMessage, $context, [], $response->status());
    }
}

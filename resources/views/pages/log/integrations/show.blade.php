<x-logs-layout>
    <!--begin::Card-->
    <div class="card card-custom">
    <div class="card-header">
        <div class="card-title">
            <h3 class="card-label">API Transaction Log Details
                <span class="d-block text-muted pt-2 font-size-sm">Detailed view of API request and response for {{ $log->interigation_name }} integration</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('admin.logs.integrations') }}" class="btn btn-light-primary font-weight-bold">
                <i class="la la-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <!--begin::Section-->
        <div class="mb-10">
            <h4 class="font-weight-bold mb-6">General Information</h4>
            
            <div class="row">
                <div class="col-xl-6">
                    <!--begin::List Widget 3-->
                    <div class="card card-custom card-stretch gutter-b">
                        <!--begin::Body-->
                        <div class="card-body pt-4">
                            <div class="d-flex align-items-center mb-8">
                                <div class="symbol symbol-40 symbol-light-primary mr-5">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-lg svg-icon-primary">
                                            <i class="flaticon2-layers-1 icon-lg"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex flex-column font-weight-bold">
                                    <span class="text-dark text-hover-primary mb-1 font-size-lg">Integration</span>
                                    <span class="text-muted">{{ $log->interigation_name }}</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-8">
                                <div class="symbol symbol-40 symbol-light-info mr-5">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-lg svg-icon-info">
                                            <i class="flaticon2-rocket-1 icon-lg"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex flex-column font-weight-bold">
                                    <span class="text-dark text-hover-primary mb-1 font-size-lg">API Name</span>
                                    <span class="text-muted">{{ $log->api_name }}</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-8">
                                <div class="symbol symbol-40 symbol-light-warning mr-5">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-lg svg-icon-warning">
                                            <i class="flaticon2-calendar-8 icon-lg"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex flex-column font-weight-bold">
                                    <span class="text-dark text-hover-primary mb-1 font-size-lg">Created At</span>
                                    <span class="text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                                </div>
                            </div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::List Widget 3-->
                </div>
                <div class="col-xl-6">
                    <!--begin::List Widget 4-->
                    <div class="card card-custom card-stretch gutter-b">
                        <!--begin::Body-->
                        <div class="card-body pt-4">
                            <div class="d-flex align-items-center mb-8">
                                <div class="symbol symbol-40 symbol-light-success mr-5">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-lg svg-icon-success">
                                            <i class="flaticon2-location icon-lg"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex flex-column font-weight-bold">
                                    <span class="text-dark text-hover-primary mb-1 font-size-lg">URL</span>
                                    <span class="text-muted">{{ $log->url }}</span>
                                </div>
                            </div>
                            
                            @php
                                $response = json_decode($log->response, true);
                                $hasError = isset($response['meta']['errors']) && !empty($response['meta']['errors']);
                                $errorMessage = $hasError ? $response['meta']['errors'][0]['message'] ?? 'Unknown error' : null;
                            @endphp
                            
                            <div class="d-flex align-items-center mb-8">
                                <div class="symbol symbol-40 symbol-light-{{ $hasError ? 'danger' : 'success' }} mr-5">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-lg svg-icon-{{ $hasError ? 'danger' : 'success' }}">
                                            <i class="flaticon2-check-mark icon-lg"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex flex-column font-weight-bold">
                                    <span class="text-dark text-hover-primary mb-1 font-size-lg">Status</span>
                                    <span class="text-{{ $hasError ? 'danger' : 'success' }}">
                                        {{ $hasError ? 'Error' : 'Success' }}
                                        @if($hasError)
                                            - {{ $errorMessage }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-8">
                                <div class="symbol symbol-40 symbol-light-primary mr-5">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-lg svg-icon-primary">
                                            <i class="flaticon2-time icon-lg"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex flex-column font-weight-bold">
                                    <span class="text-dark text-hover-primary mb-1 font-size-lg">Updated At</span>
                                    <span class="text-muted">{{ $log->updated_at->format('Y-m-d H:i:s') }}</span>
                                </div>
                            </div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::List Widget 4-->
                </div>
            </div>
        </div>
        <!--end::Section-->
        
        <!--begin::Section-->
        <div class="row">
            <div class="col-lg-6">
                <div class="card card-custom gutter-b">
                    <!--begin::Header-->
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Request Payload</h3>
                        </div>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body">
                        <div class="code-block">
                            <pre class="language-json"><code>{{ $payloadFormatted }}</code></pre>
                        </div>
                    </div>
                    <!--end::Body-->
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-custom gutter-b">
                    <!--begin::Header-->
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Response</h3>
                        </div>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body">
                        <div class="code-block">
                            <pre class="language-json"><code>{{ $responseFormatted }}</code></pre>
                        </div>
                    </div>
                    <!--end::Body-->
                </div>
            </div>
        </div>
        <!--end::Section-->
    </div>
</div>
<!--end::Card-->

@push('styles')
<style>
    .code-block {
        background-color: #f5f8fa;
        border-radius: 4px;
        padding: 15px;
        overflow: auto;
        max-height: 500px;
    }
    .code-block pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize syntax highlighting if any library is available
        if (typeof Prism !== 'undefined') {
            Prism.highlightAll();
        } else if (typeof hljs !== 'undefined') {
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightBlock(block);
            });
        }
    });
</script>
@endpush
</x-logs-layout>

<x-logs-layout>
    <!--begin::Card-->
    <div class="card card-custom">
    <div class="card-header flex-wrap py-5">
        <div class="card-title">
            <h3 class="card-label">API Integration Logs
                <span class="d-block text-muted pt-2 font-size-sm">Monitoring of API requests and responses for integrations</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <!--begin::Dropdown-->
            <div class="dropdown dropdown-inline mr-2">
                <button type="button" class="btn btn-light-primary font-weight-bolder dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="la la-download"></i>Export</button>
                <!--begin::Dropdown Menu-->
                <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">
                    <ul class="nav flex-column nav-hover">
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon la la-file-excel-o"></i>
                                <span class="nav-text">Excel</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon la la-file-text-o"></i>
                                <span class="nav-text">CSV</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <!--end::Dropdown Menu-->
            </div>
            <!--end::Dropdown-->
        </div>
    </div>
    <div class="card-body">
        <!-- Statistics -->
        <div class="row mb-6">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <!--begin::Stats Widget 11-->
                <div class="card card-custom bg-primary gutter-b">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                    <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <div class="text-inverse-primary font-weight-bolder font-size-h2 mt-3">{{ $stats['total'] }}</div>
                        <a href="#" class="text-inverse-primary font-weight-bold font-size-lg mt-1">Total API Calls</a>
                    </div>
                </div>
                <!--end::Stats Widget 11-->
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <!--begin::Stats Widget 12-->
                <div class="card card-custom bg-success gutter-b">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Media/Equalizer.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                    <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                    <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                    <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <div class="text-inverse-success font-weight-bolder font-size-h2 mt-3">{{ $stats['today'] }}</div>
                        <a href="#" class="text-inverse-success font-weight-bold font-size-lg mt-1">Today's API Calls</a>
                    </div>
                </div>
                <!--end::Stats Widget 12-->
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <!--begin::Stats Widget 13-->
                <div class="card card-custom bg-info gutter-b">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                    <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                    <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                    <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <div class="text-inverse-info font-weight-bolder font-size-h2 mt-3">{{ $stats['integration_total'] }}</div>
                        <a href="#" class="text-inverse-info font-weight-bold font-size-lg mt-1">{{ $integration }} API Calls</a>
                    </div>
                </div>
                <!--end::Stats Widget 13-->
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <!--begin::Stats Widget 14-->
                <div class="card card-custom bg-danger gutter-b">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Communication/Group.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <polygon points="0 0 24 0 24 24 0 24" />
                                    <path d="M18,14 C16.3431458,14 15,12.6568542 15,11 C15,9.34314575 16.3431458,8 18,8 C19.6568542,8 21,9.34314575 21,11 C21,12.6568542 19.6568542,14 18,14 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                    <path d="M17.6011961,15.0006174 C21.0077043,15.0378534 23.7891749,16.7601418 23.9984937,20.4 C24.0069246,20.5466056 23.9984937,21 23.4559499,21 L19.6,21 C19.6,18.7490654 18.8562935,16.6718327 17.6011961,15.0006174 Z M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z" fill="#000000" fill-rule="nonzero" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                        </span>
                        <div class="text-inverse-danger font-weight-bolder font-size-h2 mt-3">{{ $stats['errors'] }}</div>
                        <a href="#" class="text-inverse-danger font-weight-bold font-size-lg mt-1">Error API Calls</a>
                    </div>
                </div>
                <!--end::Stats Widget 14-->
            </div>
        </div>

        <!--begin::Search Form-->
        <div class="mb-7">
            <form method="GET" action="{{ route('admin.logs.integrations') }}">
                <div class="row align-items-center">
                    <div class="col-lg-3 col-xl-3">
                        <div class="input-icon">
                            <input type="text" class="form-control" placeholder="Search..." name="search" value="{{ request('search') }}" />
                            <span><i class="flaticon2-search-1 text-muted"></i></span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xl-3">
                        <div class="form-group">
                            <select class="form-control" name="integration">
                                <option value="">All Integrations</option>
                                @foreach($integrations as $name)
                                    <option value="{{ $name }}" {{ request('integration', $integration) == $name ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xl-3">
                        <div class="form-group">
                            <select class="form-control" name="date_range" id="date-range">
                                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('date_range') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="week" {{ request('date_range') == 'week' ? 'selected' : '' }}>This Week</option>
                                <option value="month" {{ request('date_range') == 'month' ? 'selected' : '' }}>This Month</option>
                                <option value="custom" {{ request('date_range') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xl-3">
                        <div class="form-group">
                            <select class="form-control" name="has_error">
                                <option value="">All Statuses</option>
                                <option value="1" {{ request('has_error') == '1' ? 'selected' : '' }}>With Errors</option>
                                <option value="0" {{ request('has_error') == '0' ? 'selected' : '' }}>Successful Only</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row custom-date-inputs" style="{{ request('date_range') == 'custom' ? '' : 'display: none;' }}">
                    <div class="col-lg-3 col-xl-3">
                        <div class="form-group">
                            <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}" placeholder="Start Date">
                        </div>
                    </div>
                    <div class="col-lg-3 col-xl-3">
                        <div class="form-group">
                            <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}" placeholder="End Date">
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-6">
                        <button type="submit" class="btn btn-primary btn-primary--icon">
                            <span>
                                <i class="la la-search"></i>
                                <span>Filter</span>
                            </span>
                        </button>
                        <a href="{{ route('admin.logs.integrations') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        <!--end::Search Form-->

        <!--begin: Datatable-->
        <table class="table table-bordered table-hover table-checkable" id="kt_datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Integration</th>
                    <th>API Name</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $response = json_decode($log->response, true);
                        $hasError = isset($response['meta']['errors']) && !empty($response['meta']['errors']);
                    @endphp
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->interigation_name }}</td>
                        <td>{{ $log->api_name }}</td>
                        <td>
                            @if ($hasError)
                                <span class="label label-lg label-light-danger label-inline">Error</span>
                            @else
                                <span class="label label-lg label-light-success label-inline">Success</span>
                            @endif
                        </td>
                        <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>
                            <a href="{{ route('admin.logs.integrations.show', $log->id) }}" class="btn btn-sm btn-clean btn-icon" title="View">
                                <i class="la la-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No logs found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <!--end: Datatable-->
        
        <div class="d-flex justify-content-center mt-5">
            {{ $logs->appends(request()->except('page'))->links() }}
        </div>
    </div>
</div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            // Show/hide custom date inputs based on selection
            $('#date-range').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('.custom-date-inputs').show();
                } else {
                    $('.custom-date-inputs').hide();
                }
            });
        });
    </script>
    @endpush
</x-logs-layout>

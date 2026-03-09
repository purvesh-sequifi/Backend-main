<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        p {
            margin: .35em;
        }

        body {
            margin: 0;
        }

        @media only screen and (max-width: 600px) {
            .table-mainParent {
                width: 100% !important;
            }
        }
    </style>
</head>

@php
    $companyProfile = App\Models\CompanyProfile::first();
    $companyAndOtherStaticImages = \App\Models\SequiDocsEmailSettings::company_and_other_static_images($companyProfile);

    $businessName = $companyProfile->business_name;
    $businessPhone = $companyProfile->business_phone;
    $companyEmail = $companyProfile->company_email;
    $businessAddress = $companyProfile->business_address;
    $footerContent = "$businessName |  + $businessPhone  |  $companyEmail | $businessAddress";

    $logo = $companyAndOtherStaticImages['Company_Logo'];
    $name = $companyProfile->name;
    $companyWebsite = $companyProfile->company_website;
    $sequifiLogoWithName = $companyAndOtherStaticImages['sequifi_logo_with_name'];
@endphp

<div style="background-color: #f2f2f2;">
    <div class="" style=" height: auto; max-width: 650px; margin: 0px auto;">
        <div class="aHl"></div>
        <div tabindex="-1"></div>
        <div class="ii gt">
            <div class="a3s aiL">
                <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                    <tr>
                        <td>
                            <div align="center" style="padding: 15px; align-items: center;">
                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="background-color: #fff; border-radius: 5px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                <tr>
                                                    <td bgcolor="#FFFFFF" align="left">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            style="width: 100%; height: 100%">
                                                            <tr>
                                                                <td>
                                                                    <div style="text-align: center;">
                                                                        <img src="{{ $logo }}" alt=""
                                                                            style="width: 120px; height: 120px; margin: 0px auto;">
                                                                    </div>

                                                                    <h2
                                                                        style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; text-align: center; color: #424242; font-weight: 500;">
                                                                        Imported Sales Report</h2>
                                                                </td>
                                                            </tr>
                                                            @if (@$valid)
                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                            <div
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px;  color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    Dear <strong style="font-weight: 600; color: #424242;">{{ @$user->first_name }} {{ @$user->last_name }}</strong>,</p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 10px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    This is to confirm that your recent Sale Excel Import has been processed.</p>

                                                                                {{-- <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    Listed below are the
                                                                                    successes and errors encountered:</p> --}}

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    <strong style="font-weight: 600; color: #424242;">Notes:</strong>
                                                                                    <ol style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px;">
                                                                                        <li>
                                                                                            Successfully imported data is highlighted in green.
                                                                                        </li>
                                                                                        <li>
                                                                                            Any errors or failed imports are marked in red. If errors are present, please update your data and re-upload a corrected Excel file. If no errors are shown in the table below, no further action is needed.
                                                                                        </li>
                                                                                    </ol>
                                                                                </p>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    Should any additional issues arise, you will receive a follow-up email with details on the failure.</p>

                                                                                    <p
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px;">
                                                                                        If you need further assistance, feel free to reach out to your Sequifi System Administrator.</p>

                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @else
                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                            <div
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px;  color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    Dear <strong style="font-weight: 600; color: #424242;">{{ @$user->first_name }} {{ @$user->last_name }}</strong>,</p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 10px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    We regret to inform you
                                                                                    that your recent sales spreadsheet
                                                                                    encountered errors during import,
                                                                                    hindering successful processing.
                                                                                    Ensuring data accuracy is crucial, and
                                                                                    we apologize for any inconvenience
                                                                                    caused.</p>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    Listed below are the
                                                                                    successes and errors encountered:</p>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                font-weight: 500;
                                                                                line-height: 24px;">
                                                                                    <strong style="font-weight: 600; color: #424242;">Notes:</strong>
                                                                                    <ol style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px;">
                                                                                        <li>
                                                                                            The rows highlighted in red indicate failed imports. To correct these errors, please modify your data and import a new Excel file with the corrected information.
                                                                                        </li>
                                                                                        <li>
                                                                                            Apart from the rows highlighted in red, all other data will be processed successfully. If any additional data fails to import, you will receive another email with the appropriate reason for the failure.
                                                                                        </li>
                                                                                    </ol>
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                            @if (@$valid)
                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; width: 70%; margin: 0px auto;"
                                                                                class="table-mainParent">
                                                                                <table cellspacing="0" cellpadding="0"
                                                                                    style="margin-top: 20px; width: 100%;border-radius: 10px;border: 1px solid #e0e0e0;">
                                                                                    <thead
                                                                                        style="background-color: #e0e0e0; border-top-left-radius: 10px;">
                                                                                        <tr>
                                                                                            <th
                                                                                                style="border-top-left-radius: 10px;padding: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                #</th>
                                                                                            <th
                                                                                                style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                PID</th>
                                                                                            <th
                                                                                                style="border-top-right-radius: 10px;padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                Reason</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        @php
                                                                                            $i = 1;
                                                                                        @endphp
                                                                                        @foreach ($errorReports as $key => $errors)
                                                                                            <tr style="background-color: rgba(255, 51, 51, 0.1)">
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $i }}</td>
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $key }}</td>
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px">
                                                                                                <ui>
                                                                                                    @foreach ($errors as $error)
                                                                                                        <li>
                                                                                                            {{ $error }}
                                                                                                        </li>
                                                                                                    @endforeach
                                                                                                </ui>
                                                                                            </td>
                                                                                            </tr>
                                                                                            @php
                                                                                                $i++;
                                                                                            @endphp
                                                                                        @endforeach
                                                                                        @foreach ($successReports as $key => $errors)
                                                                                            <tr style="background-color: rgb(215, 249, 239)">
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $i }}</td>
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $key }}</td>
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px">
                                                                                                    <ui>
                                                                                                        @foreach ($errors as $error)
                                                                                                            <li>
                                                                                                                {{ $error }}
                                                                                                            </li>
                                                                                                        @endforeach
                                                                                                    </ui>
                                                                                                </td>
                                                                                            </tr>
                                                                                            @php
                                                                                                $i++;
                                                                                            @endphp
                                                                                        @endforeach
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @else
                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; width: 70%; margin: 0px auto;"
                                                                                class="table-mainParent">
                                                                                <table cellspacing="0" cellpadding="0"
                                                                                    style="margin-top: 20px; width: 100%;border-radius: 10px;border: 1px solid #e0e0e0;">
                                                                                    <thead
                                                                                        style="background-color: #e0e0e0; border-top-left-radius: 10px;">
                                                                                        <tr>
                                                                                            <th
                                                                                                style="border-top-left-radius: 10px;padding: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                PID</th>
                                                                                            <th
                                                                                                style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                Data Field</th>
                                                                                            <th
                                                                                                style="border-top-right-radius: 10px;padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                Reason</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    @if (@$isDev)
                                                                                        <tbody>
                                                                                            @foreach ($errorReports as $error)
                                                                                                <tr style="background-color: {{ $error['is_error'] ? 'rgba(255, 51, 51, 0.1)' : 'rgb(215, 249, 239)' }};">
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['pid'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['file'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['realMessage'] }} Line :- {{ $error['line'] }}</td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                            @foreach ($successReports as $error)
                                                                                                <tr style="background-color: {{ $error['is_error'] ? 'rgba(255, 51, 51, 0.1)' : 'rgb(215, 249, 239)' }};">
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['pid'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['file'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['realMessage'] }}</td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    @else
                                                                                        <tbody>
                                                                                            @foreach ($errorReports as $error)
                                                                                                <tr style="background-color: {{ $error['is_error'] ? 'rgba(255, 51, 51, 0.1)' : 'rgb(215, 249, 239)' }};">
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['pid'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['name'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['message'] }}</td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                            @foreach ($successReports as $error)
                                                                                                <tr style="background-color: {{ $error['is_error'] ? 'rgba(255, 51, 51, 0.1)' : 'rgb(215, 249, 239)' }};">
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['pid'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['name'] }}</td>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                                    font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                        {{ $error['message'] }}</td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    @endif
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                            <tr>
                                                                <td>
                                                                    <div style="padding: 5px 40px;">
                                                                        <div style="margin-top: 3px;">
                                                                            <div style="margin-top: 20px;">
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 20px;">
                                                                                    Best regards,</p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 5px;">
                                                                                    The <strong
                                                                                        style="color: #424242;font-size: 14px;
                                                                                    font-weight: 600;">{{  $name }}
                                                                                    </strong>Team</p>
                                                                                <div style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 80px;"></div>
                                                                                <div style="padding-top: 10px; text-align: center;">
                                                                                    <p
                                                                                        style="margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center;color: #757575;
                                                                                        font-size: 12px;
                                                                                        font-weight: 500;
                                                                                        line-height: 18px;">
                                                                                        {{ $footerContent }}
                                                                                    </p>
                                                                                    <p
                                                                                        style="font-weight: 500;font-size: 12px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                                                                                        © Copyright {{ date('Y') }} | <a
                                                                                            href="{{ $companyWebsite }}"
                                                                                            target="_blank"
                                                                                            style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879FE;font-size: 12px;text-decoration: none;">
                                                                                            {{ $companyWebsite }}
                                                                                        </a>| All rights reserved</p>
                                                                                    <table role="presentation"
                                                                                        cellspacing="0" cellpadding="0"
                                                                                        style="margin: auto; margin-bottom: 10px;">
                                                                                        <tr>
                                                                                            <td style="text-align: center;">
                                                                                                <p
                                                                                                    style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 12px;">
                                                                                                    Powered by
                                                                                                </p>
                                                                                            </td>
                                                                                            <td style="text-align: center;">
                                                                                                <img src="{{ $sequifiLogoWithName }}" alt="Sequifi" style="width: 100px;">
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

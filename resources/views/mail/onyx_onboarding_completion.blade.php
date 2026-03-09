<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Onboarding Completion - {{ $employee->first_name }} {{ $employee->last_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin-bottom: 30px;
        }
        .employee-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .employee-info h3 {
            color: #007bff;
            margin-top: 0;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            min-width: 150px;
        }
        .info-value {
            color: #6c757d;
            flex: 1;
            text-align: right;
        }
        .products-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .products-section h4 {
            color: #1976d2;
            margin-top: 0;
        }
        .products-list {
            list-style: none;
            padding: 0;
        }
        .products-list li {
            background: white;
            margin: 8px 0;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
        }
        .attachments-section {
            background: #fff3e0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .attachments-section h4 {
            color: #f57c00;
            margin-top: 0;
        }
        .attachment-item {
            background: white;
            margin: 10px 0;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #ffcc02;
            display: flex;
            align-items: center;
        }
        .attachment-icon {
            margin-right: 10px;
            font-size: 18px;
        }
        .footer {
            text-align: center;
            border-top: 2px solid #007bff;
            padding-top: 20px;
            margin-top: 30px;
            color: #6c757d;
            font-size: 14px;
        }
        .timestamp {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            color: #2e7d32;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Onboarding Completed</h1>
            <p>New Sales Representative Ready for Action</p>
        </div>

        <div class="content">
            <p>Hello Team,</p>
            <p>We're excited to inform you that <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong> has successfully completed the onboarding process and is now ready to start as a Sales Representative.</p>

            <div class="timestamp">
                ⏰ Onboarding Completed: {{ now()->format('F j, Y \a\t g:i A T') }}
            </div>

            <div class="employee-info">
                <h3>📋 Employee Information</h3>
                <div class="info-row">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value">{{ $employee->first_name }} {{ $employee->middle_name }} {{ $employee->last_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address:</span>
                    <span class="info-value">{{ $employee->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number:</span>
                    <span class="info-value">{{ $employee->mobile_no ?? 'Not provided' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee ID:</span>
                    <span class="info-value">{{ $employee->employee_id ?? 'Pending Assignment' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Start Date:</span>
                    <span class="info-value">{{ $employee->period_of_agreement_start_date ? \Carbon\Carbon::parse($employee->period_of_agreement_start_date)->format('F j, Y') : 'Not set' }}</span>
                </div>
            </div>

            @if(!empty($products) && count($products) > 0)
            <div class="products-section">
                <h4>🛍️ Products to Onboard</h4>
                <ul class="products-list">
                    @foreach($products as $product)
                        <li>{{ $product }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(!empty($licenseDocuments) && count($licenseDocuments) > 0)
            <div class="attachments-section">
                <h4>📎 Documents Submitted</h4>
                @php
                    $frontLicense = null;
                    $backLicense = null;
                    $badgePhoto = null;
                    $otherDocuments = [];
                    
                    foreach($licenseDocuments as $document) {
                        $description = strtolower($document['description'] ?? '');
                        if (strpos($description, 'front') !== false && strpos($description, 'license') !== false) {
                            $frontLicense = $document;
                        } elseif (strpos($description, 'back') !== false && strpos($description, 'license') !== false) {
                            $backLicense = $document;
                        } elseif (strpos($description, 'badge') !== false || strpos($description, 'photo') !== false) {
                            $badgePhoto = $document;
                        } else {
                            $otherDocuments[] = $document;
                        }
                    }
                @endphp
                
                @if($frontLicense)
                    <div class="attachment-item">
                        <span class="attachment-icon">🆔</span>
                        <div>
                            <strong>📄 Driver's License - Front</strong><br>
                            <small>{{ $frontLicense['description'] }}</small>
                            @if(!empty($frontLicense['files']))
                                @foreach($frontLicense['files'] as $file)
                                    @if(!empty($file['s3_signed_url']))
                                        <br><a href="{{ $file['s3_signed_url'] }}" target="_blank" style="color: #007bff; text-decoration: none; font-weight: bold;">
                                            📥 View Front License ({{ basename($file['file_path']) }})
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
                
                @if($backLicense)
                    <div class="attachment-item">
                        <span class="attachment-icon">🆔</span>
                        <div>
                            <strong>📄 Driver's License - Back</strong><br>
                            <small>{{ $backLicense['description'] }}</small>
                            @if(!empty($backLicense['files']))
                                @foreach($backLicense['files'] as $file)
                                    @if(!empty($file['s3_signed_url']))
                                        <br><a href="{{ $file['s3_signed_url'] }}" target="_blank" style="color: #007bff; text-decoration: none; font-weight: bold;">
                                            📥 View Back License ({{ basename($file['file_path']) }})
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
                
                @if($badgePhoto)
                    <div class="attachment-item">
                        <span class="attachment-icon">📷</span>
                        <div>
                            <strong>📸 Badge Photo</strong><br>
                            <small>{{ $badgePhoto['description'] }}</small>
                            @if(!empty($badgePhoto['files']))
                                @foreach($badgePhoto['files'] as $file)
                                    @if(!empty($file['s3_signed_url']))
                                        <br><a href="{{ $file['s3_signed_url'] }}" target="_blank" style="color: #007bff; text-decoration: none; font-weight: bold;">
                                            📥 View Badge Photo ({{ basename($file['file_path']) }})
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
                
                @foreach($otherDocuments as $document)
                    <div class="attachment-item">
                        <span class="attachment-icon">📄</span>
                        <div>
                            <strong>{{ $document['type_name'] ?? 'Document' }}</strong><br>
                            <small>{{ $document['description'] }}</small>
                            @if(!empty($document['files']))
                                @foreach($document['files'] as $file)
                                    @if(!empty($file['s3_signed_url']))
                                        <br><a href="{{ $file['s3_signed_url'] }}" target="_blank" style="color: #007bff; text-decoration: none; font-weight: bold;">
                                            📥 View {{ $document['type_name'] ?? 'Document' }} ({{ basename($file['file_path']) }})
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
                
                @if(!$frontLicense && !$backLicense && !$badgePhoto && empty($otherDocuments))
                    <div class="attachment-item">
                        <span class="attachment-icon">⚠️</span>
                        <div>
                            <strong style="color: #ff6b35;">Documents Not Found</strong><br>
                            <small>No documents were found in the system for this employee.</small>
                        </div>
                    </div>
                @endif
            </div>
            @else
            <div class="attachments-section">
                <h4>📎 Required Documents</h4>
                <div class="attachment-item">
                    <span class="attachment-icon">🆔</span>
                    <span>Driver's License - {{ $employee->first_name }}_{{ $employee->last_name }}_DriversLicense.pdf</span>
                </div>
                <div class="attachment-item">
                    <span class="attachment-icon">📷</span>
                    <span>Badge Photo - {{ $employee->first_name }}_{{ $employee->last_name }}_BadgePhoto.jpg</span>
                </div>
                <p style="color: #ff6b35; font-style: italic;">Note: License documents not found in system. Please verify document submission.</p>
            </div>
            @endif

            @if(!empty($licenseDocuments) && count($licenseDocuments) > 0)
                @php
                    $hasfront = false;
                    $hasBack = false;
                    foreach($licenseDocuments as $doc) {
                        $desc = strtolower($doc['description'] ?? '');
                        if (strpos($desc, 'Front Of Drivers License') !== false) $hasfront = true;
                        if (strpos($desc, 'Back Of Drivers License') !== false) $hasBack = true;
                    }
                @endphp
                
                @if($hasfront && $hasBack)
                    <p style="background: #e8f5e8; padding: 10px; border-radius: 5px; color: #2e7d32;">✅ Complete license documentation submitted - Both front and back of driver's license are available for download.</p>
                @elseif($hasfront || $hasBack)
                    <p style="background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;">⚠️ Partial license documentation - Only {{ $hasfront ? 'front' : 'back' }} of driver's license is available. Please verify complete submission.</p>
                @else
                    <p style="background: #e8f5e8; padding: 10px; border-radius: 5px; color: #2e7d32;">✅ License documents have been successfully submitted and are available for download.</p>
                @endif
            @else
                <p style="background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;">⚠️ License documents may need verification. Please check the document submission status.</p>
            @endif
            
            <p>Please reach out if you need any additional information or have questions about this new team member.</p>

            <p>Best regards,<br>
            <strong>Sequifi Onboarding System</strong><br>
            Fiber Onyx Team</p>
        </div>

        <div class="footer">
            <p>This is an automated notification from the Sequifi Onboarding System.</p>
            <p>Generated on {{ now()->format('F j, Y \a\t g:i A T') }}</p>
        </div>
    </div>
</body>
</html>

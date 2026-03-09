<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: #f44336; color: white; padding: 30px 20px; text-align: center; }
        .header h2 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .error { color: #f44336; font-weight: bold; font-size: 18px; margin: 20px 0; }
        .error-box { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0; }
        .details { background: #f9f9f9; padding: 15px; border-left: 4px solid #999; margin: 20px 0; }
        .details ul { margin: 10px 0; padding-left: 20px; }
        .details li { margin: 8px 0; }
        .steps { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .steps ol { margin: 10px 0; padding-left: 20px; }
        .steps li { margin: 8px 0; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>⚠️ Position Update Failed</h2>
        </div>
        
        <div class="content">
            <p>Hi <strong>{{ $userName }}</strong>,</p>
            
            <p class="error">Position '{{ $positionName }}' update encountered an error.</p>
            
            <div class="error-box">
                <strong>Error Message:</strong><br>
                {{ $errorMessage }}
            </div>
            
            <div class="details">
                <p><strong>What happened:</strong></p>
                <ul>
                    <li><strong>Position:</strong> {{ $positionName }}</li>
                    <li><strong>Status:</strong> Position configuration was saved</li>
                    <li><strong>Issue:</strong> User assignments processing failed</li>
                    <li><strong>Failed at:</strong> {{ $failedAt }}</li>
                </ul>
            </div>
            
            <div class="steps">
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Please try updating the position again</li>
                    <li>If the issue persists, contact support with the error message above</li>
                    <li>Check Horizon dashboard for detailed job logs</li>
                </ol>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from Sequifi.</p>
            <p>For assistance, contact <a href="mailto:support@sequifi.com">support@sequifi.com</a></p>
        </div>
    </div>
</body>
</html>

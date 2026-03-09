<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: #4CAF50; color: white; padding: 30px 20px; text-align: center; }
        .header h2 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .success { color: #4CAF50; font-weight: bold; font-size: 18px; margin: 20px 0; }
        .details { background: #f9f9f9; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
        .details ul { margin: 10px 0; padding-left: 20px; }
        .details li { margin: 8px 0; }
        .footer { padding: 20px; text-center; color: #666; font-size: 12px; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✅ Position Update Completed</h2>
        </div>
        
        <div class="content">
            <p>Hi <strong>{{ $userName }}</strong>,</p>
            
            <p class="success">Position '{{ $positionName }}' has been successfully updated!</p>
            
            <div class="details">
                <p><strong>Update Details:</strong></p>
                <ul>
                    <li><strong>Position:</strong> {{ $positionName }}</li>
                    <li><strong>Updated by:</strong> {{ $updatedBy }}</li>
                    <li><strong>Completed at:</strong> {{ $completedAt }}</li>
                </ul>
            </div>
            
            <p>All user assignments have been processed successfully.</p>
            
            <p>You can view the updated position configuration in the system.</p>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from Sequifi.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>

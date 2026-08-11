<!DOCTYPE html>
<html>
<head>
    <title>TrustNode Security Alert</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #d9534f; border-bottom: 2px solid #d9534f; padding-bottom: 10px;">TrustNode Security Alert</h2>
    
    <p>Your repository security scan has completed.</p>
    
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold; width: 40%;">Repository:</td>
            <td style="padding: 8px; border: 1px solid #dddddd;">{{ $repoName }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold;">Branch:</td>
            <td style="padding: 8px; border: 1px solid #dddddd;">{{ $branch }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold;">Scan Status:</td>
            <td style="padding: 8px; border: 1px solid #dddddd; color: green; font-weight: bold;">completed</td>
        </tr>
    </table>

    <h3 style="color: #333333; margin-top: 20px;">Vulnerabilities Discovered</h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; text-align: center;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="padding: 8px; border: 1px solid #dddddd;">Critical</th>
                <th style="padding: 8px; border: 1px solid #dddddd;">High</th>
                <th style="padding: 8px; border: 1px solid #dddddd;">Medium</th>
                <th style="padding: 8px; border: 1px solid #dddddd;">Low</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold; color: #d9534f;">{{ $counts['critical'] }}</td>
                <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold; color: #f0ad4e;">{{ $counts['high'] }}</td>
                <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold; color: #5bc0de;">{{ $counts['medium'] }}</td>
                <td style="padding: 8px; border: 1px solid #dddddd; font-weight: bold; color: #777777;">{{ $counts['low'] }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 30px;">
        TrustNode identified security issues that require your immediate attention.
    </p>

    <div style="margin: 30px 0; text-align: center;">
        <a href="{{ url('/scans/' . $scanUuid) }}" style="background-color: #0275d8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-right: 10px; display: inline-block;">View Findings</a>
        <a href="{{ url('/reports/' . $reportUuid) }}" style="background-color: #5cb85c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Download Report</a>
    </div>

    <p style="font-size: 12px; color: #777777; border-top: 1px solid #eeeeee; padding-top: 10px; margin-top: 30px;">
        This is an automated security notification from TrustNode. Please do not reply directly to this email.
    </p>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TrustNode Vulnerability Assessment Report</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #1f2937; padding: 40px; margin: 0; background-color: #f9fafb; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
        .header { border-bottom: 3px solid #1e3a8a; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
        .report-title { font-size: 22px; color: #4b5563; margin-top: 5px; }
        h2 { color: #1e3a8a; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-top: 40px; }
        h3 { color: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid #e5e7eb; text-align: left; }
        th { background-color: #f3f4f6; color: #374151; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 12px; font-weight: bold; border-radius: 4px; text-transform: uppercase; }
        .badge-critical { background-color: #fecaca; color: #991b1b; }
        .badge-high { background-color: #ffedd5; color: #9a3412; }
        .badge-medium { background-color: #fef9c3; color: #854d0e; }
        .badge-low { background-color: #e0f2fe; color: #075985; }
        .badge-info { background-color: #f3f4f6; color: #374151; }
        .finding-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px; margin-bottom: 25px; background-color: #fafafa; }
        .finding-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 15px; }
        .finding-title { font-size: 18px; font-weight: bold; margin: 0; color: #111827; }
        .code-block { background-color: #1e293b; color: #f8fafc; padding: 15px; font-family: 'Courier New', Courier, monospace; border-radius: 4px; overflow-x: auto; margin: 15px 0; font-size: 14px; }
        .section-desc { color: #4b5563; font-style: italic; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">TrustNode</div>
            <div class="report-title">Repository Vulnerability Assessment Report</div>
        </div>

        <h2>Executive Summary</h2>
        <table>
            <tr>
                <th>Repository</th>
                <td>{{ $repository->name }}</td>
                <th>Visibility</th>
                <td><span class="badge">{{ $repository->visibility }}</span></td>
            </tr>
            <tr>
                <th>Branch</th>
                <td>{{ $repository->default_branch }}</td>
                <th>Scan Date</th>
                <td>{{ now()->toDateTimeString() }}</td>
            </tr>
            <tr>
                <th>Total Findings</th>
                <td>{{ count($findings) }}</td>
                <th>Risk Rating</th>
                <td>
                    @if ($severityCounts['critical'] > 0 || $severityCounts['high'] > 0)
                        <span class="badge badge-critical">HIGH RISK</span>
                    @elseif ($severityCounts['medium'] > 0)
                        <span class="badge badge-medium">MEDIUM RISK</span>
                    @else
                        <span class="badge badge-low">LOW RISK</span>
                    @endif
                </td>
            </tr>
        </table>

        <h2>Severity Distribution</h2>
        <table>
            <thead>
                <tr>
                    <th>Critical</th>
                    <th>High</th>
                    <th>Medium</th>
                    <th>Low</th>
                    <th>Informational</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="color: #991b1b; font-weight: bold;">{{ $severityCounts['critical'] }}</td>
                    <td style="color: #9a3412; font-weight: bold;">{{ $severityCounts['high'] }}</td>
                    <td style="color: #854d0e; font-weight: bold;">{{ $severityCounts['medium'] }}</td>
                    <td style="color: #075985; font-weight: bold;">{{ $severityCounts['low'] }}</td>
                    <td style="color: #374151; font-weight: bold;">{{ $severityCounts['info'] }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Vulnerability Findings</h2>
        <p class="section-desc">The following security issues were detected by TrustNode static analysis engine.</p>

        @if (count($findings) === 0)
            <p style="text-align: center; color: green; font-weight: bold; margin: 40px 0;">No vulnerabilities detected in this repository.</p>
        @else
            @foreach ($findings as $f)
                <div class="finding-card">
                    <div class="finding-header">
                        <span class="finding-title">{{ $f->title }}</span>
                        <span class="badge badge-{{ strtolower($f->severity->value ?? $f->severity) }}">{{ $f->severity->value ?? $f->severity }}</span>
                    </div>
                    
                    <p><strong>Category:</strong> {{ $f->category }}</p>
                    @if ($f->cwe)
                        <p><strong>CWE:</strong> {{ $f->cwe }}</p>
                    @endif
                    <p><strong>File Location:</strong> <code>{{ $f->technical_details ?? $f->url }}</code></p>
                    
                    <p><strong>Description:</strong></p>
                    <p>{{ $f->description }}</p>

                    @if ($f->evidence)
                        <p><strong>Evidence (Masked):</strong></p>
                        <div class="code-block">{{ $f->evidence }}</div>
                    @endif

                    <p><strong>Remediation Guidance:</strong></p>
                    <p>{{ $f->remediation }}</p>
                </div>
            @endforeach
        @endif

        <h2>Scan Methodology & Limitations</h2>
        <p>This assessment was performed using static analysis rule matching (SAST). The scanner searches files for signatures matching known vulnerability classes, hardcoded credentials, and unsafe coding patterns. Static analysis can generate false positives or miss issues that only manifest during runtime execution. Remediation should be verified in a dedicated staging or testing environment.</p>

        <div style="margin-top: 50px; border-top: 1px solid #e5e7eb; padding-top: 20px; font-size: 12px; color: #9ca3af; text-align: center;">
            Report generated automatically by TrustNode on {{ now()->toDateTimeString() }}.
        </div>
    </div>
</body>
</html>

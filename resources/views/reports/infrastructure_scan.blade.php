<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TrustNode Vulnerability Assessment Report</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: white; }
        .page { padding: 40px; }
        
        /* Cover Page Styles */
        .cover-page { 
            position: relative;
            text-align: center;
            height: 90vh;
        }
        .cover-header { margin-top: 150px; }
        .cover-logo { font-size: 46px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 3px; }
        .cover-title { font-size: 38px; color: #111827; margin-top: 60px; font-weight: bold; }
        .cover-target { font-size: 20px; color: #4b5563; margin-top: 30px; font-family: 'Courier New', Courier, monospace; background: #f3f4f6; padding: 12px 24px; display: inline-block; border-radius: 8px; border: 1px solid #e5e7eb;}
        
        .cover-details-container { margin-top: 120px; }
        .cover-details { text-align: left; display: inline-block; background: #f8fafc; padding: 35px; border-radius: 8px; border: 1px solid #e2e8f0; width: 65%; }
        .cover-details table { width: 100%; border-collapse: collapse; margin: 0; }
        .cover-details th { text-align: left; padding: 12px; color: #64748b; font-size: 14px; text-transform: uppercase; border: none; background: transparent; width: 40%;}
        .cover-details td { text-align: left; padding: 12px; color: #0f172a; font-weight: bold; border: none; }
        
        .page-break { page-break-after: always; }

        /* Report Content Styles */
        .report-header { border-bottom: 3px solid #1e3a8a; padding-bottom: 20px; margin-bottom: 30px; }
        .report-logo { font-size: 24px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
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
        .finding-header { border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 15px; }
        .finding-title { font-size: 18px; font-weight: bold; margin: 0; color: #111827; }
        .finding-badge-container { float: right; margin-top: -30px; }
        .code-block { background-color: #1e293b; color: #f8fafc; padding: 15px; font-family: 'Courier New', Courier, monospace; border-radius: 4px; overflow-x: auto; margin: 15px 0; font-size: 14px; }
        .section-desc { color: #4b5563; font-style: italic; margin-bottom: 20px; }
    </style>
</head>
<body>
    <!-- COVER PAGE -->
    <div class="cover-page page">
        <div class="cover-header">
            <div class="cover-logo">TrustNode</div>
            <div class="cover-title">Infrastructure Security Assessment Report</div>
            <div class="cover-target">{{ $target }}</div>
        </div>
        
        <div class="cover-details-container">
            <div class="cover-details">
                <table>
                    <tr>
                        <th>Date of Scan</th>
                        <td>{{ $scan->completed_at ? $scan->completed_at->toDateTimeString() : now()->toDateTimeString() }}</td>
                    </tr>
                    <tr>
                        <th>Engine Used</th>
                        <td>{{ strtoupper(str_replace('_', ' ', $scan->engine->value ?? 'Infrastructure Scanner')) }}</td>
                    </tr>
                    <tr>
                        <th>Target Network/Host</th>
                        <td>{{ $target }}</td>
                    </tr>
                    <tr>
                        <th>Total Findings</th>
                        <td>{{ count($findings) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- PAGE BREAK -->
    <div class="page-break"></div>

    <!-- REPORT CONTENT -->
    <div class="page">
        <div class="report-header">
            <div class="report-logo">TrustNode</div>
            <div style="font-size: 18px; color: #4b5563; margin-top: 5px;">Infrastructure Vulnerability Assessment Report</div>
        </div>

        <h2>Executive Summary</h2>
        <table>
            <tr>
                <th>Target</th>
                <td>{{ $target }}</td>
                <th>Scan Type</th>
                <td>{{ strtoupper(str_replace('_', ' ', $scan->type->value ?? 'NETWORK IP')) }}</td>
            </tr>
            <tr>
                <th>Scan Engine</th>
                <td>{{ strtoupper(str_replace('_', ' ', $scan->engine->value ?? 'Native Infrastructure Scanner')) }}</td>
                <th>Scan Date</th>
                <td>{{ $scan->completed_at ? $scan->completed_at->toDateTimeString() : now()->toDateTimeString() }}</td>
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
                    <td style="color: #991b1b; font-weight: bold; font-size: 18px;">{{ $severityCounts['critical'] }}</td>
                    <td style="color: #9a3412; font-weight: bold; font-size: 18px;">{{ $severityCounts['high'] }}</td>
                    <td style="color: #854d0e; font-weight: bold; font-size: 18px;">{{ $severityCounts['medium'] }}</td>
                    <td style="color: #075985; font-weight: bold; font-size: 18px;">{{ $severityCounts['low'] }}</td>
                    <td style="color: #374151; font-weight: bold; font-size: 18px;">{{ $severityCounts['info'] }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Vulnerability Findings</h2>
        <p class="section-desc">The following security issues were detected by the TrustNode infrastructure analysis engine.</p>

        @if (count($findings) === 0)
            <p style="text-align: center; color: green; font-weight: bold; margin: 40px 0; padding: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">No vulnerabilities detected on this infrastructure target.</p>
        @else
            @foreach ($findings as $f)
                <div class="finding-card">
                    <div class="finding-header">
                        <div class="finding-title">{{ $f->title }}</div>
                        <div class="finding-badge-container">
                            <span class="badge badge-{{ strtolower($f->severity->value ?? $f->severity) }}">{{ $f->severity->value ?? $f->severity }}</span>
                        </div>
                    </div>
                    
                    <p><strong>Category:</strong> {{ $f->category }}</p>
                    @if ($f->cwe)
                        <p><strong>CWE:</strong> {{ $f->cwe }}</p>
                    @endif
                    <p><strong>Technical Details:</strong> <code>{{ $f->technical_details ?? $f->url }}</code></p>
                    @if ($f->cvss_score)
                        <p><strong>CVSS Score:</strong> {{ $f->cvss_score }}</p>
                    @endif
                    
                    <p><strong>Description:</strong></p>
                    <p>{{ $f->description }}</p>

                    @if ($f->evidence)
                        <p><strong>Evidence:</strong></p>
                        <div class="code-block">{{ $f->evidence }}</div>
                    @endif

                    @if ($f->remediation)
                        <p><strong>Remediation Guidance:</strong></p>
                        <p>{{ $f->remediation }}</p>
                    @endif
                </div>
            @endforeach
        @endif

        <h2>Scan Methodology & Limitations</h2>
        <p>This assessment was performed using network interactions from the TrustNode engine. The scanner identifies open ports, SSL/TLS configurations, and HTTP headers without executing potentially harmful exploits. Note that some infrastructure findings may require manual verification, and the scan relies on active connectivity to the target which may be blocked by firewalls or IPS devices.</p>

        <div style="margin-top: 50px; border-top: 1px solid #e5e7eb; padding-top: 20px; font-size: 12px; color: #9ca3af; text-align: center;">
            Report generated automatically by TrustNode on {{ now()->toDateTimeString() }}.
        </div>
    </div>
</body>
</html>

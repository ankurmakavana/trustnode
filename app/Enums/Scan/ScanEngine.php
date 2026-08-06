<?php

namespace App\Enums\Scan;

enum ScanEngine: string
{
    case NMAP = 'nmap';
    case OWASP_ZAP = 'owasp_zap';
    case NUCLEI = 'nuclei';
    case TRIVY = 'trivy';
    case NESSUS = 'nessus';
}

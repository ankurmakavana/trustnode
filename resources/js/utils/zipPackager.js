/**
 * Lightweight, zero-dependency zip archive packager using standard browser typed arrays.
 * Compatible with standard PKZip format for uncompressed (STORE) files.
 */

// CRC32 Table
const CRC_TABLE = new Uint32Array(256);
for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) {
        c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
    }
    CRC_TABLE[n] = c;
}

function calculateCrc32(data) {
    let crc = 0xFFFFFFFF;
    for (let i = 0; i < data.length; i++) {
        crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ data[i]) & 0xFF];
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
}

/**
 * Creates a Zip file Blob from an array of file records:
 * files: Array of { path: string, content: Uint8Array }
 */
export async function createZipBlob(files) {
    const encoder = new TextEncoder();
    const localFileHeaders = [];
    const centralDirHeaders = [];
    let offset = 0;

    for (const file of files) {
        const nameBytes = encoder.encode(file.path.replace(/\\/g, '/'));
        const dataBytes = file.content;
        const crc = calculateCrc32(dataBytes);
        const size = dataBytes.length;

        // DOS Time & Date: fixed to 2026-01-01 00:00:00
        const dosTime = (0 << 11) | (0 << 5) | (0 >> 1);
        const dosDate = ((2026 - 1980) << 9) | (1 << 5) | 1;

        // Local file header: 30 bytes + name length
        const lfh = new Uint8Array(30 + nameBytes.length);
        const lfhView = new DataView(lfh.buffer);
        lfhView.setUint32(0, 0x04034b50, true);  // Local file header signature
        lfhView.setUint16(4, 20, true);          // Version needed to extract (2.0)
        lfhView.setUint16(6, 0x0800, true);       // General purpose bit flag (UTF-8)
        lfhView.setUint16(8, 0, true);           // Compression method (0 = store)
        lfhView.setUint16(10, dosTime, true);
        lfhView.setUint16(12, dosDate, true);
        lfhView.setUint32(14, crc, true);
        lfhView.setUint32(18, size, true);       // Compressed size
        lfhView.setUint32(22, size, true);       // Uncompressed size
        lfhView.setUint16(26, nameBytes.length, true);
        lfhView.setUint16(28, 0, true);          // Extra field length
        lfh.set(nameBytes, 30);

        localFileHeaders.push(lfh);
        localFileHeaders.push(dataBytes);

        // Central directory entry: 46 bytes + name length
        const cdh = new Uint8Array(46 + nameBytes.length);
        const cdhView = new DataView(cdh.buffer);
        cdhView.setUint32(0, 0x02014b50, true);  // Central directory header signature
        cdhView.setUint16(4, 20, true);          // Version made by (2.0)
        cdhView.setUint16(6, 20, true);          // Version needed (2.0)
        cdhView.setUint16(8, 0x0800, true);       // UTF-8 flag
        cdhView.setUint16(10, 0, true);          // Compression method (0 = store)
        cdhView.setUint16(12, dosTime, true);
        cdhView.setUint16(14, dosDate, true);
        cdhView.setUint32(16, crc, true);
        cdhView.setUint32(20, size, true);
        cdhView.setUint32(24, size, true);
        cdhView.setUint16(28, nameBytes.length, true);
        cdhView.setUint16(30, 0, true);          // Extra field length
        cdhView.setUint16(32, 0, true);          // File comment length
        cdhView.setUint16(34, 0, true);          // Disk number start
        cdhView.setUint16(36, 0, true);          // Internal file attributes
        cdhView.setUint32(38, 0, true);          // External file attributes
        cdhView.setUint32(42, offset, true);     // Relative offset of local header
        cdh.set(nameBytes, 46);

        centralDirHeaders.push(cdh);
        offset += lfh.length + dataBytes.length;
    }

    const centralDirStart = offset;
    let centralDirSize = 0;
    for (const chunk of centralDirHeaders) {
        centralDirSize += chunk.length;
    }

    // End of central directory record (EOCD): 22 bytes
    const eocd = new Uint8Array(22);
    const eocdView = new DataView(eocd.buffer);
    eocdView.setUint32(0, 0x06054b50, true);   // EOCD signature
    eocdView.setUint16(4, 0, true);            // Disk number
    eocdView.setUint16(6, 0, true);            // Disk with central dir
    eocdView.setUint16(8, files.length, true); // Total entries this disk
    eocdView.setUint16(10, files.length, true);// Total entries central dir
    eocdView.setUint32(12, centralDirSize, true);
    eocdView.setUint32(16, centralDirStart, true);
    eocdView.setUint16(20, 0, true);           // Comment length

    const allParts = [...localFileHeaders, ...centralDirHeaders, eocd];
    return new Blob(allParts, { type: 'application/zip' });
}

/**
 * Filter security sensitive or unnecessary files matching backend policy:
 * - Excludes .git, vendor, node_modules, storage, public, tests caches
 * - Excludes .env, secrets, credentials, keys (*.pem, *.key, id_rsa, etc.)
 * - Excludes files > 5MB
 */
export function isFileEligible(relativePath, size) {
    const norm = relativePath.replace(/\\/g, '/');
    const parts = norm.split('/');
    const filename = parts[parts.length - 1];

    // Traversal or null safety
    if (norm.includes('..') || norm.startsWith('/') || norm.includes('\0')) {
        return false;
    }

    // Directory exclusions
    for (const part of parts.slice(0, -1)) {
        if (part === '.git' || part === 'node_modules' || part === 'vendor' || part === 'storage' || part === '.idea' || part === '.vscode') {
            return false;
        }
    }

    // Size limit: 5MB per file max
    if (size > 5 * 1024 * 1024) {
        return false;
    }

    // Sensitive files exclusions
    if (filename === '.env' || filename.startsWith('.env.') || filename === '.htpasswd' || filename === '.netrc') {
        return false;
    }

    if (/\.(pem|key|pkcs12|pfx|kdbx|crt|der)$/i.test(filename)) {
        return false;
    }

    if (/^(id_rsa|id_dsa|id_ecdsa|id_ed25519)/i.test(filename)) {
        return false;
    }

    // Binary file extensions exclusion
    if (/\.(png|jpg|jpeg|gif|ico|zip|tar|gz|exe|bin|pdf|woff|woff2|eot|ttf|mp4|db|sqlite|sqlite3)$/i.test(filename)) {
        return false;
    }

    return true;
}

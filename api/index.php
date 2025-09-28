<?php
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '256M');

$url = $_GET['url'] ?? '';
if (empty($url)) {
    die('No URL provided');
}

$url = urldecode($url);

$encoded_url = preg_replace_callback(
    '/[^a-z0-9\-\._~:\/?#\[\]@!$&\'()*+,;=]/i',
    function ($match) {
        return rawurlencode($match[0]);
    },
    $url
);

while (ob_get_level()) ob_end_clean();

function downloadFile($url) {
    // Extreme timeouts for very slow PS3 downloads
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 86400, // 24 hours timeout
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $head_context = stream_context_create([
        'ssl' => ['verify_peer' => false],
        'http' => ['method' => 'HEAD', 'timeout' => 300] // 5 minutes for HEAD
    ]);
    
    $file_size = 0;
    $headers = @get_headers($url, 1, $head_context);
    if ($headers && isset($headers['Content-Length'])) {
        $file_size = is_array($headers['Content-Length']) ? 
                    end($headers['Content-Length']) : $headers['Content-Length'];
    }
    
    $range_header = $_SERVER['HTTP_RANGE'] ?? '';
    $start = 0;
    $end = $file_size - 1;
    $is_resume = false;
    
    if ($range_header && preg_match('/bytes=(\d+)-(\d*)/i', $range_header, $matches)) {
        $is_resume = true;
        $start = intval($matches[1]);
        if (!empty($matches[2])) {
            $end = intval($matches[2]);
        }
        
        if ($start >= $file_size || $start < 0) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header('Content-Range: bytes */' . $file_size);
            exit;
        }
    }
    
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false],
        'http' => [
            'timeout' => 86400, // 24 hours
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'header' => $is_resume ? "Range: bytes=$start-" : ''
        ]
    ]);
    
    $remote = @fopen($url, 'rb', false, $context);
    if (!$remote) return false;
    
    $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'download.zip';
    
    if ($is_resume) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
        header('Content-Length: ' . ($end - $start + 1));
    } else {
        header('Content-Length: ' . $file_size);
    }
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    
    // Important: Prevent browser/timeout issues
    header('Cache-Control: no-cache, must-revalidate');
    
    if ($is_resume) {
        fseek($remote, $start);
    }
    
    $chunk_size = 8192;
    $bytes_sent = 0;
    $total_to_send = $is_resume ? ($end - $start + 1) : $file_size;
    
    while (!feof($remote) && $bytes_sent < $total_to_send) {
        $buffer = fread($remote, min($chunk_size, $total_to_send - $bytes_sent));
        echo $buffer;
        flush();
        
        // Prevent output buffering issues
        if (ob_get_level() > 0) ob_flush();
        
        $bytes_sent += strlen($buffer);
        
        if ($is_resume && $bytes_sent >= $total_to_send) {
            break;
        }
    }
    
    fclose($remote);
    return true;
}

if (!downloadFile($encoded_url)) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false],
        'http' => ['timeout' => 86400] // 24 hours fallback
    ]);
    
    $remote = @fopen($encoded_url, 'rb', false, $context);
    if ($remote) {
        $filename = basename(parse_url($encoded_url, PHP_URL_PATH)) ?: 'download.zip';
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, must-revalidate');
        
        while (!feof($remote)) {
            echo fread($remote, 8192);
            flush();
            if (ob_get_level() > 0) ob_flush();
        }
        fclose($remote);
    } else {
        die('Failed to download. The server may be blocking the request.');
    }
}

exit;
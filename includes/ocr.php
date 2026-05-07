<?php
// OCR service configuration
define('OCR_SPACE_API_KEY', 'K82457054888957');
define('OCR_SPACE_API_ENDPOINT', 'https://api.ocr.space/parse/image');

function ocr_space_parse_file($filePath, $language = 'eng') {
    if (!function_exists('curl_version')) {
        return ['success' => false, 'error' => 'cURL is required for OCR processing.'];
    }

    if (!file_exists($filePath)) {
        return ['success' => false, 'error' => 'Uploaded file is missing.'];
    }

    // Get file content and prepare CURLFile properly
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
    } else {
        // Fallback to getimagesize if finfo isn't available
        $imageInfo = getimagesize($filePath);
        $mimeType = $imageInfo['mime'] ?? 'application/octet-stream';
    }
    
    // Ensure we have a valid MIME type
    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        return ['success' => false, 'error' => "Unsupported file type: $mimeType. Please upload an image file."];
    }
    
    // Create a proper filename with extension
    $extension = '';
    switch($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $extension = '.jpg';
            break;
        case 'image/png':
            $extension = '.png';
            break;
        case 'image/gif':
            $extension = '.gif';
            break;
        case 'image/bmp':
            $extension = '.bmp';
            break;
        case 'image/tiff':
            $extension = '.tiff';
            break;
        case 'image/webp':
            $extension = '.webp';
            break;
        default:
            $extension = '.jpg';
    }
    
    $fileName = 'receipt_' . time() . '_' . rand(1000, 9999) . $extension;
    
    // Create CURLFile
    $curlFile = new CURLFile($filePath, $mimeType, $fileName);
    
    $payload = [
        'apikey' => OCR_SPACE_API_KEY,
        'language' => $language,
        'isOverlayRequired' => 'false',
        'file' => $curlFile,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => OCR_SPACE_API_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => false,
    ]);

    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => 'OCR request failed: ' . $error];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "OCR service returned HTTP $httpCode"];
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        return ['success' => false, 'error' => 'Invalid OCR response from server.'];
    }
    
    // Log the response for debugging (optional - remove in production)
    error_log("OCR API Response: " . json_encode($data));
    
    if (!empty($data['IsErroredOnProcessing'])) {
        $message = $data['ErrorMessage'] ?? $data['ErrorDetails'] ?? 'Unknown OCR error.';
        if (is_array($message)) {
            $message = implode(' | ', $message);
        }
        // Check if there are partial results we can use
        if (!empty($data['ParsedResults'][0]['ParsedText'])) {
            // Even if there was an error flag, we might still have some text
            $parsedText = trim($data['ParsedResults'][0]['ParsedText']);
            if ($parsedText) {
                return ['success' => true, 'text' => $parsedText, 'warning' => $message];
            }
        }
        return ['success' => false, 'error' => trim($message) ?: 'OCR processing failed.'];
    }

    $parsedText = $data['ParsedResults'][0]['ParsedText'] ?? null;
    
    if ($parsedText === null || trim($parsedText) === '') {
        return ['success' => false, 'error' => 'No text could be extracted from the image. Please ensure the receipt is clear and well-lit.'];
    }

    return ['success' => true, 'text' => trim($parsedText)];
}
?>
<?php
require_once '../includes/ocr.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Receipt upload failed. Please choose a valid image file.']);
    exit;
}

$uploadedFile = $_FILES['receipt'];

// Check for upload errors
if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    $errorMsg = $uploadErrors[$uploadedFile['error']] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// Get file info
$fileName = $uploadedFile['name'];
$fileTmpName = $uploadedFile['tmp_name'];
$fileSize = $uploadedFile['size'];
$fileError = $uploadedFile['error'];

// Validate file size (max 5MB)
if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File size too large. Maximum 5MB allowed.']);
    exit;
}

// Get file extension - try multiple methods
$fileExtension = '';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp'];

// Method 1: Get from original filename
$pathInfoExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($pathInfoExt && in_array($pathInfoExt, $allowedExtensions)) {
    $fileExtension = $pathInfoExt;
}
// Method 2: Detect via finfo (more reliable)
else {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mimeType = finfo_file($finfo, $fileTmpName);
        finfo_close($finfo);
        
        // Map MIME types to extensions
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/webp' => 'webp'
        ];
        
        if (isset($mimeMap[$mimeType])) {
            $fileExtension = $mimeMap[$mimeType];
        }
    }
}

// If we still don't have a valid extension, try to temporarily rename the file with an extension
if (!$fileExtension) {
    // Try to guess from the file content
    $imageInfo = getimagesize($fileTmpName);
    if ($imageInfo && isset($imageInfo['mime'])) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/webp' => 'webp'
        ];
        
        if (isset($mimeMap[$imageInfo['mime']])) {
            $fileExtension = $mimeMap[$imageInfo['mime']];
            // Create a temporary file with correct extension
            $tempFileWithExt = $fileTmpName . '.' . $fileExtension;
            copy($fileTmpName, $tempFileWithExt);
            $fileTmpName = $tempFileWithExt;
        }
    }
}

if (!$fileExtension) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload an image file (JPG, PNG, GIF, BMP, TIFF, WEBP).']);
    exit;
}

// Call OCR function
$result = ocr_space_parse_file($fileTmpName, 'eng');

// Clean up temporary file if we created one
if (isset($tempFileWithExt) && file_exists($tempFileWithExt) && $tempFileWithExt !== $uploadedFile['tmp_name']) {
    unlink($tempFileWithExt);
}

if (!$result['success']) {
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true, 'text' => $result['text']]);
?>
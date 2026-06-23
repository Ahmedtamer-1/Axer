<?php

namespace Lume\Controllers\Admin;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class MediaController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);

        $media = QueryBuilder::table('media')
            ->orderBy('id', 'desc')
            ->get();

        return $this->renderAdmin('media/index', [
            'title' => 'Media Library',
            'media' => $media
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->checkAuth($request);

        if (isset($_FILES['files']) && is_array($_FILES['files']['error'])) {
            $uploadDir = BASE_PATH . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileCount = count($_FILES['files']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['files']['name'][$i];
                    $tmpName = $_FILES['files']['tmp_name'][$i];
                    $fileSize = $_FILES['files']['size'][$i];
                    $mimeType = mime_content_type($tmpName);
                    
                    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $fileName = uniqid('media_', true) . '.' . $fileExtension;
                    $targetFile = $uploadDir . $fileName;
                    
                    // Image Compression using GD if it's an image
                    $isCompressed = false;
                    if (strpos($mimeType, 'image/') === 0 && in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp'])) {
                        // Max dimensions to scale down huge images
                        $maxWidth = 1920;
                        $maxHeight = 1080;
                        
                        list($origWidth, $origHeight) = getimagesize($tmpName);
                        
                        if ($origWidth > 0 && $origHeight > 0) {
                            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
                            $newWidth = round($origWidth * $ratio);
                            $newHeight = round($origHeight * $ratio);
                            
                            $imageTmp = null;
                            if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                                $imageTmp = @imagecreatefromjpeg($tmpName);
                            } elseif ($fileExtension === 'png') {
                                $imageTmp = @imagecreatefrompng($tmpName);
                            } elseif ($fileExtension === 'webp') {
                                $imageTmp = @imagecreatefromwebp($tmpName);
                            }
                            
                            if ($imageTmp) {
                                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                                
                                // Preserve transparency for PNG and WebP
                                if ($fileExtension === 'png' || $fileExtension === 'webp') {
                                    imagealphablending($newImage, false);
                                    imagesavealpha($newImage, true);
                                    $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                                    imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
                                }
                                
                                imagecopyresampled($newImage, $imageTmp, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                                
                                // Save compressed image
                                if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                                    imagejpeg($newImage, $targetFile, 80); // 80% quality
                                    $isCompressed = true;
                                } elseif ($fileExtension === 'png') {
                                    imagepng($newImage, $targetFile, 8); // 8 compression level
                                    $isCompressed = true;
                                } elseif ($fileExtension === 'webp') {
                                    imagewebp($newImage, $targetFile, 80); // 80% quality
                                    $isCompressed = true;
                                }
                                
                                imagedestroy($imageTmp);
                                imagedestroy($newImage);
                            }
                        }
                    }
                    
                    // Fallback to standard upload if not compressed
                    if (!$isCompressed) {
                        move_uploaded_file($tmpName, $targetFile);
                    }
                    
                    QueryBuilder::table('media')->insert([
                        'filename' => $fileName,
                        'original_name' => basename($originalName),
                        'path' => '/uploads/' . $fileName,
                        'mime_type' => $mimeType,
                        'size' => filesize($targetFile), // Get new compressed size
                        'folder' => 'general'
                    ]);
                }
            }
        }
        
        return $this->redirect('/admin/media');
    }

    public function delete(Request $request): Response
    {
        $this->checkAuth($request);

        $id = $request->post('id');
        if ($id) {
            $media = QueryBuilder::table('media')->where('id', $id)->first();
            if ($media) {
                $filePath = BASE_PATH . $media['path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                QueryBuilder::table('media')->where('id', $id)->delete();
                
                // Note: We leave product_images references intact to avoid 
                // breaking product listings, they will just show a broken image 
                // icon until the user uploads a new one, but ideally we'd clean them up.
            }
        }

        return $this->redirect('/admin/media');
    }

    public function apiList(Request $request): Response
    {
        $this->checkAuth($request);
        
        $media = QueryBuilder::table('media')
            ->orderBy('id', 'desc')
            ->get();
            
        return Response::json($media);
    }
}

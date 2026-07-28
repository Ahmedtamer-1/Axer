<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;

class MediaController extends AdminController
{
    /**
     * Extensions the library accepts, mapped to the MIME types
     * fileinfo is allowed to report for them.
     *
     * The previous handler accepted any file at all: it only branched on
     * extension to decide whether to *compress* the upload, and otherwise
     * copied it into content/uploads/ under its original extension with no
     * check whatsoever. A file named shell.php would have been saved as
     * shell.php. The site's .htaccess now refuses to execute PHP found
     * under content/uploads/, but that is a single point of failure on one
     * web server (it does nothing on nginx) — the upload itself must
     * reject the file.
     */
    private const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'svg' => ['image/svg+xml'],
        'pdf' => ['application/pdf'],
    ];

    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    public function index(Request $request): Response
    {
        $this->checkAuth($request);

        $media = QueryBuilder::table('media')->orderBy('id', 'desc')->get();

        return $this->renderAdmin('media/index', [
            'title' => 'Media Library',
            'media' => $media,
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->checkAuth($request);

        $files = $_FILES['files'] ?? null;

        if (!is_array($files) || !is_array($files['error'] ?? null)) {
            return $this->redirect('/admin/media');
        }

        $uploadDir = BASE_PATH . '/content/uploads/';

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            Session::flash('error', 'Could not prepare the uploads directory.');

            return $this->redirect('/admin/media');
        }

        $count = count($files['name']);
        $accepted = 0;
        $rejected = [];

        for ($i = 0; $i < $count; $i++) {
            $result = $this->storeOne($files, $i, $uploadDir);

            if ($result['ok']) {
                $accepted++;
            } elseif ($result['message'] !== null) {
                $rejected[] = $result['message'];
            }
        }

        if ($accepted > 0) {
            Session::flash('success', $accepted . ' file(s) uploaded.');
        }

        if ($rejected !== []) {
            Session::flash('error', implode(' ', array_slice($rejected, 0, 3)));
        }

        return $this->redirect('/admin/media');
    }

    /**
     * @return array{ok: bool, message: ?string}
     */
    protected function storeOne(array $files, int $index, string $uploadDir): array
    {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'message' => null];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'One file failed to upload.'];
        }

        $tmpName = $files['tmp_name'][$index];
        $originalName = (string) $files['name'][$index];
        $size = (int) ($files['size'][$index] ?? 0);

        if (!is_uploaded_file($tmpName)) {
            Logger::warning('Rejected an upload that was not a genuine HTTP upload.');

            return ['ok' => false, 'message' => null];
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'message' => "\"{$originalName}\" is too large (max 10 MB)."];
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED[$extension])) {
            return ['ok' => false, 'message' => "\"{$originalName}\" has a file type that is not allowed."];
        }

        // Trust the server's own inspection of the bytes, not the
        // extension the client claims, or the Content-Type it sent.
        $detectedMime = @mime_content_type($tmpName) ?: '';

        if (!in_array($detectedMime, self::ALLOWED[$extension], true)) {
            Logger::warning('Upload extension/content mismatch', [
                'name' => $originalName,
                'extension' => $extension,
                'detected' => $detectedMime,
            ]);

            return ['ok' => false, 'message' => "\"{$originalName}\" does not look like a valid {$extension} file."];
        }

        // SVG can carry <script>; strip anything active before it is
        // served back to a browser.
        if ($extension === 'svg' && !$this->sanitizeSvg($tmpName)) {
            return ['ok' => false, 'message' => "\"{$originalName}\" could not be safely processed."];
        }

        // The filename is fully server-generated — the client's name is
        // never used to build a path, so there is no directory-traversal
        // surface regardless of what the upload was called.
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetFile = $uploadDir . $fileName;

        $compressed = $this->compressImage($tmpName, $targetFile, $extension);

        if (!$compressed && !move_uploaded_file($tmpName, $targetFile)) {
            return ['ok' => false, 'message' => "Could not save \"{$originalName}\"."];
        }

        try {
            QueryBuilder::table('media')->insert([
                'filename' => $fileName,
                'original_name' => basename($originalName),
                'path' => '/content/uploads/' . $fileName,
                'mime_type' => $detectedMime,
                'size' => filesize($targetFile) ?: $size,
                'folder' => 'general',
            ]);
        } catch (\Throwable $e) {
            @unlink($targetFile);
            Logger::exception($e);

            return ['ok' => false, 'message' => "Could not save \"{$originalName}\"."];
        }

        return ['ok' => true, 'message' => null];
    }

    protected function compressImage(string $tmpName, string $targetFile, string $extension): bool
    {
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || !extension_loaded('gd')) {
            return false;
        }

        $size = @getimagesize($tmpName);

        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return false;
        }

        [$origWidth, $origHeight] = $size;
        $maxWidth = 1920;
        $maxHeight = 1080;

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newWidth = max(1, (int) round($origWidth * $ratio));
        $newHeight = max(1, (int) round($origHeight * $ratio));

        $source = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($tmpName),
            'png' => @imagecreatefrompng($tmpName),
            'webp' => @imagecreatefromwebp($tmpName),
            default => false,
        };

        if ($source === false) {
            return false;
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($extension === 'png' || $extension === 'webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $ok = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($resized, $targetFile, 85),
            'png' => imagepng($resized, $targetFile, 8),
            'webp' => imagewebp($resized, $targetFile, 85),
            default => false,
        };

        imagedestroy($source);
        imagedestroy($resized);

        return $ok;
    }

    /**
     * Strip <script>, event handler attributes and external references from
     * an uploaded SVG before writing it to disk — an SVG is XML, and
     * without this an "image" upload is a stored-XSS vector.
     */
    protected function sanitizeSvg(string $tmpName): bool
    {
        $contents = file_get_contents($tmpName);

        if ($contents === false) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($contents, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return false;
        }

        $xpath = new \DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('//*[local-name()="script"]') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (iterator_to_array($xpath->query('//@*') ?: []) as $attr) {
            $name = strtolower($attr->nodeName);

            if (str_starts_with($name, 'on') || $name === 'href' && str_starts_with(trim($attr->nodeValue), 'javascript:')) {
                $attr->ownerElement?->removeAttribute($attr->nodeName);
            }
        }

        return file_put_contents($tmpName, $doc->saveXML()) !== false;
    }

    public function delete(Request $request): Response
    {
        $this->checkAuth($request);

        $id = $request->int('id');

        if ($id <= 0) {
            return $this->redirect('/admin/media');
        }

        $media = QueryBuilder::table('media')->where('id', $id)->first();

        if ($media !== null) {
            $filePath = BASE_PATH . $media['path'];

            // Confirm the resolved path is still inside content/uploads
            // before deleting — a defensive check in case `path` was ever
            // stored some other way than by this controller.
            $uploadsRoot = realpath(BASE_PATH . '/content/uploads');
            $resolved = realpath($filePath);

            if ($uploadsRoot !== false && $resolved !== false && str_starts_with($resolved, $uploadsRoot)) {
                @unlink($resolved);
            }

            QueryBuilder::table('media')->where('id', $id)->delete();
        }

        return $this->redirect('/admin/media');
    }

    public function apiList(Request $request): Response
    {
        $this->checkAuth($request);

        $media = QueryBuilder::table('media')->orderBy('id', 'desc')->get();

        return Response::json($media);
    }
}

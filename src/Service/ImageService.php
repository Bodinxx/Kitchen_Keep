<?php
declare(strict_types=1);
namespace App\Service;
final class ImageService
{
    private array $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    public function upload(array $file, string $recipeId): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \RuntimeException('Image upload failed.');
        $tmpName = $file['tmp_name'] ?? ''; if (!is_string($tmpName) || !is_uploaded_file($tmpName)) throw new \RuntimeException('Invalid upload source.');
        $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = $finfo ? finfo_file($finfo, $tmpName) : null; if ($finfo) finfo_close($finfo);
        if (!is_string($mime) || !isset($this->allowedMime[$mime])) throw new \RuntimeException('Unsupported image format.');
        $extension = $this->allowedMime[$mime]; $filename = slugify($recipeId . '-' . pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_FILENAME)) . '-' . bin2hex(random_bytes(4)) . '.' . $extension; $originalPath = DATA_PATH . '/media/' . $filename;
        if (!move_uploaded_file($tmpName, $originalPath)) throw new \RuntimeException('Unable to store uploaded image.');
        $this->thumbnail($filename, 720, 480); return $filename;
    }
    public function thumbnail(string $filename, int $w, int $h): string
    {
        $source = DATA_PATH . '/media/' . basename($filename); $target = PUBLIC_PATH . '/uploads/' . basename($filename); if (!is_file($source)) return '/assets/img/placeholder.svg';
        if (!extension_loaded('gd')) { copy($source, $target); return '/uploads/' . basename($filename); }
        $info = getimagesize($source); if ($info === false) { copy($source, $target); return '/uploads/' . basename($filename); }
        [$srcW, $srcH, $type] = $info; $src = match ($type) { IMAGETYPE_JPEG => imagecreatefromjpeg($source), IMAGETYPE_PNG => imagecreatefrompng($source), IMAGETYPE_GIF => imagecreatefromgif($source), IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : false, default => false, };
        if (!$src) { copy($source, $target); return '/uploads/' . basename($filename); }
        $ratio = min($w / $srcW, $h / $srcH); $newW = (int) max(1, floor($srcW * $ratio)); $newH = (int) max(1, floor($srcH * $ratio)); $thumb = imagecreatetruecolor($newW, $newH); imagealphablending($thumb, false); imagesavealpha($thumb, true); imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        match ($type) { IMAGETYPE_JPEG => imagejpeg($thumb, $target, 88), IMAGETYPE_PNG => imagepng($thumb, $target), IMAGETYPE_GIF => imagegif($thumb, $target), IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($thumb, $target, 88) : imagejpeg($thumb, $target, 88), default => copy($source, $target), };
        imagedestroy($src); imagedestroy($thumb); return '/uploads/' . basename($filename);
    }
    public function delete(string $filename): void { foreach ([DATA_PATH . '/media/' . basename($filename), PUBLIC_PATH . '/uploads/' . basename($filename)] as $path) if (is_file($path)) unlink($path); }
    public function getUrl(string $filename): string { $name = basename($filename); return $name !== '' && is_file(PUBLIC_PATH . '/uploads/' . $name) ? '/uploads/' . $name : '/assets/img/placeholder.svg'; }
}

<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ZipArchive;

class FileContentValidator
{
    /** @var array<string, string> */
    private const LABELS = [
        'csv' => 'CSV',
        'txt' => 'TXT',
        'json' => 'JSON',
        'xlsx' => 'XLSX',
        'xls' => 'XLS',
        'docx' => 'DOCX',
        'pdf' => 'PDF',
        'jpg' => 'JPG',
        'jpeg' => 'JPEG',
        'png' => 'PNG',
        'gif' => 'GIF',
        'webp' => 'WebP',
        'bmp' => 'BMP',
    ];

    /**
     * Validate the client extension against the actual file structure.
     *
     * @param  list<string>  $allowedExtensions
     */
    public function validate(UploadedFile $file, array $allowedExtensions): void
    {
        if (! $file->isValid() || ! is_readable($file->getRealPath())) {
            throw new InvalidArgumentException('The uploaded file could not be read. Select the file again and retry.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = array_values(array_unique(array_map('strtolower', $allowedExtensions)));
        if (! in_array($extension, $allowedExtensions, true)) {
            $labels = array_map(fn (string $allowed): string => self::LABELS[$allowed] ?? strtoupper($allowed), $allowedExtensions);

            throw new InvalidArgumentException('Unsupported file type. Upload a '.implode(', ', $labels).' file.');
        }

        if (($file->getSize() ?? 0) <= 0) {
            throw new InvalidArgumentException('The uploaded file is empty. Select a file containing data.');
        }

        $path = $file->getRealPath();
        $error = match ($extension) {
            'xlsx' => $this->officeArchiveError($path, 'xl/worksheets/', 'Excel'),
            'docx' => $this->officeArchiveError($path, 'word/document.xml', 'Word'),
            'pdf' => $this->pdfError($path),
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' => $this->imageError($path, $extension),
            'csv', 'txt', 'json', 'xls' => $this->textError($path, $extension),
            default => null,
        };

        if ($error !== null) {
            Log::notice('Rejected uploaded file after content inspection.', [
                'filename' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                'extension' => $extension,
                'reason' => $error,
            ]);

            throw new InvalidArgumentException($error);
        }
    }

    private function officeArchiveError(string $path, string $requiredEntry, string $label): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return "The uploaded {$label} file could not be read. The file may be corrupted or invalid.";
        }

        if ($zip->locateName('xl/vbaProject.bin') !== false
            || $zip->locateName('word/vbaProject.bin') !== false
            || $this->zipContainsPrefix($zip, 'xl/macrosheets/')) {
            $zip->close();

            return "Macro-enabled {$label} files are not supported. Upload a standard file without macros.";
        }

        $found = str_ends_with($requiredEntry, '/')
            ? $this->zipContainsPrefix($zip, $requiredEntry)
            : $zip->locateName($requiredEntry) !== false;
        $zip->close();

        return $found
            ? null
            : "The uploaded {$label} file has an invalid structure or does not match its extension.";
    }

    private function zipContainsPrefix(ZipArchive $zip, string $prefix): bool
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_starts_with($name, $prefix) && str_ends_with($name, '.xml')) {
                return true;
            }
        }

        return false;
    }

    private function pdfError(string $path): ?string
    {
        $header = file_get_contents($path, false, null, 0, 1024);

        return is_string($header) && str_starts_with(ltrim($header), '%PDF-')
            ? null
            : 'The file content does not match the .pdf extension. Upload a valid PDF file.';
    }

    private function imageError(string $path, string $extension): ?string
    {
        $image = @getimagesize($path);
        if ($image === false || empty($image['mime'])) {
            return 'The uploaded image is corrupted or invalid.';
        }

        $expected = match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        };

        return in_array($image['mime'], $expected, true)
            ? null
            : "The image content does not match the .{$extension} extension.";
    }

    private function textError(string $path, string $extension): ?string
    {
        $sample = file_get_contents($path, false, null, 0, 8192);
        if (! is_string($sample)) {
            return 'The uploaded file could not be read. Select the file again and retry.';
        }

        // Let the existing XLS reader return its specific conversion guidance
        // for genuine legacy BIFF/OLE workbooks.
        if ($extension === 'xls' && str_starts_with($sample, "\xD0\xCF\x11\xE0")) {
            return null;
        }

        $trimmed = ltrim($sample);
        $isUtf16 = str_starts_with($sample, "\xFF\xFE") || str_starts_with($sample, "\xFE\xFF");
        $hasForeignSignature = str_starts_with($trimmed, '%PDF-')
            || str_starts_with($sample, "PK\x03\x04")
            || str_starts_with($sample, 'MZ')
            || str_starts_with($sample, "\x7FELF")
            || str_starts_with($sample, "\x89PNG")
            || str_starts_with($sample, "\xFF\xD8\xFF")
            || preg_match('/^RIFF.{4}WEBP/s', $sample) === 1
            || preg_match('/^\s*(?:<\?php|<script\b|#![^\r\n]*(?:sh|python|node|perl))/i', $sample) === 1;
        $hasBinaryControls = ! $isUtf16 && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $sample) === 1;

        if ($hasForeignSignature || $hasBinaryControls) {
            return "The file content does not match the .{$extension} extension. Upload a valid ".(self::LABELS[$extension] ?? strtoupper($extension)).' file.';
        }

        return null;
    }
}

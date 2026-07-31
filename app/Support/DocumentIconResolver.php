<?php

namespace App\Support;

/**
 * Maps a document's file extension to a Tabler icon name (see
 * app/helpers.php's icon()) for the "Documenti" entity's folder/file
 * listing (resources/views/documents/index.blade.php). Falls back to a
 * generic "file" icon for anything not explicitly mapped — deliberately
 * not exhaustive, just the extensions common enough to be worth a
 * distinct glyph.
 */
class DocumentIconResolver
{
    /**
     * @var array<string, string>
     */
    private const ICONS_BY_EXTENSION = [
        'pdf' => 'file-type-pdf',
        'doc' => 'file-type-doc',
        'docx' => 'file-type-docx',
        'xls' => 'file-type-xls',
        'xlsx' => 'file-type-xls',
        'ppt' => 'file-type-ppt',
        'pptx' => 'file-type-ppt',
        'csv' => 'file-type-csv',
        'txt' => 'file-type-txt',
        'zip' => 'file-type-zip',
        'rar' => 'file-zip',
        '7z' => 'file-zip',
        'jpg' => 'file-type-jpg',
        'jpeg' => 'file-type-jpg',
        'png' => 'file-type-png',
        'gif' => 'photo',
        'webp' => 'photo',
        'svg' => 'file-type-svg',
        'bmp' => 'file-type-bmp',
        'html' => 'file-type-html',
        'css' => 'file-type-css',
        'xml' => 'file-type-xml',
        'mp4' => 'video',
        'avi' => 'video',
        'mov' => 'video',
        'mp3' => 'file-music',
        'wav' => 'file-music',
    ];

    private const DEFAULT_ICON = 'file';

    public static function forFilename(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return self::ICONS_BY_EXTENSION[$extension] ?? self::DEFAULT_ICON;
    }
}

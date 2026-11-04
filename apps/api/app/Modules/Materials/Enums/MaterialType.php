<?php

namespace App\Modules\Materials\Enums;

enum MaterialType: string
{
    case Pdf = 'pdf';
    case Image = 'image';
    case Video = 'video';
    case ExternalVideo = 'external_video';
    case Link = 'link';
    case Document = 'document';
    case Presentation = 'presentation';
    case Spreadsheet = 'spreadsheet';
    case Audio = 'audio';
    case Other = 'other';

    public function requiresUrl(): bool
    {
        return in_array($this, [self::ExternalVideo, self::Link], true);
    }

    /** Classe de arquivo aceita; null em "Outro" aceita qualquer formato autorizado. */
    public function fileClass(): ?string
    {
        return match ($this) {
            self::Pdf, self::Image, self::Video, self::Document, self::Presentation, self::Spreadsheet, self::Audio => $this->value,
            default => null,
        };
    }
}

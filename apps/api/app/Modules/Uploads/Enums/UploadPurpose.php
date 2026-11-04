<?php

namespace App\Modules\Uploads\Enums;

enum UploadPurpose: string
{
    /** Arquivo de material: bucket privado, entregue por URL temporária. */
    case Material = 'material';

    /** Capa, foto ou banner: bucket público, com variantes em WebP. */
    case Image = 'image';
}

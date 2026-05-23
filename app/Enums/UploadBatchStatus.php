<?php

namespace App\Enums;

enum UploadBatchStatus: string
{
    case Queued = 'queued';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';
}

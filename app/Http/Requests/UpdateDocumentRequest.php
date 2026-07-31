<?php

namespace App\Http\Requests;

/**
 * Same rules as uploading, except the file itself is optional — see
 * StoreDocumentRequest::fileRequired().
 */
class UpdateDocumentRequest extends StoreDocumentRequest
{
    protected function fileRequired(): bool
    {
        return false;
    }
}

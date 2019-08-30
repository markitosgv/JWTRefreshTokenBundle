<?php

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository as OldDocumentRepository;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository as ForwardDocumentRepository;

// @todo: remove this condition and extend directly from `ForwardDocumentRepository`
// when support for "doctrine/mongodb-odm <2.0" is removed.
if (class_exists(ForwardDocumentRepository::class, false)) {
    class DocumentRepository extends ForwardDocumentRepository
    {
    }
} else {
    class DocumentRepository extends OldDocumentRepository
    {
    }
}

<?php

namespace App\Enums;

/** The states a Subject moves through, per CONTEXT.md. */
enum SubjectState: string
{
    case Backlog = 'backlog';
    case Watching = 'watching';
    case Rising = 'rising';
    case Trending = 'trending';
    case Mainstream = 'mainstream';
    case Detrending = 'detrending';
    case Archived = 'archived';
}

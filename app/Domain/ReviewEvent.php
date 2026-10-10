<?php
declare(strict_types=1);

namespace App\Domain;

/** What happened in review, for the job stage it implies (ReviewRules::stageMoves). Go: type ReviewEvent string. */
enum ReviewEvent: string
{
    /** A maker handed in a part. */
    case Submitted = 'submitted';
    /** Request review on a post, every post handed in, or a CD asked the ECD to review. */
    case ReviewRequested = 'review_requested';
    /** A reviewer rejected a part. */
    case Rejected = 'rejected';
    /** The ECD approved a CD-approved job. */
    case EcdApproved = 'ecd_approved';
}

<?php

namespace App\Enums\Forms;

enum ApprovalStatus: string
{
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

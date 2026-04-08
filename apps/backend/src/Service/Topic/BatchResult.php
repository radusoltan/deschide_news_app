<?php

declare(strict_types=1);

namespace App\Service\Topic;

class BatchResult
{
    public int $classified = 0;
    public int $skipped = 0;
    public int $totalAssignments = 0;
    public int $failedChunks = 0;
    public int $failedArticles = 0;
}

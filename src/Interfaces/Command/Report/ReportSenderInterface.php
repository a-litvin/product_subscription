<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Interfaces\Command\Report;

use BelVG\ProductSubscription\Entity\Subscription;

interface ReportSenderInterface
{
    /**
     * Successful items
     */
    const SUCCESSFUL_STATUS = 'successful';

    /**
     * Failed items
     */
    const FAILED_STATUS = 'failed';

    /**
     * @param string $message
     * @return bool|int
     */
    public function send(string $message);

    /**
     * @param string $message
     * @param int $status
     * @param Subscription $subscription
     * @return ReportSenderInterface
     */
    public function addReportItem(Subscription $subscription, int $status, string $message = ''): ReportSenderInterface;
}

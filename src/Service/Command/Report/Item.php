<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Command\Report;

use BelVG\ProductSubscription\Entity\Subscription;

class Item
{
    /**
     * Successful items
     */
    const SUCCESSFUL = 1;

    /**
     * Failed items
     */
    const FAILED = 0;

    /**
     * @var Subscription
     */
    private $subscription;

    /**
     * @var string
     */
    private $message;

    /**
     * @var int
     */
    private $status;

    /**
     * @param Subscription $subscription
     * @param int $status
     * @param string $message
     */
    public function __construct(
        Subscription $subscription,
        int $status,
        string $message = ''
    ) {
        $this->subscription = $subscription;
        $this->message = $message;
        $this->status = $status;
    }

    /**
     * @return Subscription
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param Subscription $subscription
     * @return $this
     */
    public function setSubscription(Subscription $subscription): Item
    {
        $this->subscription = $subscription;
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): Item
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): Item
    {
        $this->status = $status;
        return $this;
    }
}

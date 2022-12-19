<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Command\Report\Renderer;

use BelVG\ProductSubscription\Interfaces\Command\Report\Renderer\ReportItemsRendererInterface;
use BelVG\ProductSubscription\Service\Command\Report\Item;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Context;
use Customer;

class MailReportItemsRenderer implements ReportItemsRendererInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $successful = [];

    /**
     * @var array
     */
    private $failed = [];

    /**
     * @param ContainerInterface $container
     * @param array $successful
     * @param array $failed
     */
    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function render(array $items): string
    {
        return $this->prepareItems($items)->container->get('twig')->render(
            '@Modules/productsubscription/mails/templates/cron_items.twig',
            [
                'successful' => $this->successful,
                'failed' => $this->failed,
                'locale' => Context::getContext()->language->locale
            ]
        );
    }

    /**
     * @param Item[] $items
     * @return $this
     */
    private function prepareItems(array $items): MailReportItemsRenderer
    {
        $preparedItems = [];
        /** @var Item $item */
        foreach ($items as $item) {
            $subscription = $item->getSubscription();
            /** @var Customer */
            $customer = $this->container->get('prestashop.adapter.data_provider.customer')->getCustomer($subscription->getCustomerId());
            $preparedItems[$item->getStatus()][] = [
                'id' => $subscription->getId(),
                'customer' => $customer->email,
                'name' => $subscription->getName(),
                'message' => $item->getMessage()
            ];
        }
        $this->successful = $preparedItems[Item::SUCCESSFUL] ?? [];
        $this->failed = $preparedItems[Item::FAILED] ?? [];
        return $this;
    }
}

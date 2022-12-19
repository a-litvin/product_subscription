<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Interfaces\Command\Report\Renderer;

use BelVG\ProductSubscription\Service\Command\Report\Item;

interface ReportItemsRendererInterface
{
    /**
     * @param Item[] $items
     * @return string
     */
    public function render(array $items): string;
}

<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Command\Report\Sender;

use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Interfaces\Command\Report\ReportSenderInterface;
use BelVG\ProductSubscription\Interfaces\Command\Report\Renderer\ReportItemsRendererInterface;
use BelVG\ProductSubscription\Service\Command\Report\Item as ReportItem;
use Context;
use Configuration;
use Mail;

class MailSender implements ReportSenderInterface
{
    /**
     * @var ReportItemsRendererInterface
     */
    private $itemsRenderer;

    /**
     * @var ReportItem[]
     */
    protected $reportItems = [];

    /**
     * @param ReportItemsRendererInterface $itemsRenderer
     */
    public function __construct(
        ReportItemsRendererInterface $itemsRenderer
    ) {
        $this->itemsRenderer = $itemsRenderer;
    }

    /**
     * @inheritDoc
     */
    public function send(string $message)
    {
        $successful = $this->reportItems[ReportItem::SUCCESSFUL] ?? [];
        $failed = $this->reportItems[ReportItem::FAILED] ?? [];
        $subject = Context::getContext()->getTranslator()->trans('Autoship: successful %s / failed %s', [count($successful), count($failed)]);
        $itemsContent = $this->itemsRenderer->render(array_merge($successful, $failed));
		$emailsList = array('k.suvorev@belvg.com', 'alitvin@belvg.com', 'casetest803@gmail.com');
		foreach ($emailsList as $email) {
			Mail::send(
				Context::getContext()->language->id,
				'productsubscription_cron',
				$subject,
				[
					'title_message' => $message,
					'content_message' => $itemsContent
				],
				$email,
				null,
				null,
				null,
				null,
				null,
				_PS_MODULE_DIR_.'productsubscription/mails/'
			);
		};

        return Mail::send(
            Context::getContext()->language->id,
            'productsubscription_cron',
            $subject,
            [
                'title_message' => $message,
                'content_message' => $itemsContent
            ],
            Configuration::get('PRODUCT_SUBSCRIPTION_CRON_EMAIL', null, null, null, Configuration::get('PS_SHOP_EMAIL')),
            null,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_.'productsubscription/mails/'
        );
    }

    /**
     * ToDo: develop and use Factory for report items creating
     * @inheritDoc
     */
    public function addReportItem(Subscription $subscription, int $status, string $message = ''): ReportSenderInterface
    {
        $item = new ReportItem($subscription, $status, $message);
        $this->reportItems[$status][] = $item;
        return $this;
    }
}

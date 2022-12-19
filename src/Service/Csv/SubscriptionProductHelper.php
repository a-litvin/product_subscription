<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Csv;

use BelVG\ProductSubscription\DTO\ProductDTO;
use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionProduct;
use BelVG\ProductSubscription\ReadModel\ProductReadModel;
use BelVG\ProductSubscription\Service\Cart\CartCloneService;
use Cart;
use Context;
use Currency;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Adapter\Validate;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use PrestaShop\PrestaShop\Core\Import\File\CsvFileReader;
use PrestaShop\PrestaShop\Core\Import\File\DataRow\DataRowInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SubscriptionProductHelper extends AbstractHelper
{
    private const HEADER_COLUMNS = [
        'id_subscription' => 0,
        'id_cart' => 1,
        'id_product' => 2,
        'id_product_attribute' => 3,
        'qty' => 4,
        'id_periodicity' => 5,
        'first_delivery' => 6,
        'status' => 7,
        'add_date' => 8,
        'only_next' => 9,
        'set_id' => 10,
        'skip_next' => 11,
        'order_status' => 12,
    ];
    /**
     * @var CartCloneService
     */
    private $cartCloneService;
    /**
     * @var ProductReadModel
     */
    private $productReadModel;

    /**
     * @param CsvFileReader $csvFileReader
     * @param EntityManagerInterface $entityManager
     * @param CartCloneService $cartCloneService
     * @param ProductReadModel $productReadModel
     */
    public function __construct(
        CsvFileReader $csvFileReader,
        EntityManagerInterface $entityManager,
        CartCloneService $cartCloneService,
        ProductReadModel $productReadModel
    ) {
        parent::__construct($csvFileReader, $entityManager);
        $this->cartCloneService = $cartCloneService;
        $this->productReadModel = $productReadModel;
    }

    /**
     * {@inheritDoc}
     */
    public function processRow(DataRowInterface $dataCell, SymfonyStyle $io): void
    {
        $subscriptionOldId = (int) $dataCell[self::HEADER_COLUMNS['set_id']]->getValue();

        if (0 === $subscriptionOldId) {
            return;
        }

        $subscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'oldId' => $subscriptionOldId,
        ]);

        if (null === $subscription) {
            $io->writeln(sprintf('The subscription with old ID:%d was not found.', $subscriptionOldId));

            return;
        }

        $productId = (int) $dataCell[self::HEADER_COLUMNS['id_product']]->getValue();
        $productAttributeId = (int) $dataCell[self::HEADER_COLUMNS['id_product_attribute']]->getValue();

        if (!$this->productReadModel->isCombinationExist($productId, $productAttributeId)) {
            $this->deleteSubscription($subscription);
            $io->writeln(sprintf('The subscription with old ID:%d was deleted.', $subscriptionOldId));

            throw new DomainException(sprintf('The product with ID:%d and attribute ID:%d was not found.', $productId, $productAttributeId));
        }

        $cart = new Cart($subscription->getCartId());
        $context = Context::getContext();
        $context->cart = $cart;
        $context->currency = new Currency($cart->id_currency);

        $productDTO = new ProductDTO(
            $productId,
            $productAttributeId,
            (int) $dataCell[self::HEADER_COLUMNS['qty']]->getValue()
        );
        $this->cartCloneService->updateCartProducts($cart, $productDTO);

        if (1 === (int) $dataCell[self::HEADER_COLUMNS['only_next']]->getValue()) {
            $subscriptionProduct = $this->entityManager->getRepository(SubscriptionProduct::class)
                ->getOrCreate($subscription, $productId, $productAttributeId);
            $subscriptionProduct->setNextShipmentOnly();
        }

        if (1 === (int) $dataCell[self::HEADER_COLUMNS['skip_next']]->getValue()) {
            $subscriptionProduct = $this->entityManager->getRepository(SubscriptionProduct::class)
                ->getOrCreate($subscription, $productId, $productAttributeId);
            $subscriptionProduct->skipNextShipmentOnly();
        }

        $io->writeln(sprintf('The product with ID:%d and attribute ID:%d was added to subscription with old ID:%d.', $productId, $productAttributeId, $subscriptionOldId));
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderColumns(): array
    {
        return array_keys(self::HEADER_COLUMNS);
    }

    /**
     * {@inheritDoc}
     */
    public function postProcess(SymfonyStyle $io): void
    {
        $this->entityManager->flush();
    }

    /**
     * @param Subscription $subscription
     */
    private function deleteSubscription(Subscription $subscription): void
    {
        $cart = new Cart($subscription->getCartId());

        if (Validate::isLoadedObject($cart)) {
            $cart->delete();
        }

        $this->entityManager->remove($subscription);
        $this->entityManager->flush();
    }
}

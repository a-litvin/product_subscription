<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use BelVG\ProductSubscription\DTO\ProductDTO;
use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionCartProduct;
use BelVG\ProductSubscription\Entity\SubscriptionPayment;
use BelVG\ProductSubscription\Interfaces\VaultingHelperInterface;
use BelVG\ProductSubscription\ReadModel\CartProductReadModel;
use BelVG\ProductSubscription\ReadModel\SubscriptionCartProductReadModel;
use BelVG\ProductSubscription\Service\Cart\CartCloneService;
use Cart;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionService
{
    /**
     * @var int
     */
    private $cartId;

    /**
     * @var Cart
     */
    private $cartCopy;

    /**
     * @var int
     */
    private $customerId;

    /**
     * @var array
     */
    private $cartProducts = [];

    /**
     * @var array
     */
    private $subscriptionCartProducts = [];

    /**
     * @var SubscriptionCartProductReadModel
     */
    private $subscriptionCartProductReadModel;

    /**
     * @var CartProductReadModel
     */
    private $cartProductReadModel;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var VaultingHelperInterface
     */
    private $helper;

    /**
     * @var CartCloneService
     */
    private $cartCloneService;

    /**
     * @param SubscriptionCartProductReadModel $subscriptionCartProductReadModel
     * @param CartProductReadModel $cartProductReadModel
     * @param EntityManagerInterface $entityManager
     * @param CartCloneService $cartCloneService
     */
    public function __construct(
        SubscriptionCartProductReadModel $subscriptionCartProductReadModel,
        CartProductReadModel $cartProductReadModel,
        EntityManagerInterface $entityManager,
        CartCloneService $cartCloneService
    ) {
        $this->subscriptionCartProductReadModel = $subscriptionCartProductReadModel;
        $this->cartProductReadModel = $cartProductReadModel;
        $this->entityManager = $entityManager;
        $this->cartCloneService = $cartCloneService;
    }

    /**
     * @param Cart $cart
     * @param VaultingHelperInterface $helper
     */
    public function process(Cart $cart, VaultingHelperInterface $helper): void
    {
        $this->cartId = $cart->id;
        $this->customerId = $cart->id_customer;
        $this->helper = $helper;
        $this->run();
    }

    /**
     * Subscription creation
     */
    private function run(): void
    {
        $periodicityIds = $this->subscriptionCartProductReadModel->getDistinctActivePeriodicityIdsByCartId($this->cartId);

        if (empty($periodicityIds)) {
            return;
        }

        $this->initialize();
        
        foreach ($periodicityIds as $periodicityId) {
            $cart = $this->cartCloneService->getCartClone($this->cartId);
            $subscriptionCartProducts = $this->getSubscriptionProductsByPeriodicityId((int) $periodicityId);

            foreach ($subscriptionCartProducts as $subscriptionCartProduct) {
                $productId = $subscriptionCartProduct->getProductId();
                $productAttributeId = $subscriptionCartProduct->getProductAttributeId();

                $productDTO = new ProductDTO(
                    $productId,
                    $productAttributeId,
                    $this->cartProducts[$productId][$productAttributeId]
                );

                $this->cartCloneService->updateCartProducts($cart, $productDTO);
            }

            $periodicity = reset($subscriptionCartProducts)->getPeriodicity();

            $subscription = new Subscription(
                (int) $this->customerId,
                (int) $cart->id,
                $periodicity
            );

            $subscription->setNextDelivery(
                (new \DateTimeImmutable('today'))
                    ->modify(sprintf('+ %d days', $periodicity->getInterval()))
            );

            if (!isset($numberAutoship)) {
                $customerAutoshipsArr = $this->entityManager->getRepository(Subscription::class)->findAllWithAssociationsByCustomerId(intval($this->customerId));
                $names = [];
                foreach ($customerAutoshipsArr as $customerAutoship){
                    $names[$customerAutoship->getId()]=$customerAutoship->getName();
                }
                $numberAutoship = count($this->entityManager->getRepository(Subscription::class)->findAllWithAssociationsByCustomerId(intval($this->customerId))) + 1;
            } else {
                $numberAutoship++;
            }
            $name = "Autoship " . $numberAutoship;
            while(in_array($name, $names)){
                $numberAutoship++;
                $name = "Autoship " . $numberAutoship;
            }
            $subscription->setName($name);
            $this->entityManager->persist($subscription);

            $subscriptionPayment = new SubscriptionPayment(
                $subscription,
                $this->helper->getModuleName(),
                $this->helper->getVaultId()
            );
            $this->entityManager->persist($subscriptionPayment);
        }

        $this->entityManager->flush();
    }

    /**
     * Data initialization.
     */
    private function initialize()
    {
        $this->initializeCartProductData();
        $this->initializeSubscriptionProducts();
    }

    /**
     * Retrieving the cart products data.
     */
    private function initializeCartProductData()
    {
        $this->cartProducts = $this->cartProductReadModel->getCartProductsByCartId($this->cartId);
    }

    /**
     * Retrieving the subscription products.
     */
    private function initializeSubscriptionProducts(): void
    {
        $this->subscriptionCartProducts = $this->entityManager
            ->getRepository(SubscriptionCartProduct::class)
            ->findAllActiveByCartId($this->cartId);
    }

    /**
     * @param int $periodicityId
     *
     * @return array|SubscriptionCartProduct[]
     */
    private function getSubscriptionProductsByPeriodicityId(int $periodicityId): array
    {
        return array_filter($this->subscriptionCartProducts, function (SubscriptionCartProduct $item) use ($periodicityId) {
            return $item->getPeriodicity()->getId() === $periodicityId;
        });
    }
}

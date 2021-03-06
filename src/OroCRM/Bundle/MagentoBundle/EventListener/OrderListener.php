<?php

namespace OroCRM\Bundle\MagentoBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;

use OroCRM\Bundle\ChannelBundle\EventListener\ChannelDoctrineListener;
use OroCRM\Bundle\MagentoBundle\Entity\Customer;
use OroCRM\Bundle\MagentoBundle\Entity\Order;
use OroCRM\Bundle\MagentoBundle\Entity\Repository\CustomerRepository;

class OrderListener
{
    /** @var ChannelDoctrineListener */
    protected $channelDoctrineListener;

    /**
     * @param ChannelDoctrineListener $channelDoctrineListener
     */
    public function __construct(ChannelDoctrineListener $channelDoctrineListener)
    {
        $this->channelDoctrineListener = $channelDoctrineListener;
    }

    /** @var array */
    protected $ordersForUpdate = [];

    /**
     * @param LifecycleEventArgs $event
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        /** @var Order $entity */
        $entity = $event->getEntity();

        // if new order has valuable subtotal and status
        if (
            $this->isOrderValid($entity)
            && $entity->getSubtotalAmount()
            && $entity->getStatus() !== Order::STATUS_CANCELED
        ) {
            $this->updateCustomerLifetime($event->getEntityManager(), $entity);
        }
    }

    /**
     * @param PreUpdateEventArgs $event
     */
    public function preUpdate(PreUpdateEventArgs $event)
    {
        $entity = $event->getEntity();

        // if subtotal or status has been changed
        if ($this->isOrderValid($entity)
            && array_intersect(['subtotalAmount', 'status'], array_keys($event->getEntityChangeSet()))
        ) {
            $this->ordersForUpdate[$entity->getId()] = true;
        }
    }

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
    {
        if (count($this->ordersForUpdate) === 0) {
            return;
        }

        $orders = $this->getChangedOrders($event->getEntityManager()->getUnitOfWork());
        foreach ($orders as $order) {
            // if order was scheduled for update
            if (!empty($this->ordersForUpdate[$order->getId()])) {
                $this->updateCustomerLifetime($event->getEntityManager(), $order);
                unset($this->ordersForUpdate[$order->getId()]);
            }
        }
    }

    /**
     * @param UnitOfWork $uow
     * @return array|Order[]
     */
    protected function getChangedOrders(UnitOfWork $uow)
    {
        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityDeletions(),
            $uow->getScheduledEntityUpdates()
        );

        $collections = array_merge(
            $uow->getScheduledCollectionDeletions(),
            $uow->getScheduledCollectionUpdates()
        );

        /** @var PersistentCollection $collectionToChange */
        foreach ($collections as $collectionToChange) {
            $entities = array_merge($entities, $collectionToChange->unwrap()->toArray());
        }

        return array_filter(
            $entities,
            function ($entity) {
                return $this->isOrderValid($entity);
            }
        );
    }

    /**
     * @param Order|object $order
     *
     * @return bool
     */
    protected function isOrderValid($order)
    {
        return $order instanceof Order
            && $order->getCustomer() instanceof Customer;
    }

    /**
     * @param EntityManager $entityManager
     * @param Order $order
     */
    protected function updateCustomerLifetime(EntityManager $entityManager, Order $order)
    {
        /** @var CustomerRepository $customerRepository */
        $customerRepository = $entityManager->getRepository('OroCRMMagentoBundle:Customer');

        $subtotalAmount = $order->getSubtotalAmount();
        if ($subtotalAmount) {
            // if order status changed to canceled we should remove subtotalAmount from customer lifetime
            if ($order->getStatus() === Order::STATUS_CANCELED) {
                $subtotalAmount *= -1;
            }

            $customer = $order->getCustomer();
            $customerRepository->updateCustomerLifetimeValue($customer, $subtotalAmount);

            // schedule lifetime history update
            if ($customer->getAccount()) {
                $this->channelDoctrineListener->scheduleEntityUpdate(
                    $customer,
                    $customer->getAccount(),
                    $customer->getDataChannel()
                );
            }
        }
    }
}

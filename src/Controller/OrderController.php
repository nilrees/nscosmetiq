<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    // CUSTOMER ORDER HISTORY
    #[Route('/my-orders', name: 'customer_orders', methods: ['GET'])]
    public function myOrders(OrderRepository $orders): Response
    {
        return $this->render('order/my_orders.html.twig', [
            'orders' => $orders->findBy(
                ['customer' => $this->getUser()],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    // CUSTOMER ORDER DETAILS
    #[Route('/my-orders/{id}', name: 'customer_order_show', methods: ['GET'])]
    public function show(
        Order $order,
        OrderItemRepository $items
    ): Response {
        if ($order->getCustomer()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
            'items' => $items->findBy(['customerOrder' => $order]),
        ]);
    }

    // ADMIN AND STAFF ORDER LIST - SEARCH, FILTER AND STATISTICS
    #[Route('/staff/orders', name: 'staff_orders', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function staffOrders(
        Request $request,
        OrderRepository $orders,
        OrderItemRepository $items
    ): Response {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $search = trim((string) $request->query->get('search', ''));
        $status = (string) $request->query->get('status', '');
        $allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $visibleOrders = [];
        foreach ($orders->findBy([], ['createdAt' => 'DESC']) as $order) {
            $orderItems = $items->findBy(['customerOrder' => $order]);
            if (!$orderItems) {
                continue;
            }

            if (!$isAdmin) {
                $canManage = true;
                foreach ($orderItems as $item) {
                    if ($item->getProduct()?->getCreatedBy()?->getId() !== $user?->getId()) {
                        $canManage = false;
                        break;
                    }
                }
                if (!$canManage) {
                    continue;
                }
            }
            $visibleOrders[] = $order;
        }

        // Counts reflect authorized orders before filtering.
        $stats = [
            'total' => count($visibleOrders),
            'pending' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];
        foreach ($visibleOrders as $order) {
            $key = strtolower($order->getStatus());
            if (array_key_exists($key, $stats)) {
                ++$stats[$key];
            }
        }

        $filteredOrders = [];
        foreach ($visibleOrders as $order) {
            if ($status !== '' && $order->getStatus() !== $status) {
                continue;
            }
            if ($search !== '') {
                $orderNumber = (string) $order->getId();
                $customerEmail = $order->getCustomer()?->getEmail() ?? '';
                if (stripos($orderNumber, $search) === false
                    && stripos($customerEmail, $search) === false) {
                    continue;
                }
            }
            $filteredOrders[] = $order;
        }

        return $this->render('order/staff_orders.html.twig', [
            'orders' => $filteredOrders,
            'search' => $search,
            'selectedStatus' => $status,
            'stats' => $stats,
        ]);
    }

    // ADMIN AND STAFF ORDER DETAILS
    #[Route('/staff/orders/{id}', name: 'staff_order_show', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function staffOrderShow(
        Order $order,
        OrderItemRepository $items
    ): Response {
        $orderItems = $items->findBy(['customerOrder' => $order]);

        if (!$orderItems) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            foreach ($orderItems as $item) {
                if ($item->getProduct()?->getCreatedBy()?->getId()
                    !== $this->getUser()?->getId()) {
                    throw $this->createAccessDeniedException();
                }
            }
        }

        return $this->render('order/staff_show.html.twig', [
            'order' => $order,
            'items' => $orderItems,
        ]);
    }

    // ADMIN AND STAFF STATUS UPDATES
    #[Route('/staff/orders/{id}/status', name: 'staff_order_status', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function updateStatus(
        Order $order,
        Request $request,
        OrderItemRepository $items,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid(
            'order_status_' . $order->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $orderId = $order->getId();
        $nextStatus = (string) $request->request->get('status', '');

        $allowedTransitions = [
            'Pending' => ['Processing'],
            'Processing' => ['Shipped'],
            'Shipped' => ['Delivered'],
            'Delivered' => [],
            'Cancelled' => [],
        ];

        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            // Lock the order so cancellation and status updates
            // cannot modify the same order simultaneously.
            $lockedOrder = $em->find(
                Order::class,
                $orderId,
                LockMode::PESSIMISTIC_WRITE
            );

            if (!$lockedOrder) {
                throw $this->createNotFoundException();
            }

            $orderItems = $items->findBy([
                'customerOrder' => $lockedOrder,
            ]);

            if (!$orderItems) {
                throw $this->createAccessDeniedException();
            }

            if (!$this->isGranted('ROLE_ADMIN')) {
                foreach ($orderItems as $item) {
                    if ($item->getProduct()?->getCreatedBy()?->getId()
                        !== $this->getUser()?->getId()) {
                        throw $this->createAccessDeniedException();
                    }
                }
            }

            $currentStatus = $lockedOrder->getStatus();

            if (!in_array(
                $nextStatus,
                $allowedTransitions[$currentStatus] ?? [],
                true
            )) {
                $connection->rollBack();

                $this->addFlash(
                    'error',
                    'This order cannot be changed to that status.'
                );

                return $this->redirectToRoute('staff_orders');
            }

            $lockedOrder->setStatus($nextStatus);
            $em->flush();
            $connection->commit();

            $this->addFlash('success', 'Order status updated.');

            return $this->redirectToRoute('staff_orders');
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        }
    }

    // CUSTOMER AND ADMIN - CANCEL PENDING ORDER
    #[Route('/orders/{id}/cancel', name: 'order_cancel', methods: ['POST'])]
    public function cancelOrder(
        Order $order,
        Request $request,
        OrderItemRepository $items,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Staff are explicitly forbidden from cancelling.
        // Admin inherits ROLE_STAFF, so check admin first.
        if (!$isAdmin && $this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException(
                'Staff cannot cancel orders.'
            );
        }

        // Customers can cancel only their own orders.
        if (!$isAdmin &&
            $order->getCustomer()?->getId() !== $user?->getId()) {
            throw $this->createAccessDeniedException(
                'You cannot cancel this order.'
            );
        }

        if (!$this->isCsrfTokenValid(
            'cancel_order_' . $order->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid cancellation token.'
            );
        }

        $orderId = $order->getId();

        $redirectRoute = $isAdmin
            ? 'staff_orders'
            : 'customer_orders';

        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            // Lock the order row before checking its status.
            $lockedOrder = $em->find(
                Order::class,
                $orderId,
                LockMode::PESSIMISTIC_WRITE
            );

            if (!$lockedOrder) {
                throw $this->createNotFoundException();
            }

            // Recheck permission inside the transaction.
            if (!$isAdmin &&
                $lockedOrder->getCustomer()?->getId() !== $user?->getId()) {
                throw $this->createAccessDeniedException();
            }

            // Only Pending orders may be cancelled.
            if ($lockedOrder->getStatus() !== 'Pending') {
                $connection->rollBack();

                $this->addFlash(
                    'error',
                    'Only Pending orders can be cancelled.'
                );

                return $this->redirectToRoute($redirectRoute);
            }

            $orderItems = $items->findBy([
                'customerOrder' => $lockedOrder,
            ]);

            if (!$orderItems) {
                throw new \RuntimeException(
                    'This order has no items. Cancellation was not completed.'
                );
            }

            // Lock products in consistent ID order.
            // Aggregate quantities in case a product appears
            // more than once in an order.
            $quantities = [];

            foreach ($orderItems as $item) {
                $productId = $item->getProduct()?->getId();
                $quantity = $item->getQuantity();

                if ($productId === null || $quantity < 1) {
                    throw new \RuntimeException(
                        'Order item data is invalid.'
                    );
                }

                $quantities[$productId] =
                    ($quantities[$productId] ?? 0) + $quantity;
            }

            ksort($quantities, SORT_NUMERIC);

            foreach ($quantities as $productId => $quantity) {
                $product = $em->find(
                    Product::class,
                    $productId,
                    LockMode::PESSIMISTIC_WRITE
                );

                if (!$product) {
                    throw new \RuntimeException(
                        'A purchased product no longer exists.'
                    );
                }

                // Restore the purchased quantity.
                $product->setStock(
                    $product->getStock() + $quantity
                );
            }

            // Mark cancelled in the same transaction.
            $lockedOrder->setStatus('Cancelled');

            $em->flush();
            $connection->commit();

            $this->addFlash(
                'success',
                'Order #' . $orderId .
                ' was cancelled and its stock was restored.'
            );

            return $this->redirectToRoute($redirectRoute);
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        }
    }
}

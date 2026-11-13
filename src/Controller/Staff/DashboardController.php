<?php

namespace App\Controller\Staff;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class DashboardController extends AbstractController
{
    #[Route('/staff/dashboard', name: 'staff_dashboard', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository
    ): Response {
        /** @var User $staff */
        $staff = $this->getUser();

        // ==========================================
        // STAFF PRODUCT STATISTICS
        // ==========================================

        // Staff can only see products they created.
        $products = $productRepository->findBy(
            ['createdBy' => $staff],
            ['createdAt' => 'DESC']
        );

        $totalProducts = count($products);
        $totalStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        $lowStockProducts = [];
        $outOfStockProducts = [];

        foreach ($products as $product) {
            $stock = $product->getStock() ?? 0;

            $totalStock += $stock;

            if ($stock === 0) {
                $outOfStock++;
                $outOfStockProducts[] = $product;
            } elseif ($stock <= 5) {
                $lowStock++;
                $lowStockProducts[] = $product;
            }
        }

        // Show products with the lowest stock first.
        usort(
            $lowStockProducts,
            fn ($a, $b) =>
                ($a->getStock() ?? 0) <=> ($b->getStock() ?? 0)
        );

        $recentProducts = array_slice($products, 0, 5);

        // ==========================================
        // STAFF ORDER STATISTICS
        // ==========================================

        $totalOrders = 0;
        $pendingOrders = 0;
        $processingOrders = 0;
        $shippedOrders = 0;
        $deliveredOrders = 0;
        $cancelledOrders = 0;

        $recentOrders = [];

        foreach ($orderRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        ) as $order) {
            $items = $orderItemRepository->findBy([
                'customerOrder' => $order,
            ]);

            if (!$items) {
                continue;
            }

            $canManage = true;

            // Staff can only see orders containing
            // products that belong to them.
            foreach ($items as $item) {
                $owner = $item->getProduct()?->getCreatedBy();

                if ($owner?->getId() !== $staff->getId()) {
                    $canManage = false;
                    break;
                }
            }

            if (!$canManage) {
                continue;
            }

            $totalOrders++;

            switch ($order->getStatus()) {
                case 'Pending':
                    $pendingOrders++;
                    break;

                case 'Processing':
                    $processingOrders++;
                    break;

                case 'Shipped':
                    $shippedOrders++;
                    break;

                case 'Delivered':
                    $deliveredOrders++;
                    break;

                case 'Cancelled':
                    $cancelledOrders++;
                    break;
            }

            if (count($recentOrders) < 5) {
                $recentOrders[] = $order;
            }
        }

        // ==========================================
        // RENDER STAFF DASHBOARD
        // ==========================================

        return $this->render('staff/dashboard.html.twig', [
            // Product statistics
            'totalProducts' => $totalProducts,
            'totalRecords' => $totalProducts,
            'totalStock' => $totalStock,
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'lowStockProducts' => $lowStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'recentProducts' => $recentProducts,

            // Order statistics
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'shippedOrders' => $shippedOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'recentOrders' => $recentOrders,
        ]);
    }
}

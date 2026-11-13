<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository
    ): Response {
        // ==========================================
        // USER STATISTICS
        // ==========================================

        $users = $userRepository->findAll();

        $totalUsers = count($users);
        $totalStaff = 0;
        $totalAdmins = 0;
        $activeUsers = 0;

        foreach ($users as $user) {
            if ($user->isActive()) {
                $activeUsers++;
            }

            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $totalAdmins++;
            } elseif (in_array('ROLE_STAFF', $user->getRoles(), true)) {
                $totalStaff++;
            }
        }

        // ==========================================
        // PRODUCT AND INVENTORY STATISTICS
        // ==========================================

        $products = $productRepository->findAll();

        $totalRecords = count($products);
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
            } elseif ($stock > 0 && $stock <= 5) {
                $lowStock++;
                $lowStockProducts[] = $product;
            }
        }

        usort(
            $lowStockProducts,
            fn ($a, $b) =>
                ($a->getStock() ?? 0) <=> ($b->getStock() ?? 0)
        );

        $recentProducts = $productRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );

        // ==========================================
        // ORDER STATISTICS
        // ==========================================

        $totalOrders = $orderRepository->count([]);
        $pendingOrders = $orderRepository->count([
            'status' => 'Pending',
        ]);
        $processingOrders = $orderRepository->count([
            'status' => 'Processing',
        ]);
        $shippedOrders = $orderRepository->count([
            'status' => 'Shipped',
        ]);
        $deliveredOrders = $orderRepository->count([
            'status' => 'Delivered',
        ]);
        $cancelledOrders = $orderRepository->count([
            'status' => 'Cancelled',
        ]);

        $recentOrders = $orderRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );

        // ==========================================
        // SALES AND REVENUE STATISTICS
        // ==========================================

        // Count only delivered orders as completed sales.
        $completedOrders = $orderRepository->findBy([
            'status' => 'Delivered',
        ]);

        $totalRevenue = 0.0;
        $totalProductsSold = 0;
        $bestSellingProducts = [];
        $monthlySales = [];

        foreach ($completedOrders as $order) {
            $totalRevenue += (float) $order->getTotalAmount();

            $month = $order->getCreatedAt()->format('Y-m');

            if (!isset($monthlySales[$month])) {
                $monthlySales[$month] = 0.0;
            }

            $monthlySales[$month] +=
                (float) $order->getTotalAmount();

            $items = $orderItemRepository->findBy([
                'customerOrder' => $order,
            ]);

            foreach ($items as $item) {
                $quantity = $item->getQuantity() ?? 0;

                $totalProductsSold += $quantity;

                $product = $item->getProduct();

                if (!$product) {
                    continue;
                }

                $productId = $product->getId();

                if (!isset($bestSellingProducts[$productId])) {
                    $bestSellingProducts[$productId] = [
                        'product' => $product,
                        'quantity' => 0,
                        'revenue' => 0.0,
                    ];
                }

                $bestSellingProducts[$productId]['quantity']
                    += $quantity;

                $bestSellingProducts[$productId]['revenue']
                    += $quantity * (float) $item->getUnitPrice();
            }
        }

        // Sort products by number of units sold.
        uasort(
            $bestSellingProducts,
            fn ($a, $b) =>
                $b['quantity'] <=> $a['quantity']
        );

        $bestSellingProducts = array_slice(
            array_values($bestSellingProducts),
            0,
            5
        );

        // Sort monthly revenue chronologically.
        ksort($monthlySales);

        $completedOrderCount = count($completedOrders);

        $averageOrderValue = $completedOrderCount > 0
            ? $totalRevenue / $completedOrderCount
            : 0.0;

        // ==========================================
        // RENDER ADMIN DASHBOARD
        // ==========================================

        return $this->render('admin/dashboard.html.twig', [
            // User statistics
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalAdmins' => $totalAdmins,
            'activeUsers' => $activeUsers,

            // Product statistics
            'totalRecords' => $totalRecords,
            'totalProducts' => $totalRecords,
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

            // Sales statistics
            'totalRevenue' => $totalRevenue,
            'completedOrderCount' => $completedOrderCount,
            'totalProductsSold' => $totalProductsSold,
            'averageOrderValue' => $averageOrderValue,
            'bestSellingProducts' => $bestSellingProducts,
            'monthlySales' => $monthlySales,
        ]);
    }
}

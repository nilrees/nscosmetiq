<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
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
        OrderRepository $orderRepository
    ): Response {
        // USER STATISTICS
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

        // PRODUCT STATISTICS
        $products = $productRepository->findAll();

        $totalRecords = count($products);
        $totalStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($products as $product) {
            $stock = $product->getStock() ?? 0;

            $totalStock += $stock;

            if ($stock === 0) {
                $outOfStock++;
            } elseif ($stock <= 5) {
                $lowStock++;
            }
        }

        $recentProducts = $productRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );

        // ORDER STATISTICS - ALL CUSTOMER ORDERS
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

        $recentOrders = $orderRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalAdmins' => $totalAdmins,
            'activeUsers' => $activeUsers,
            'totalRecords' => $totalRecords,
            'totalProducts' => $totalRecords,
            'totalStock' => $totalStock,
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'recentProducts' => $recentProducts,

            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'shippedOrders' => $shippedOrders,
            'deliveredOrders' => $deliveredOrders,
            'recentOrders' => $recentOrders,
        ]);
    }
}

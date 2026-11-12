<?php

namespace App\Controller\Staff;

use App\Entity\User;
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
        ProductRepository $productRepository
    ): Response {
        /** @var User $staff */
        $staff = $this->getUser();

        // Retrieve products created by the logged-in user.
        $products = $productRepository->findBy(
            ['createdBy' => $staff],
            ['createdAt' => 'DESC']
        );

        $totalProducts = count($products);
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

        // Display the five newest products.
        $recentProducts = array_slice($products, 0, 5);

        return $this->render('staff/dashboard.html.twig', [
            'totalProducts' => $totalProducts,
            'totalRecords' => $totalProducts,
            'totalStock' => $totalStock,
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'recentProducts' => $recentProducts,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository
    ): Response {
        // Get all products from the database
        $products = $productRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        // Get the 6 newest products for the homepage
        $featuredProducts = array_slice($products, 0, 6);

        // Get the 4 latest products
        $newArrivals = array_slice($products, 0, 4);

        // Get available product categories
        $categories = [];

        foreach ($products as $product) {
            $category = $product->getCategory();

            if ($category && !in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        return $this->render('home/index.html.twig', [
            'products' => $products,
            'featuredProducts' => $featuredProducts,
            'newArrivals' => $newArrivals,
            'categories' => $categories,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'shop_index', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        Request $request
    ): Response {
        $search = trim($request->query->getString('search'));
        $category = trim($request->query->getString('category'));

        $qb = $productRepository->createQueryBuilder('p')
            ->where('p.stock > 0')
            ->orderBy('p.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search')
               ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($category !== '') {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }

        return $this->render('shop/index.html.twig', [
            'products' => $qb->getQuery()->getResult(),
            'search' => $search,
            'category' => $category,
        ]);
    }
}

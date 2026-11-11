<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findForListing(
        ?string $search = null,
        ?string $category = null
    ): array {
        $qb = $this->createQueryBuilder('p');

        // Show all products to Admin and Staff
        // No filtering by product owner

        // Search products by name or description
        if ($search !== null && trim($search) !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(p.name) LIKE :search',
                    'LOWER(p.description) LIKE :search'
                )
            )
            ->setParameter(
                'search',
                '%' . strtolower(trim($search)) . '%'
            );
        }

        // Filter products by category
        if ($category !== null && trim($category) !== '') {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', trim($category));
        }

        // Display newest products first
        return $qb
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

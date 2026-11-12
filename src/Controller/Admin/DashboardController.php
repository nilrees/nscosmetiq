<?php

namespace App\Controller\Admin;

use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        ProductRepository $productRepository
    ): Response {
        $users = $userRepository->findAll();
        $totalStaff = 0;

        foreach ($users as $user) {
            if (in_array('ROLE_STAFF', $user->getRoles(), true)
                && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $totalStaff++;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => count($users),
            'totalStaff' => $totalStaff,
            'totalRecords' => $productRepository->count([]),
            'recentProducts' => $productRepository->findBy(
                [],
                ['id' => 'DESC'],
                5
            ),
        ]);
    }
}

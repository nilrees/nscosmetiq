<?php

namespace App\Controller\Admin;

use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        ProductRepository $productRepository
    ) {
        $users = $userRepository->findAll();

        $totalStaff = 0;

        foreach ($users as $user) {
            if (!$user->isAdmin()) {
                $totalStaff++;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => count($users),
            'totalStaff' => $totalStaff,
            'totalRecords' => $productRepository->count([]),
        ]);
    }
}
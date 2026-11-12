<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $repo): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();

        $form = $this->createForm(UserType::class, $user, [
            'password_required' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $hasher->hashPassword($user, $plainPassword)
            );

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $isSelf = $user->getId() === $this->getUser()->getId();

        $form = $this->createForm(UserType::class, $user, [
            'is_self' => $isSelf,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
            'isSelf' => $isSelf,
        ]);
    }

    #[Route('/{id}/role', name: 'admin_user_role', methods: ['POST'])]
    public function updateRole(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        // Verify CSRF token.
        if (!$this->isCsrfTokenValid(
            'change-role-' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('admin_user_index');
        }

        // Only these roles can be assigned.
        $role = (string) $request->request->get('role');

        if (!in_array($role, ['ROLE_ADMIN', 'ROLE_STAFF'], true)) {
            $this->addFlash('danger', 'Invalid role selected.');

            return $this->redirectToRoute('admin_user_index');
        }

        // Prevent the logged-in Admin from changing their own role.
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash(
                'danger',
                'You cannot change your own role.'
            );

            return $this->redirectToRoute('admin_user_index');
        }

        // Protect the last Admin account.
        $isCurrentlyAdmin = in_array(
            'ROLE_ADMIN',
            $user->getRoles(),
            true
        );

        if ($isCurrentlyAdmin && $role !== 'ROLE_ADMIN') {
            $adminCount = 0;

            foreach ($userRepository->findAll() as $account) {
                if (in_array('ROLE_ADMIN', $account->getRoles(), true)) {
                    $adminCount++;
                }
            }

            if ($adminCount <= 1) {
                $this->addFlash(
                    'danger',
                    'You cannot remove the last Admin account.'
                );

                return $this->redirectToRoute('admin_user_index');
            }
        }

        // Save the new role.
        $user->setRoles([$role]);
        $em->flush();

        $this->addFlash(
            'success',
            'Role updated successfully for ' . $user->getEmail() . '.'
        );

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route(
        '/{id}/reset-password',
        name: 'admin_user_reset_password',
        methods: ['GET', 'POST']
    )]
    public function resetPassword(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $form = $this->createFormBuilder()
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'New password',
                ],
                'second_options' => [
                    'label' => 'Confirm new password',
                ],
                'invalid_message' => 'The passwords do not match.',
                'constraints' => [
                    new NotBlank(
                        message: 'Password is required.'
                    ),
                    new Length(
                        min: 8,
                        minMessage: 'Password must be at least {{ limit }} characters.'
                    ),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $hasher->hashPassword($user, $plainPassword)
            );

            $em->flush();

            $this->addFlash(
                'success',
                'Password reset for ' . $user->getEmail() . '.'
            );

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/reset_password.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_user_toggle', methods: ['POST'])]
    public function toggle(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        if (!$this->isCsrfTokenValid(
            'toggle' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('admin_user_index');
        }

        // Prevent disabling your own account.
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash(
                'danger',
                'You cannot disable your own account.'
            );

            return $this->redirectToRoute('admin_user_index');
        }

        // Prevent disabling the last active Admin.
        if ($user->isActive() && $this->isLastActiveAdmin($user, $userRepository)) {
            $this->addFlash(
                'danger',
                'You cannot disable the last active Admin account.'
            );

            return $this->redirectToRoute('admin_user_index');
        }

        $user->setIsActive(!$user->isActive());
        $em->flush();

        $this->addFlash(
            'success',
            $user->isActive() ? 'Account enabled.' : 'Account disabled.'
        );

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('admin_user_index');
        }

        // Prevent deleting your own account.
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash(
                'danger',
                'You cannot delete your own account.'
            );

            return $this->redirectToRoute('admin_user_index');
        }

        // Prevent deleting the last active Admin.
        if ($this->isLastActiveAdmin($user, $userRepository)) {
            $this->addFlash(
                'danger',
                'You cannot delete the last active Admin account.'
            );

            return $this->redirectToRoute('admin_user_index');
        }

        try {
            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'User deleted successfully.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash(
                'danger',
                'This user owns products and cannot be deleted. Disable the account instead.'
            );
        }

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Check whether a user is the last active Admin.
     */
    private function isLastActiveAdmin(
        User $user,
        UserRepository $userRepository
    ): bool {
        if (
            !$user->isActive() ||
            !in_array('ROLE_ADMIN', $user->getRoles(), true)
        ) {
            return false;
        }

        $activeAdminCount = 0;

        foreach ($userRepository->findAll() as $account) {
            if (
                $account->isActive() &&
                in_array('ROLE_ADMIN', $account->getRoles(), true)
            ) {
                $activeAdminCount++;
            }
        }

        return $activeAdminCount <= 1;
    }
}

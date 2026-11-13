<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/products')]
#[IsGranted('ROLE_STAFF')]
class ProductController extends AbstractController
{
    // ==========================================
    // PRODUCT IMAGE UPLOAD
    // ==========================================

    private function uploadProductImage(
        UploadedFile $image
    ): string {
        $uploadDirectory = $this->getParameter('kernel.project_dir')
            . '/public/uploads/products';

        if (!is_dir($uploadDirectory)) {
            if (
                !mkdir($uploadDirectory, 0775, true)
                && !is_dir($uploadDirectory)
            ) {
                throw new \RuntimeException(
                    'Unable to create the product image directory.'
                );
            }
        }

        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \RuntimeException(
                'Unsupported product image format.'
            ),
        };

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        $image->move($uploadDirectory, $filename);

        return $filename;
    }

    // ==========================================
    // DELETE PRODUCT IMAGE
    // ==========================================

    private function deleteProductImage(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $uploadDirectory = $this->getParameter('kernel.project_dir')
            . '/public/uploads/products';

        $safeFilename = basename($filename);
        $path = $uploadDirectory . '/' . $safeFilename;

        if (is_file($path)) {
            unlink($path);
        }
    }

    // ==========================================
    // PRODUCT LIST
    // ADMIN: ALL PRODUCTS
    // STAFF: OWN PRODUCTS ONLY
    // ==========================================

    #[Route('', name: 'product_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository
    ): Response {
        $search = trim(
            (string) $request->query->get('search', '')
        );

        $category = trim(
            (string) $request->query->get('category', '')
        );

        // Keep existing search and category filter.
        $products = $productRepository->findForListing(
            $search,
            $category
        );

        // Staff can only see their own products.
        if (!$this->isGranted('ROLE_ADMIN')) {
            $staffId = $this->getUser()->getId();

            $products = array_values(
                array_filter(
                    $products,
                    fn (Product $product): bool =>
                        $product->getCreatedBy()?->getId() === $staffId
                )
            );
        }

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'category' => $category,
        ]);
    }

    // ==========================================
    // CREATE PRODUCT
    // ==========================================

    #[Route('/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $product = new Product();

        $form = $this->createForm(
            ProductType::class,
            $product
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('imageFile')->getData();
            $uploadedFilename = null;

            if ($image instanceof UploadedFile) {
                $uploadedFilename = $this->uploadProductImage($image);

                $product->setImageFilename(
                    $uploadedFilename
                );
            }

            // Automatically assign product ownership.
            $product->setCreatedBy($this->getUser());

            try {
                $entityManager->persist($product);
                $entityManager->flush();
            } catch (\Throwable $exception) {
                $this->deleteProductImage($uploadedFilename);
                throw $exception;
            }

            $this->addFlash(
                'success',
                'Product created successfully.'
            );

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    // ==========================================
    // EDIT PRODUCT
    // ADMIN: ANY PRODUCT
    // STAFF: OWN PRODUCT ONLY
    // ==========================================

    #[Route('/{id}/edit', name: 'product_edit', methods: ['GET', 'POST'])]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Existing ProductVoter checks ownership.
        $this->denyAccessUnlessGranted('edit', $product);

        $form = $this->createForm(
            ProductType::class,
            $product
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('imageFile')->getData();

            $oldFilename = $product->getImageFilename();
            $newFilename = null;

            if ($image instanceof UploadedFile) {
                $newFilename = $this->uploadProductImage($image);

                $product->setImageFilename($newFilename);
            }

            try {
                $entityManager->flush();
            } catch (\Throwable $exception) {
                $this->deleteProductImage($newFilename);
                throw $exception;
            }

            // Delete old image after successful update.
            if ($newFilename !== null) {
                $this->deleteProductImage($oldFilename);
            }

            $this->addFlash(
                'success',
                'Product updated successfully.'
            );

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    // ==========================================
    // DELETE PRODUCT
    // ADMIN: ANY PRODUCT
    // STAFF: OWN PRODUCT ONLY
    // ==========================================

    #[Route('/{id}/delete', name: 'product_delete', methods: ['POST'])]
    public function delete(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('delete', $product);

        if (!$this->isCsrfTokenValid(
            'delete-product-' . $product->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash(
                'danger',
                'Invalid security token. Product was not deleted.'
            );

            return $this->redirectToRoute('product_index');
        }

        $filename = $product->getImageFilename();

        $entityManager->remove($product);
        $entityManager->flush();

        // Delete image only after successful database deletion.
        $this->deleteProductImage($filename);

        $this->addFlash(
            'success',
            'Product deleted successfully.'
        );

        return $this->redirectToRoute('product_index');
    }

    // ==========================================
    // RESTOCK PRODUCT
    // ADMIN: ANY PRODUCT
    // STAFF: OWN PRODUCT ONLY
    // ==========================================

    #[Route('/{id}/restock', name: 'product_restock', methods: ['POST'])]
    public function restock(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Check product ownership and permissions.
        $this->denyAccessUnlessGranted('edit', $product);

        // Validate CSRF token.
        if (!$this->isCsrfTokenValid(
            'restock-product-' . $product->getId(),
            (string) $request->request->get('_token', '')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid restock security token.'
            );
        }

        // Validate restock quantity.
        $rawQuantity = $request->request->get('quantity');

        $quantity = filter_var(
            $rawQuantity,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($quantity === false || $quantity === null) {
            $this->addFlash(
                'error',
                'Please enter a valid quantity greater than zero.'
            );

            return $this->redirectToRoute('product_index');
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // Lock product row to prevent simultaneous updates.
            $lockedProduct = $entityManager->find(
                Product::class,
                $product->getId(),
                LockMode::PESSIMISTIC_WRITE
            );

            if (!$lockedProduct) {
                throw $this->createNotFoundException(
                    'Product not found.'
                );
            }

            // Reload stock after acquiring the database lock.
            $entityManager->refresh($lockedProduct);

            // Recheck permissions.
            $this->denyAccessUnlessGranted(
                'edit',
                $lockedProduct
            );

            $currentStock = $lockedProduct->getStock() ?? 0;

            // Prevent invalid stock values.
            if ($currentStock < 0) {
                throw new \RuntimeException(
                    'Current product stock is invalid.'
                );
            }

            if ($quantity > PHP_INT_MAX - $currentStock) {
                throw new \InvalidArgumentException(
                    'Restock quantity is too large.'
                );
            }

            // Add quantity to current stock.
            $newStock = $currentStock + $quantity;

            $lockedProduct->setStock($newStock);

            $entityManager->flush();
            $connection->commit();

            $this->addFlash(
                'success',
                $lockedProduct->getName()
                . ' restocked successfully. Added '
                . $quantity
                . ' unit(s). New stock: '
                . $newStock . '.'
            );

        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        return $this->redirectToRoute('product_index');
    }
}

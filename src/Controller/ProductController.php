<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
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
    private function uploadProductImage(
        UploadedFile $image
    ): string {
        $uploadDirectory = $this->getParameter('kernel.project_dir')
            . '/public/uploads/products';

        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0775, true)
                && !is_dir($uploadDirectory)) {
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

    #[Route('', name: 'product_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $category = trim((string) $request->query->get('category', ''));

        $products = $productRepository->findForListing(
            $search,
            $category
        );

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'category' => $category,
        ]);
    }

    #[Route('/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $product = new Product();

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('imageFile')->getData();
            $uploadedFilename = null;

            if ($image instanceof UploadedFile) {
                $uploadedFilename = $this->uploadProductImage($image);
                $product->setImageFilename($uploadedFilename);
            }

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

    #[Route('/{id}/edit', name: 'product_edit', methods: ['GET', 'POST'])]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('edit', $product);

        $form = $this->createForm(ProductType::class, $product);
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

    #[Route('/{id}/delete', name: 'product_delete', methods: ['POST'])]
    public function delete(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('delete', $product);

        if ($this->isCsrfTokenValid(
            'delete-product-' . $product->getId(),
            $request->request->get('_token')
        )) {
            $filename = $product->getImageFilename();

            $entityManager->remove($product);
            $entityManager->flush();

            $this->deleteProductImage($filename);

            $this->addFlash(
                'success',
                'Product deleted successfully.'
            );
        }

        return $this->redirectToRoute('product_index');
    }
}

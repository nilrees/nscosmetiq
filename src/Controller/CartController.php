<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart', name: 'cart_')]
class CartController extends AbstractController
{
    // DISPLAY SHOPPING CART
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $products
    ): Response {
        $cart = $request->getSession()->get('cart', []);
        $items = [];
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $products->find($productId);

            if (!$product) {
                continue;
            }

            $quantity = (int) $quantity;

            if ($quantity < 1) {
                continue;
            }

            $subtotal = (float) $product->getPrice() * $quantity;

            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];

            $total += $subtotal;
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    // ADD PRODUCT TO CART
    #[Route('/add/{id}', name: 'add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        ProductRepository $products
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(
            'cart_add_' . $id,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $product = $products->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $currentQuantity = (int) ($cart[$id] ?? 0);
        $stock = (int) $product->getStock();

        if ($stock < 1 || $currentQuantity >= $stock) {
            $this->addFlash('error', 'Not enough stock available.');

            return $this->redirectToRoute('cart_index');
        }

        $cart[$id] = $currentQuantity + 1;
        $session->set('cart', $cart);

        $this->addFlash('success', 'Product added to cart!');

        return $this->redirectToRoute('cart_index');
    }

    // UPDATE PRODUCT QUANTITY
    #[Route('/update/{id}', name: 'update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        ProductRepository $products
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(
            'cart_update_' . $id,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $product = $products->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        if (!isset($cart[$id])) {
            $this->addFlash('error', 'Product is not in your cart.');

            return $this->redirectToRoute('cart_index');
        }

        $rawQuantity = $request->request->get('quantity');

        if (!is_scalar($rawQuantity) ||
            !preg_match('/^[1-9][0-9]*$/', (string) $rawQuantity)) {
            $this->addFlash('error', 'Please enter a valid quantity.');

            return $this->redirectToRoute('cart_index');
        }

        $quantity = filter_var(
            (string) $rawQuantity,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($quantity === false) {
            $this->addFlash('error', 'Invalid quantity.');

            return $this->redirectToRoute('cart_index');
        }

        if ($quantity > (int) $product->getStock()) {
            $this->addFlash('error', 'Not enough stock available.');

            return $this->redirectToRoute('cart_index');
        }

        $cart[$id] = $quantity;
        $session->set('cart', $cart);

        $this->addFlash('success', 'Cart updated successfully.');

        return $this->redirectToRoute('cart_index');
    }

    // REMOVE PRODUCT FROM CART
    #[Route('/remove/{id}', name: 'remove', methods: ['POST'])]
    public function remove(
        int $id,
        Request $request
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(
            'cart_remove_' . $id,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        unset($cart[$id]);

        $session->set('cart', $cart);

        $this->addFlash('success', 'Product removed from cart.');

        return $this->redirectToRoute('cart_index');
    }
}

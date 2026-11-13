<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'checkout_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $products
    ): Response {
        $cart = $request->getSession()->get('cart', []);
        $items = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $product = $products->find($id);

            if (!$product || (int) $quantity < 1) {
                continue;
            }

            $subtotal = (float) $product->getPrice() * (int) $quantity;

            $items[] = [
                'product' => $product,
                'quantity' => (int) $quantity,
                'subtotal' => $subtotal,
            ];

            $total += $subtotal;
        }

        if (!$items) {
            $this->addFlash('error', 'Your cart is empty.');
            return $this->redirectToRoute('cart_index');
        }

        return $this->render('checkout/index.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/checkout/place-order', name: 'checkout_place_order', methods: ['POST'])]
    public function placeOrder(
        Request $request,
        ProductRepository $products,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid(
            'place_order',
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $customer = $this->getUser();

        if (!$customer instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $address = trim((string) $request->request->get('shipping_address', ''));
        $contact = trim((string) $request->request->get('contact_number', ''));

        if ($address === '' || mb_strlen($address) > 1000 ||
            $contact === '' || mb_strlen($contact) > 30) {
            $this->addFlash('error', 'Please provide valid shipping details.');
            return $this->redirectToRoute('checkout_index');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        if (!$cart) {
            $this->addFlash('error', 'Your cart is empty.');
            return $this->redirectToRoute('cart_index');
        }

        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $order = new Order();
            $order->setCustomer($customer);
            $order->setShippingAddress($address);
            $order->setContactNumber($contact);
            $order->setPaymentMethod('COD');
            $order->setStatus('Pending');

            $totalCents = 0;

            // Sort IDs for consistent locking order.
            ksort($cart, SORT_NUMERIC);

            foreach ($cart as $id => $quantity) {
                $quantity = filter_var(
                    $quantity,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($quantity === false) {
                    throw new \RuntimeException('Invalid cart quantity.');
                }

                $product = $em->find(
                    \App\Entity\Product::class,
                    (int) $id,
                    LockMode::PESSIMISTIC_WRITE
                );

                if (!$product) {
                    throw new \RuntimeException('A product is no longer available.');
                }

                if ($product->getStock() < $quantity) {
                    throw new \RuntimeException(
                        'Not enough stock for ' . $product->getName()
                    );
                }

                $price = $product->getPrice();
                $priceCents = (int) round((float) $price * 100);
                $totalCents += $priceCents * $quantity;

                $item = new OrderItem();
                $item->setCustomerOrder($order);
                $item->setProduct($product);
                $item->setQuantity($quantity);
                $item->setUnitPrice($price);

                $product->setStock($product->getStock() - $quantity);

                $em->persist($item);
            }

            $order->setTotalAmount(
                number_format($totalCents / 100, 2, '.', '')
            );

            $em->persist($order);
            $em->flush();
            $connection->commit();

            $session->remove('cart');

            $this->addFlash('success', 'Your order was placed successfully!');

            return $this->redirectToRoute('checkout_success', [
                'id' => $order->getId(),
            ]);

        } catch (\RuntimeException $e) {
            $connection->rollBack();
            $em->clear();

            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('checkout_index');
        } catch (\Throwable $e) {
            $connection->rollBack();
            $em->clear();

            throw $e;
        }
    }

    #[Route('/checkout/success/{id}', name: 'checkout_success', methods: ['GET'])]
    public function success(
        Order $order
    ): Response {
        if ($order->getCustomer()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }
}

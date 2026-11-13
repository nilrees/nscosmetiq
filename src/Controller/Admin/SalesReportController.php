<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SalesReportController extends AbstractController
{
    // Read and validate the selected dates.
    private function getDateFilters(Request $request): array
    {
        $startDate = $request->query->get('start_date', '');
        $endDate = $request->query->get('end_date', '');

        $startDate = is_string($startDate) ? $startDate : '';
        $endDate = is_string($endDate) ? $endDate : '';

        foreach ([$startDate, $endDate] as $date) {
            if ($date === '') {
                continue;
            }

            $parsed = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );

            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw $this->createNotFoundException(
                    'Invalid sales report date.'
                );
            }
        }

        if ($startDate !== '' && $endDate !== ''
            && $startDate > $endDate) {
            throw $this->createNotFoundException(
                'Start date cannot be later than end date.'
            );
        }

        return [$startDate, $endDate];
    }

    // Retrieve delivered orders within the date range.
    private function getFilteredOrders(
        OrderRepository $orderRepository,
        string $startDate,
        string $endDate
    ): array {
        $builder = $orderRepository->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', 'Delivered')
            ->orderBy('o.createdAt', 'DESC');

        if ($startDate !== '') {
            $builder
                ->andWhere('o.createdAt >= :startDate')
                ->setParameter(
                    'startDate',
                    new \DateTimeImmutable($startDate . ' 00:00:00')
                );
        }

        if ($endDate !== '') {
            $builder
                ->andWhere('o.createdAt < :endDate')
                ->setParameter(
                    'endDate',
                    (new \DateTimeImmutable($endDate . ' 00:00:00'))
                        ->modify('+1 day')
                );
        }

        return $builder->getQuery()->getResult();
    }

    #[Route('/admin/sales', name: 'admin_sales_report', methods: ['GET'])]
    public function index(
        Request $request,
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository
    ): Response {
        [$startDate, $endDate] = $this->getDateFilters($request);

        $deliveredOrders = $this->getFilteredOrders(
            $orderRepository,
            $startDate,
            $endDate
        );

        $totalRevenue = 0.0;
        $totalProductsSold = 0;
        $monthlySales = [];
        $bestSellingProducts = [];

        foreach ($deliveredOrders as $order) {
            $amount = (float) $order->getTotalAmount();
            $totalRevenue += $amount;

            $month = $order->getCreatedAt()->format('Y-m');

            $monthlySales[$month] =
                ($monthlySales[$month] ?? 0) + $amount;

            $items = $orderItemRepository->findBy([
                'customerOrder' => $order,
            ]);

            foreach ($items as $item) {
                $quantity = $item->getQuantity() ?? 0;
                $totalProductsSold += $quantity;

                $product = $item->getProduct();

                if ($product === null) {
                    continue;
                }

                $id = $product->getId();

                if (!isset($bestSellingProducts[$id])) {
                    $bestSellingProducts[$id] = [
                        'product' => $product,
                        'quantity' => 0,
                        'revenue' => 0.0,
                    ];
                }

                $bestSellingProducts[$id]['quantity'] += $quantity;
                $bestSellingProducts[$id]['revenue'] +=
                    $quantity * (float) $item->getUnitPrice();
            }
        }

        ksort($monthlySales);

        uasort(
            $bestSellingProducts,
            fn ($a, $b) => $b['quantity'] <=> $a['quantity']
        );

        $bestSellingProducts = array_slice(
            array_values($bestSellingProducts),
            0,
            5
        );

        $completedOrderCount = count($deliveredOrders);

        $averageOrderValue = $completedOrderCount > 0
            ? $totalRevenue / $completedOrderCount
            : 0.0;

        return $this->render('admin/sales_report/index.html.twig', [
            'totalRevenue' => $totalRevenue,
            'completedOrderCount' => $completedOrderCount,
            'totalProductsSold' => $totalProductsSold,
            'averageOrderValue' => $averageOrderValue,
            'monthlySales' => $monthlySales,
            'bestSellingProducts' => $bestSellingProducts,
            'deliveredOrders' => array_slice($deliveredOrders, 0, 10),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    #[Route('/admin/sales/export', name: 'admin_sales_export', methods: ['GET'])]
    public function export(
        Request $request,
        OrderRepository $orderRepository
    ): Response {
        [$startDate, $endDate] = $this->getDateFilters($request);

        $orders = $this->getFilteredOrders(
            $orderRepository,
            $startDate,
            $endDate
        );

        $response = new StreamedResponse(
            function () use ($orders): void {
                $output = fopen('php://output', 'w');

                if ($output === false) {
                    return;
                }

                fwrite($output, "\xEF\xBB\xBF");

                fputcsv($output, [
                    'Order ID',
                    'Customer Email',
                    'Order Date',
                    'Status',
                    'Payment Method',
                    'Total Amount (PHP)',
                ]);

                foreach ($orders as $order) {
                    $email = $order->getCustomer()?->getEmail() ?? '';

                    // Protect Excel against formula injection.
                    if (preg_match('/^\s*[=+\-@]/u', $email)) {
                        $email = "'" . $email;
                    }

                    fputcsv($output, [
                        $order->getId(),
                        $email,
                        $order->getCreatedAt()->format('Y-m-d H:i:s'),
                        $order->getStatus(),
                        $order->getPaymentMethod(),
                        $order->getTotalAmount(),
                    ]);
                }

                fclose($output);
            }
        );

        $response->headers->set(
            'Content-Type',
            'text/csv; charset=UTF-8'
        );

        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="ns_cosmetiq_sales_report.csv"'
        );

        $response->headers->set(
            'Cache-Control',
            'no-store, private'
        );

        return $response;
    }
}

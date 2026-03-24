<?php

function invoice_ascii(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false) {
        $text = $converted;
    }

    return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
}

function invoice_pdf_escape(string $text): string
{
    $text = invoice_ascii($text);
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\(', $text);
    $text = str_replace(')', '\)', $text);
    return $text;
}

function build_simple_pdf(array $pages, string $destination): bool
{
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $pageRefs = [];
    $objNum = 4;

    foreach ($pages as $pageLines) {
        $content = "BT\n/F1 12 Tf\n16 TL\n50 790 Td\n";
        $first = true;
        foreach ($pageLines as $line) {
            $safeLine = invoice_pdf_escape($line);
            if ($first) {
                $content .= '(' . $safeLine . ") Tj\n";
                $first = false;
            } else {
                $content .= "T*\n(" . $safeLine . ") Tj\n";
            }
        }
        $content .= "ET";

        $contentObj = $objNum++;
        $pageObj = $objNum++;

        $objects[$contentObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents {$contentObj} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
        $pageRefs[] = "{$pageObj} 0 R";
    }

    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];

    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $maxObj = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= $maxObj; $i++) {
        $offset = $offsets[$i] ?? 0;
        $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";

    return file_put_contents($destination, $pdf) !== false;
}

function generate_order_invoice_pdf(array $orderData, string $destination): bool
{
    $lines = [
        'TRIKUT RESTAURANT & CAFE',
        'Customer Bill / Order Receipt',
        '----------------------------------------',
        'Order ID: #' . ($orderData['order_id'] ?? ''),
        'Date: ' . ($orderData['created_at'] ?? date('Y-m-d H:i')),
        'Customer: ' . ($orderData['name'] ?? ''),
        'Phone: ' . ($orderData['phone'] ?? ''),
        'Delivery Type: ' . ($orderData['delivery_type'] ?? ''),
        '----------------------------------------',
        'Items:',
    ];

    foreach ($orderData['items'] ?? [] as $item) {
        $qty = (int) ($item['qty'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $lineTotal = $qty * $price;
        $lines[] = sprintf(
            '%s x%d @ Rs %0.2f = Rs %0.2f',
            (string) ($item['name'] ?? 'Item'),
            $qty,
            $price,
            $lineTotal
        );
    }

    $lines[] = '----------------------------------------';
    $lines[] = 'Total: Rs ' . number_format((float) ($orderData['total'] ?? 0), 2);
    $lines[] = '----------------------------------------';
    $lines[] = 'Thank you for your order.';

    $pages = array_chunk($lines, 40);
    return build_simple_pdf($pages, $destination);
}

function generate_booking_invoice_pdf(array $bookingData, string $destination): bool
{
    $lines = [
        'TRIKUT RESTAURANT & CAFE',
        'Reservation Receipt',
        '----------------------------------------',
        'Booking ID: #' . ($bookingData['booking_id'] ?? ''),
        'Created: ' . ($bookingData['created_at'] ?? date('Y-m-d H:i')),
        'Guest Name: ' . ($bookingData['name'] ?? ''),
        'Phone: ' . ($bookingData['phone'] ?? ''),
        'Reservation Time: ' . ($bookingData['date'] ?? ''),
        'Guests: ' . ($bookingData['size'] ?? ''),
        'Service Type: Table Reservation',
        '----------------------------------------',
        'Please keep this reservation slip for check-in.',
        'Thank you for choosing Trikut Restaurant & Cafe.',
    ];

    $pages = array_chunk($lines, 40);
    return build_simple_pdf($pages, $destination);
}

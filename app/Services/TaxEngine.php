<?php
namespace App\Services;

class TaxEngine {
    public static function calculate(array $tenantTaxProfile, array $items, string $placeOfSupplyStateCode): array {
        $mode = $tenantTaxProfile['registration_mode'] ?? 'unregistered';
        $supplierState = $tenantTaxProfile['registered_state_code'] ?? '21'; // 21 = Odisha
        $isInterstate = ($supplierState !== $placeOfSupplyStateCode);

        $documentType = match($mode) {
            'regular' => 'TAX_INVOICE',
            'composition' => 'BILL_OF_SUPPLY',
            default => 'COMMERCIAL_INVOICE'
        };

        $processedItems = [];
        $totalSubtotal = 0.0;
        $totalDiscount = 0.0;
        $totalTaxable = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 1);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount'] ?? 0);
            $gross = round($qty * $unitPrice, 2);
            $netAfterDiscount = max(0, $gross - $discount);

            $classification = $item['tax_classification'] ?? 'taxable';
            $ratePercent = (float)($item['tax_rate_percent'] ?? 0);
            $priceIncludesTax = !empty($item['price_includes_tax']);

            $taxableValue = $netAfterDiscount;
            $cgstRate = 0.0;
            $cgstAmount = 0.0;
            $sgstRate = 0.0;
            $sgstAmount = 0.0;
            $igstRate = 0.0;
            $igstAmount = 0.0;
            $lineTotal = $netAfterDiscount;

            // Only regular registered businesses charge/break down GST
            if ($mode === 'regular' && $classification === 'taxable' && $ratePercent > 0) {
                if ($priceIncludesTax) {
                    $taxableValue = round($netAfterDiscount / (1 + ($ratePercent / 100)), 2);
                    $totalTax = round($netAfterDiscount - $taxableValue, 2);
                } else {
                    $taxableValue = $netAfterDiscount;
                    $totalTax = round($taxableValue * ($ratePercent / 100), 2);
                }

                if ($isInterstate) {
                    $igstRate = $ratePercent;
                    $igstAmount = $totalTax;
                } else {
                    $cgstRate = $ratePercent / 2;
                    $cgstAmount = round($totalTax / 2, 2);
                    $sgstRate = $ratePercent / 2;
                    $sgstAmount = round($totalTax - $cgstAmount, 2); // Avoid 1 paisa rounding drift
                }

                $lineTotal = round($taxableValue + $cgstAmount + $sgstAmount + $igstAmount, 2);
            } else {
                // Non-taxable, composition, or unregistered
                $taxableValue = $netAfterDiscount;
                $lineTotal = $netAfterDiscount;
            }

            $processedItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'name_en' => $item['name_en'] ?? 'Item',
                'name_or' => $item['name_or'] ?? '',
                'unit' => $item['unit'] ?? 'pcs',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_classification' => $classification,
                'tax_rate_percent' => $ratePercent,
                'taxable_value' => $taxableValue,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => $igstRate,
                'igst_amount' => $igstAmount,
                'line_total' => $lineTotal,
            ];

            $totalSubtotal += $gross;
            $totalDiscount += $discount;
            $totalTaxable += $taxableValue;
            $totalCgst += $cgstAmount;
            $totalSgst += $sgstAmount;
            $totalIgst += $igstAmount;
            $totalAmount += $lineTotal;
        }

        return [
            'document_type' => $documentType,
            'registration_mode' => $mode,
            'supplier_state_code' => $supplierState,
            'place_of_supply_state_code' => $placeOfSupplyStateCode,
            'is_interstate' => $isInterstate,
            'subtotal' => round($totalSubtotal, 2),
            'discount_amount' => round($totalDiscount, 2),
            'taxable_amount' => round($totalTaxable, 2),
            'cgst_amount' => round($totalCgst, 2),
            'sgst_amount' => round($totalSgst, 2),
            'igst_amount' => round($totalIgst, 2),
            'tax_amount' => round($totalCgst + $totalSgst + $totalIgst, 2),
            'total_amount' => round($totalAmount, 2),
            'items' => $processedItems,
        ];
    }
}

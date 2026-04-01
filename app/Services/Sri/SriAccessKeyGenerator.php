<?php

namespace App\Services\Sri;

use App\Models\SriInvoice;
use Carbon\Carbon;

class SriAccessKeyGenerator
{
    private array $docTypeCodes = [
        'invoice' => '01',
        'sale_note' => '03',
        'credit_note' => '04',
        'debit_note' => '05',
        'purchase_liquidation' => '03',
    ];

    public function generate(
        string $issueDate,
        string $invoiceType,
        string $ruc,
        string $environment,
        string $establishment,
        string $emissionPoint,
        string $sequential,
    ): string {
        $date = Carbon::parse($issueDate)->format('dmY');
        $docType = $this->docTypeCodes[$invoiceType] ?? '01';
        $env = $environment === 'production' ? '2' : '1';
        $numericCode = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        $base = $date . $docType . $ruc . $env . $establishment . $emissionPoint . $sequential . '1' . $numericCode;

        $checkDigit = $this->calculateMod11($base);
        $accessKey = $base . $checkDigit;

        // Ensure uniqueness
        while (SriInvoice::where('access_key', $accessKey)->exists()) {
            $numericCode = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $base = $date . $docType . $ruc . $env . $establishment . $emissionPoint . $sequential . '1' . $numericCode;
            $checkDigit = $this->calculateMod11($base);
            $accessKey = $base . $checkDigit;
        }

        return $accessKey;
    }

    private function calculateMod11(string $data): string
    {
        $weights = [2, 3, 4, 5, 6, 7];
        $digits = array_reverse(str_split($data));
        $sum = 0;

        foreach ($digits as $i => $digit) {
            $sum += (int) $digit * $weights[$i % 6];
        }

        $remainder = $sum % 11;

        if ($remainder === 0) return '0';
        if ($remainder === 1) return '1';

        return (string) (11 - $remainder);
    }
}

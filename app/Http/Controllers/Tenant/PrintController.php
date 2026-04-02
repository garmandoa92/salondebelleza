<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Sale;
use App\Models\SriInvoice;
use App\Models\Stylist;
use App\Services\PrintService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __construct(private PrintService $printService)
    {
    }

    public function sale(Sale $sale)
    {
        return response($this->printService->generateSaleReceipt($sale))
            ->header('Content-Type', 'text/html');
    }

    public function appointment(Appointment $appointment)
    {
        return response($this->printService->generateAppointmentTicket($appointment))
            ->header('Content-Type', 'text/html');
    }

    public function closing(string $date)
    {
        $parsedDate = Carbon::parse($date);

        return response($this->printService->generateDailyClosing($parsedDate))
            ->header('Content-Type', 'text/html');
    }

    public function invoice(SriInvoice $invoice)
    {
        return response($this->printService->generateInvoiceRide($invoice))
            ->header('Content-Type', 'text/html');
    }

    public function commission(Request $request, Stylist $stylist)
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->toDateString()));

        return response($this->printService->generateCommissionReport($stylist, $from, $to))
            ->header('Content-Type', 'text/html');
    }
}

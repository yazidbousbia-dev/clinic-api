<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['patient', 'consultation'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $invoices = $query->get();
        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'      => 'required|exists:patients,id',
            'consultation_id' => 'nullable|exists:consultations,id',
            'amount'          => 'required|numeric|min:0',
            'status'          => 'in:unpaid,paid,cancelled',
            'payment_method'  => 'nullable|in:cash,card,insurance',
        ]);

        $invoice = Invoice::create($data);
        return response()->json($invoice->load('patient'), 201);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['patient', 'consultation'])->findOrFail($id);
        return response()->json($invoice);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $data = $request->validate([
            'status'         => 'in:unpaid,paid,cancelled',
            'payment_method' => 'nullable|in:cash,card,insurance',
            'amount'         => 'numeric|min:0',
        ]);

        if (isset($data['status']) && $data['status'] === 'paid') {
            $data['paid_at'] = now();
        }

        $invoice->update($data);
        return response()->json($invoice);
    }

    public function pay(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $request->validate([
            'payment_method' => 'required|in:cash,card,insurance',
        ]);

        $invoice->update([
            'status'         => 'paid',
            'payment_method' => $request->payment_method,
            'paid_at'        => now(),
        ]);

        return response()->json($invoice->load('patient'));
    }

    public function destroy($id)
    {
        Invoice::findOrFail($id)->delete();
        return response()->json(['message' => 'Invoice deleted']);
    }
}
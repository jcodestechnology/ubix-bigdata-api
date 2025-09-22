<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TransactionDataTableController extends Controller
{
    /**
     * Server-side DataTable response
     */
    public function index(Request $request)
    {
        $request->validate([
            'draw' => 'required|integer',
            'start' => 'required|integer',
            'length' => 'required|integer|max:1000', // Prevent excessive requests
            'search.value' => 'nullable|string|max:255',
            'order' => 'nullable|array',
            'columns' => 'nullable|array',
        ]);

        // Base query
        $query = Transaction::query();

        // Global search
        if (!empty($request->search['value'])) {
            $searchTerm = $request->search['value'];
            $query->where(function ($q) use ($searchTerm) {
                $columns = [
                    'transaction_id', 'account_number', 'merchant_name', 
                    'merchant_category', 'description', 'reference_number',
                    'country', 'city'
                ];
                
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        // Column-specific search
        if (!empty($request->columns)) {
            foreach ($request->columns as $column) {
                if (!empty($column['search']['value'])) {
                    $query->where($column['data'], 'LIKE', "%{$column['search']['value']}%");
                }
            }
        }

        // Get total records count
        $totalRecords = Cache::remember('transactions_total', 300, function () {
            return Transaction::count();
        });

        // Get filtered count
        $filteredRecords = $query->count();

        // Ordering
        if (!empty($request->order)) {
            foreach ($request->order as $order) {
                $columnIndex = $order['column'];
                $columnName = $request->columns[$columnIndex]['data'];
                $direction = $order['dir'];
                
                $query->orderBy($columnName, $direction);
            }
        } else {
            // Default ordering
            $query->orderBy('transaction_date', 'desc');
        }

        // Pagination
        $data = $query->skip($request->start)
                     ->take($request->length)
                     ->get();

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Get filter options for specific column
     */
    public function getFilterOptions(Request $request, $column)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|max:100',
        ]);

        $search = $request->input('search', '');
        $limit = $request->input('limit', 10);

        $query = Transaction::select($column)
            ->distinct()
            ->whereNotNull($column)
            ->where($column, '!=', '');

        if (!empty($search)) {
            $query->where($column, 'LIKE', "%{$search}%");
        }

        $options = $query->orderBy($column)
            ->take($limit)
            ->pluck($column);

        return response()->json($options);
    }


}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\TransactionRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'sort' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = $user->transactions()->with('category');

        foreach (['type', 'category_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('description', $search)
                    ->orWhereHas('category', fn (Builder $category) => $category->whereLike('name', $search));
            });
        }

        $sort = $filters['sort'] ?? 'desc';

        return TransactionResource::collection($query->orderBy('transaction_date', $sort)->orderBy('id', $sort)->paginate($filters['per_page'] ?? 15));
    }

    public function store(TransactionRequest $request): TransactionResource
    {
        try {
            $transaction = $this->user($request)->transactions()->create($request->validated());
        } catch (QueryException $exception) {
            $this->categoryError($exception);
        }

        return new TransactionResource($transaction->load('category'));
    }

    public function show(Request $request, int $transaction): TransactionResource
    {
        return new TransactionResource($this->user($request)->transactions()->with('category')->findOrFail($transaction));
    }

    public function update(TransactionRequest $request, int $transaction): TransactionResource
    {
        $record = $this->user($request)->transactions()->findOrFail($transaction);

        try {
            $record->update($request->validated());
        } catch (QueryException $exception) {
            $this->categoryError($exception);
        }

        return new TransactionResource($record->load('category'));
    }

    public function destroy(Request $request, int $transaction): Response
    {
        $this->user($request)->transactions()->findOrFail($transaction)->delete();

        return response()->noContent();
    }

    protected function categoryError(QueryException $exception): never
    {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        throw ValidationException::withMessages(['category_id' => 'หมวดหมู่มีการเปลี่ยนแปลง กรุณาเลือกใหม่']);
    }
}

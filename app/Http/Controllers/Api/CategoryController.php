<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);

        return CategoryResource::collection($this->user($request)->categories()->withCount('transactions')->orderBy('type')->orderBy('name')->paginate($validated['per_page'] ?? 15));
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        try {
            $record = $this->user($request)->categories()->create($request->validated());
        } catch (QueryException $exception) {
            $this->categoryError($exception);
        }

        return new CategoryResource($record->loadCount('transactions'));
    }

    public function show(Request $request, int $category): CategoryResource
    {
        return new CategoryResource($this->user($request)->categories()->withCount('transactions')->findOrFail($category));
    }

    public function update(CategoryRequest $request, int $category): CategoryResource
    {
        $record = $this->user($request)->categories()->findOrFail($category);

        try {
            $record->update($request->validated());
        } catch (QueryException $exception) {
            $this->categoryError($exception);
        }

        return new CategoryResource($record->loadCount('transactions'));
    }

    public function destroy(Request $request, int $category): Response
    {
        $record = $this->user($request)->categories()->findOrFail($category);

        if ($record->transactions()->exists()) {
            throw ValidationException::withMessages(['category' => 'ลบไม่ได้ เพราะหมวดหมู่นี้มีรายการอยู่']);
        }

        try {
            DB::transaction(fn () => $record->delete());
        } catch (QueryException $exception) {
            $this->categoryError($exception);
        }

        return response()->noContent();
    }

    protected function categoryError(QueryException $exception): never
    {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        throw ValidationException::withMessages(['category' => 'บันทึกไม่ได้ ชื่อซ้ำหรือมีรายการใช้หมวดหมู่นี้แล้ว']);
    }
}

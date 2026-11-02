<?php

namespace App\Http\Requests\Api;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    protected ?Category $record = null;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function authorize(): bool
    {
        if ($id = $this->route('category')) {
            $this->record = $this->user('api')->categories()->whereKey($id)->firstOrFail();
        }

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $presence = $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];
        $type = $this->input('type', $this->record?->type);
        $name = $this->input('name', $this->record?->name);

        return [
            'name' => ['bail', ...$presence, 'string', 'max:100', Rule::unique('categories', 'name')->where('user_id', $this->user('api')->id)->where('type', is_string($type) ? $type : '')->ignore($this->record?->id)],
            'type' => ['bail', ...$presence, Rule::in(['income', 'expense']), function (string $attribute, mixed $value, \Closure $fail) use ($name): void {
                if ($this->record !== null && $value !== $this->record->type) {
                    if ($this->record->transactions()->exists()) {
                        $fail('เปลี่ยนประเภทไม่ได้ เพราะหมวดหมู่นี้มีรายการอยู่แล้ว');
                    }

                    if (is_string($name) && $this->user('api')->categories()->where('name', $name)->where('type', $value)->whereKeyNot($this->record->id)->exists()) {
                        $fail('คุณมีหมวดหมู่ชื่อนี้ในประเภทเดียวกันแล้ว');
                    }
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'กรุณากรอกชื่อหมวดหมู่',
            'name.max' => 'ชื่อหมวดหมู่ต้องยาวไม่เกิน 100 ตัวอักษร',
            'name.unique' => 'คุณมีหมวดหมู่ชื่อนี้ในประเภทเดียวกันแล้ว',
            'type.in' => 'กรุณาเลือก income หรือ expense',
        ];
    }
}

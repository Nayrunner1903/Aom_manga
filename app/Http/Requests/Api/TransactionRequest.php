<?php

namespace App\Http\Requests\Api;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    protected ?Transaction $record = null;

    public function authorize(): bool
    {
        if ($id = $this->route('transaction')) {
            $this->record = $this->user('api')->transactions()->whereKey($id)->firstOrFail();
        }

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $presence = $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];
        $type = $this->input('type', $this->record?->type);

        return [
            'type' => [...$presence, Rule::in(['income', 'expense'])],
            'category_id' => [...$presence, 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user('api')->id)->where('type', is_string($type) ? $type : '')],
            'amount_satang' => [...$presence, 'integer', 'min:1', 'max:99999999999999'],
            'transaction_date' => [...$presence, 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->record !== null && $this->has('type') && ! $this->has('category_id') && $this->input('type') !== $this->record->type) {
                    $validator->errors()->add('category_id', 'เมื่อเปลี่ยนประเภท กรุณาเลือกหมวดหมู่ให้ตรงกัน');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.*' => 'กรุณาเลือก income หรือ expense',
            'category_id.*' => 'กรุณาเลือกหมวดหมู่ของคุณให้ตรงกับประเภท',
            'amount_satang.*' => 'จำนวนเงินต้องเป็นจำนวนเต็มสตางค์ ตั้งแต่ 1 ถึง 99999999999999',
            'transaction_date.*' => 'กรุณาระบุวันที่เป็น YYYY-MM-DD ระหว่างปี ค.ศ. 1900–9999',
            'description.*' => 'รายละเอียดต้องเป็นข้อความไม่เกิน 1,000 ตัวอักษร',
        ];
    }
}

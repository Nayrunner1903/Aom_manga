<?php

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('บันทึกรายรับรายจ่าย')] class extends Component
{
    #[Locked]
    public ?int $transactionId = null;

    public string $type = 'expense';

    public string $amount = '';

    public string $categoryId = '';

    public string $description = '';

    public string $transactionDate = '';

    public function mount(?int $transaction = null): void
    {
        $this->transactionDate = now('Asia/Bangkok')->toDateString();

        if ($transaction !== null) {
            $record = Auth::user()->transactions()->findOrFail($transaction);
            $this->transactionId = $record->id;
            $this->type = $record->type;
            $this->amount = sprintf('%d.%02d', intdiv($record->amount_satang, 100), $record->amount_satang % 100);
            $this->categoryId = (string) $record->category_id;
            $this->description = $record->description ?? '';
            $this->transactionDate = $record->transaction_date->toDateString();
        }
    }

    /** @return Collection<int, Category> */
    #[Computed]
    public function categories(): Collection
    {
        return Auth::user()->categories()->where('type', $this->type)->orderBy('name')->get();
    }

    public function updatedType(): void
    {
        $this->categoryId = '';
    }

    public function save(): void
    {
        $user = Auth::user();
        $record = $this->transactionId === null ? null : $user->transactions()->findOrFail($this->transactionId);

        $validated = $this->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'regex:/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/'],
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $user->id)->where('type', $this->type)],
            'description' => ['nullable', 'string', 'max:1000'],
            'transactionDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
        ], [
            'type.*' => 'กรุณาเลือกประเภทรายการให้ถูกต้อง',
            'amount.*' => 'กรุณากรอกจำนวนเงินไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง',
            'categoryId.*' => 'กรุณาเลือกหมวดหมู่ของคุณให้ตรงกับประเภทรายการ',
            'description.*' => 'รายละเอียดต้องยาวไม่เกิน 1,000 ตัวอักษร',
            'transactionDate.*' => 'กรุณาระบุวันที่ให้ถูกต้อง ระหว่างปี ค.ศ. 1900–9999',
        ]);

        [$baht, $fraction] = array_pad(explode('.', $validated['amount'], 2), 2, '');
        $satang = ((int) $baht * 100) + (int) str_pad($fraction, 2, '0');

        if ($satang === 0) {
            $this->addError('amount', 'จำนวนเงินต้องมากกว่า 0 บาท');

            return;
        }

        $attributes = [
            'type' => $validated['type'],
            'amount_satang' => $satang,
            'category_id' => $validated['categoryId'],
            'description' => $validated['description'] === '' ? null : $validated['description'],
            'transaction_date' => $validated['transactionDate'],
        ];

        try {
            if ($record === null) {
                $user->transactions()->create($attributes);
            } else {
                $record->update($attributes);
            }
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $this->addError('categoryId', 'หมวดหมู่มีการเปลี่ยนแปลง กรุณาเลือกหมวดหมู่อีกครั้ง');

            return;
        }

        session()->flash('status', 'บันทึกรายการเรียบร้อยแล้ว');
        $this->redirectRoute('transactions.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <div>
        <span class="manga-kicker mb-3">จดเพิ่มอีกหนึ่งเรื่อง</span>
        <flux:heading size="xl" level="1">{{ $transactionId ? 'แก้ไขรายการ' : 'เพิ่มรายรับรายจ่าย' }}</flux:heading>
        <flux:text>บันทึกการเงินของคุณ จำนวนเงินเป็นบาท (THB)</flux:text>
    </div>
    <form wire:submit="save" class="space-y-5 rounded-xl border-2 border-zinc-900 bg-white p-5 dark:border-zinc-200 dark:bg-zinc-900">
        <flux:select wire:model.live="type" label="ประเภทรายการ">
            <option value="expense">รายจ่าย</option><option value="income">รายรับ</option>
        </flux:select>
        <flux:input wire:model="amount" label="จำนวนเงิน (บาท)" inputmode="decimal" placeholder="0.00" required />
        <flux:select wire:model="categoryId" label="หมวดหมู่" required>
            <option value="">เลือกหมวดหมู่</option>
            @foreach ($this->categories as $category)
                <option wire:key="category-{{ $category->id }}" value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </flux:select>
        <flux:button :href="route('categories.index')" variant="ghost" size="sm" wire:navigate>จัดการหมวดหมู่</flux:button>
        <flux:input wire:model="transactionDate" label="วันที่ทำรายการ" type="date" min="1900-01-01" max="9999-12-31" required />
        <flux:textarea wire:model="description" label="รายละเอียด (ไม่บังคับ)" rows="3" maxlength="1000" />
        <div class="flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">บันทึกรายการ</flux:button>
            <flux:button :href="route('transactions.index')" wire:navigate>ยกเลิก</flux:button>
        </div>
    </form>
</section>

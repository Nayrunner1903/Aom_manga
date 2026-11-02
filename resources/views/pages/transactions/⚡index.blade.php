<?php

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('ประวัติรายรับรายจ่าย')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = '';

    public string $categoryId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sort = 'desc';

    public function applyFilters(): void
    {
        $this->resetPage();
        $this->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'categoryId' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', Auth::id())],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', Rule::when($this->dateFrom !== '', ['after_or_equal:dateFrom'])],
            'sort' => ['required', Rule::in(['asc', 'desc'])],
        ], [
            'search.*' => 'คำค้นหาต้องยาวไม่เกิน 200 ตัวอักษร',
            'type.*' => 'กรุณาเลือกประเภทรายการให้ถูกต้อง',
            'categoryId.*' => 'กรุณาเลือกหมวดหมู่ของคุณ',
            'dateFrom.*' => 'กรุณาระบุวันที่เริ่มต้นให้ถูกต้อง',
            'dateTo.*' => 'วันที่สิ้นสุดต้องถูกต้องและไม่น้อยกว่าวันที่เริ่มต้น',
            'sort.*' => 'กรุณาเลือกลำดับวันที่ให้ถูกต้อง',
        ]);
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type', 'categoryId', 'dateFrom', 'dateTo', 'sort');
        $this->resetValidation();
        $this->resetPage();
    }

    /** @return Collection<int, Category> */
    #[Computed]
    public function categories(): Collection
    {
        return Auth::user()->categories()->orderBy('type')->orderBy('name')->get();
    }

    /** @return LengthAwarePaginator<int, Transaction> */
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Auth::user()->transactions()->with('category')
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->categoryId !== '', fn (Builder $query) => $query->where('category_id', $this->categoryId))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('transaction_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('transaction_date', '<=', $this->dateTo))
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereLike('description', '%'.$this->search.'%')
                        ->orWhereHas('category', fn (Builder $query) => $query->whereLike('name', '%'.$this->search.'%'));
                });
            })
            ->orderBy('transaction_date', $this->sort === 'asc' ? 'asc' : 'desc')
            ->orderBy('id', $this->sort === 'asc' ? 'asc' : 'desc')
            ->paginate(15);
    }

    public function deleteTransaction(int $id): void
    {
        Auth::user()->transactions()->findOrFail($id)->delete();
        $this->resetPage();
        session()->flash('status', 'ลบรายการเรียบร้อยแล้ว');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><span class="manga-kicker mb-3">บทที่ 02 · ทุกบาทมีที่มา</span><flux:heading size="xl" level="1">ประวัติรายรับรายจ่าย</flux:heading><flux:text>ดูและจัดการรายการทั้งหมดของคุณ</flux:text></div>
        <flux:button :href="route('transactions.create')" variant="primary" wire:navigate>เพิ่มรายการ</flux:button>
    </div>
    @if (session('status'))
        <p role="status" class="rounded-lg border-2 border-zinc-500 p-3">{{ session('status') }}</p>
    @endif
    <form wire:submit="applyFilters" class="grid gap-4 rounded-xl border border-zinc-300 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-600">
        <flux:input wire:model="search" label="ค้นหารายละเอียดหรือหมวดหมู่" placeholder="พิมพ์คำค้นหา" maxlength="200" />
        <flux:select wire:model="type" label="ประเภทรายการ"><option value="">ทุกประเภท</option><option value="income">รายรับ</option><option value="expense">รายจ่าย</option></flux:select>
        <flux:select wire:model="categoryId" label="หมวดหมู่">
            <option value="">ทุกหมวดหมู่</option>
            @foreach ($this->categories as $category)
                <option wire:key="filter-category-{{ $category->id }}" value="{{ $category->id }}">{{ $category->name }} ({{ $category->type === 'income' ? 'รายรับ' : 'รายจ่าย' }})</option>
            @endforeach
        </flux:select>
        <flux:select wire:model="sort" label="เรียงตามวันที่"><option value="desc">ใหม่ไปเก่า</option><option value="asc">เก่าไปใหม่</option></flux:select>
        <flux:input wire:model="dateFrom" label="ตั้งแต่วันที่" type="date" />
        <flux:input wire:model="dateTo" label="ถึงวันที่" type="date" />
        <div class="flex items-end gap-2 sm:col-span-2"><flux:button type="submit" variant="primary">ค้นหาและกรอง</flux:button><flux:button wire:click="clearFilters" type="button">ล้างตัวกรอง</flux:button></div>
    </form>
    @php($transactions = $this->transactions)
    <div wire:loading.delay role="status" class="text-sm">กำลังโหลดรายการ…</div>
    <div class="overflow-x-auto rounded-xl border-2 border-zinc-900 dark:border-zinc-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-100 dark:bg-zinc-900"><tr><th scope="col" class="p-3">วันที่</th><th scope="col" class="p-3">ประเภท / หมวดหมู่</th><th scope="col" class="p-3">รายละเอียด</th><th scope="col" class="p-3 text-right">จำนวนเงิน</th><th scope="col" class="p-3">จัดการ</th></tr></thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($transactions as $transaction)
                    <tr wire:key="transaction-{{ $transaction->id }}">
                        <td class="whitespace-nowrap p-3">{{ $transaction->transaction_date->locale('th')->translatedFormat('j M') }} {{ $transaction->transaction_date->year + 543 }}</td>
                        <td class="p-3"><span class="font-bold">{{ $transaction->type === 'income' ? 'รายรับ' : 'รายจ่าย' }}</span><br>{{ $transaction->category->name }}</td>
                        <td class="max-w-xs break-words p-3">{{ $transaction->description ?: '—' }}</td>
                        <td class="whitespace-nowrap p-3 text-right font-semibold"><x-money :satang="$transaction->amount_satang" /></td>
                        <td class="p-3"><div class="flex gap-2"><flux:button size="sm" :href="route('transactions.edit', $transaction)" wire:navigate>แก้ไข</flux:button><flux:button size="sm" wire:click="deleteTransaction({{ $transaction->id }})" wire:confirm="ยืนยันการลบรายการนี้? การลบไม่สามารถย้อนกลับได้" wire:loading.attr="disabled">ลบ</flux:button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-10 text-center">ไม่พบรายการ ลองเปลี่ยนตัวกรองหรือเพิ่มรายการใหม่</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-finance-pagination :paginator="$transactions" />
</section>

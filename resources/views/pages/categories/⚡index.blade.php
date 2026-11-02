<?php

use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('จัดการหมวดหมู่')] class extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'expense';

    /** @return LengthAwarePaginator<int, Category> */
    #[Computed]
    public function categories(): LengthAwarePaginator
    {
        return Auth::user()->categories()->withCount('transactions')->orderBy('type')->orderBy('name')->paginate(15);
    }

    public function editCategory(int $id): void
    {
        $category = Auth::user()->categories()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->type = $category->type;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'name', 'type');
        $this->resetValidation();
    }

    public function save(): void
    {
        $user = Auth::user();
        $category = $this->editingId === null ? null : $user->categories()->findOrFail($this->editingId);
        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->where('user_id', $user->id)->where('type', $this->type)->ignore($category?->id)],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ], [
            'name.required' => 'กรุณากรอกชื่อหมวดหมู่',
            'name.max' => 'ชื่อหมวดหมู่ต้องยาวไม่เกิน 100 ตัวอักษร',
            'name.unique' => 'คุณมีหมวดหมู่ชื่อนี้ในประเภทเดียวกันแล้ว',
            'type.*' => 'กรุณาเลือกประเภทหมวดหมู่ให้ถูกต้อง',
        ]);

        if ($category !== null && $category->type !== $validated['type'] && $category->transactions()->exists()) {
            $this->addError('type', 'เปลี่ยนประเภทไม่ได้ เพราะหมวดหมู่นี้มีรายการอยู่แล้ว');

            return;
        }

        try {
            if ($category === null) {
                $user->categories()->create($validated);
            } else {
                $category->update($validated);
            }
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $this->addError('name', 'บันทึกไม่ได้ ชื่ออาจซ้ำหรือหมวดหมู่มีรายการใช้งานอยู่ กรุณาตรวจสอบอีกครั้ง');

            return;
        }

        $this->cancelEdit();
        $this->resetPage();
        session()->flash('status', 'บันทึกหมวดหมู่เรียบร้อยแล้ว');
    }

    public function deleteCategory(int $id): void
    {
        $this->resetValidation();
        $category = Auth::user()->categories()->findOrFail($id);

        if ($category->transactions()->exists()) {
            $this->addError('category', 'ลบไม่ได้ เพราะหมวดหมู่นี้มีรายการอยู่ กรุณาย้ายหรือลบรายการก่อน');

            return;
        }

        try {
            $category->delete();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $this->addError('category', 'ลบไม่ได้ เพราะมีรายการเริ่มใช้หมวดหมู่นี้แล้ว');

            return;
        }

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        $this->resetPage();
        session()->flash('status', 'ลบหมวดหมู่เรียบร้อยแล้ว');
    }
}; ?>

<section class="w-full space-y-6">
    <div><span class="manga-kicker mb-3">บทที่ 03 · จัดเรื่องเงินให้เป็นหมวด</span><flux:heading size="xl" level="1">จัดการหมวดหมู่</flux:heading><flux:text>หมวดหมู่ส่วนตัวสำหรับรายรับและรายจ่ายของคุณ</flux:text></div>
    @if (session('status'))
        <p role="status" class="rounded-lg border-2 border-zinc-500 p-3">{{ session('status') }}</p>
    @endif
    <form wire:submit="save" class="space-y-4 rounded-xl border-2 border-zinc-900 p-5 dark:border-zinc-200">
        <flux:heading level="2">{{ $editingId ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" label="ชื่อหมวดหมู่" maxlength="100" required />
            <flux:select wire:model="type" label="ประเภท"><option value="expense">รายจ่าย</option><option value="income">รายรับ</option></flux:select>
        </div>
        <flux:text>หมวดหมู่ที่มีรายการแล้วสามารถเปลี่ยนชื่อได้ แต่เปลี่ยนประเภทหรือลบไม่ได้</flux:text>
        <div class="flex gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">บันทึกหมวดหมู่</flux:button>@if ($editingId)<flux:button type="button" wire:click="cancelEdit">ยกเลิกการแก้ไข</flux:button>@endif</div>
    </form>
    <flux:error name="category" />
    @php($categories = $this->categories)
    <div class="overflow-x-auto rounded-xl border border-zinc-300 dark:border-zinc-600">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-100 dark:bg-zinc-900"><tr><th scope="col" class="p-3">หมวดหมู่</th><th scope="col" class="p-3">ประเภท</th><th scope="col" class="p-3">จำนวนรายการ</th><th scope="col" class="p-3">จัดการ</th></tr></thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($categories as $category)
                    <tr wire:key="category-{{ $category->id }}"><td class="break-words p-3 font-semibold">{{ $category->name }}</td><td class="p-3">{{ $category->type === 'income' ? 'รายรับ' : 'รายจ่าย' }}</td><td class="p-3">{{ $category->transactions_count }}</td><td class="p-3"><div class="flex gap-2"><flux:button size="sm" wire:click="editCategory({{ $category->id }})">แก้ไข</flux:button><flux:button size="sm" wire:click="deleteCategory({{ $category->id }})" wire:confirm="ยืนยันการลบหมวดหมู่นี้?" wire:loading.attr="disabled">ลบ</flux:button></div></td></tr>
                @empty
                    <tr><td colspan="4" class="p-10 text-center">ยังไม่มีหมวดหมู่ เพิ่มหมวดหมู่แรกได้จากแบบฟอร์มด้านบน</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-finance-pagination :paginator="$categories" />
</section>

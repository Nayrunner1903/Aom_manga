<?php

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ภาพรวมการเงิน')] class extends Component
{
    #[Locked]
    public string $month = '';

    public string $monthInput = '';

    public function mount(): void
    {
        $this->month = now('Asia/Bangkok')->format('Y-m');
        $this->monthInput = $this->month;
    }

    public function changeMonth(): void
    {
        $this->validate([
            'monthInput' => ['required', 'date_format:Y-m', 'after_or_equal:1900-01', 'before_or_equal:9999-12'],
        ], ['monthInput.*' => 'กรุณาเลือกเดือนให้ถูกต้อง ระหว่างปี ค.ศ. 1900–9999']);

        $this->month = $this->monthInput;
        unset($this->summary, $this->trends, $this->expenseCategories, $this->monthLabel);
    }

    protected function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month.'-01', 'Asia/Bangkok');
    }

    #[Computed]
    public function monthLabel(): string
    {
        $month = $this->monthStart();

        return $month->locale('th')->translatedFormat('F').' '.($month->year + 543);
    }

    /** @return array{income: int, expense: int, balance: int, monthlyIncome: int, monthlyExpense: int, monthlyBalance: int} */
    #[Computed]
    public function summary(): array
    {
        $month = $this->monthStart();
        $totals = Auth::user()->transactions()->select('type')
            ->selectRaw('SUM(amount_satang) AS total_satang')
            ->selectRaw('SUM(CASE WHEN date(transaction_date) BETWEEN ? AND ? THEN amount_satang ELSE 0 END) AS monthly_satang', [
                $month->toDateString(), $month->endOfMonth()->toDateString(),
            ])->groupBy('type')->toBase()->get()->keyBy('type');

        $income = (int) ($totals->get('income')?->total_satang ?? 0);
        $expense = (int) ($totals->get('expense')?->total_satang ?? 0);
        $monthlyIncome = (int) ($totals->get('income')?->monthly_satang ?? 0);
        $monthlyExpense = (int) ($totals->get('expense')?->monthly_satang ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'monthlyIncome' => $monthlyIncome,
            'monthlyExpense' => $monthlyExpense,
            'monthlyBalance' => $monthlyIncome - $monthlyExpense,
        ];
    }

    /** @return list<array{month: string, label: string, income: int, expense: int}> */
    #[Computed]
    public function trends(): array
    {
        $end = $this->monthStart();
        $start = $end->subMonths(5);
        $rows = Auth::user()->transactions()
            ->whereBetween('transaction_date', [$start->toDateString(), $end->endOfMonth()->toDateString().' 23:59:59'])
            ->selectRaw("strftime('%Y-%m', transaction_date) AS month, type, SUM(amount_satang) AS total_satang")
            ->groupBy('month', 'type')->toBase()->get()->keyBy(fn (object $row): string => $row->month.'-'.$row->type);

        $months = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $start->addMonths($offset);
            $key = $month->format('Y-m');
            $months[] = [
                'month' => $key,
                'label' => $month->locale('th')->translatedFormat('M').' '.($month->year + 543),
                'income' => (int) ($rows->get($key.'-income')?->total_satang ?? 0),
                'expense' => (int) ($rows->get($key.'-expense')?->total_satang ?? 0),
            ];
        }

        return $months;
    }

    /** @return list<array{id: int, name: string, satang: int}> */
    #[Computed]
    public function expenseCategories(): array
    {
        $month = $this->monthStart();

        return Auth::user()->transactions()->where('type', 'expense')
            ->whereBetween('transaction_date', [$month->toDateString(), $month->endOfMonth()->toDateString().' 23:59:59'])
            ->select('category_id')->selectRaw('SUM(amount_satang) AS total_satang')
            ->groupBy('category_id')->orderByDesc('total_satang')->orderBy('category_id')
            ->with('category:id,name')->get()
            ->map(fn (Transaction $row): array => [
                'id' => $row->category_id,
                'name' => $row->category->name,
                'satang' => (int) $row->getAttribute('total_satang'),
            ])->all();
    }

    /** @return Collection<int, Transaction> */
    #[Computed]
    public function recentTransactions(): Collection
    {
        return Auth::user()->transactions()->with('category')->orderByDesc('transaction_date')->orderByDesc('id')->limit(5)->get();
    }
}; ?>

<section class="w-full space-y-8">
    @php
        $summary = $this->summary;
        $trends = $this->trends;
        $maximum = max(1, ...array_column($trends, 'income'), ...array_column($trends, 'expense'));
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><span class="manga-kicker mb-3">บทที่ 01 · มองภาพรวม</span><flux:heading size="xl" level="1">ภาพรวมการเงิน</flux:heading><flux:text>ดูเงินเข้า เงินออก และการใช้จ่ายของคุณในที่เดียว</flux:text></div>
        <flux:button :href="route('transactions.create')" variant="primary" wire:navigate>เพิ่มรายการ</flux:button>
    </div>

    <div class="space-y-3">
        <flux:heading level="2">ยอดรวมทั้งหมด</flux:heading>
        <flux:text>รวมทุกรายการที่บันทึกไว้ รวมถึงรายการลงวันที่ล่วงหน้า ยอดส่วนนี้ไม่เปลี่ยนตามเดือนที่เลือก</flux:text>
        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border-2 border-zinc-900 p-5 dark:border-zinc-200"><dt class="text-sm">รายรับทั้งหมด</dt><dd class="mt-2 break-words text-2xl font-bold"><x-money :satang="$summary['income']" /></dd></div>
            <div class="rounded-xl border-2 border-zinc-900 p-5 dark:border-zinc-200"><dt class="text-sm">รายจ่ายทั้งหมด</dt><dd class="mt-2 break-words text-2xl font-bold"><x-money :satang="$summary['expense']" /></dd></div>
            <div class="rounded-xl border-2 border-zinc-900 bg-zinc-100 p-5 dark:border-zinc-200 dark:bg-zinc-900"><dt class="text-sm">ยอดคงเหลือ (รายรับ − รายจ่าย)</dt><dd class="mt-2 break-words text-2xl font-bold"><x-money :satang="$summary['balance']" /></dd></div>
        </dl>
    </div>

    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <flux:heading level="2" size="lg">สรุปเดือน{{ $this->monthLabel }}</flux:heading>
            <form wire:submit="changeMonth" class="flex flex-wrap items-end gap-3">
                <flux:input wire:model="monthInput" type="month" label="เลือกเดือน" min="1900-01" max="9999-12" required />
                <flux:button type="submit" wire:loading.attr="disabled">ดูเดือนนี้</flux:button>
            </form>
        </div>
        <div wire:loading.delay role="status" class="text-sm">กำลังคำนวณยอด…</div>
        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-300 p-5 dark:border-zinc-600"><dt>รายรับเดือนที่เลือก</dt><dd class="mt-2 text-xl font-semibold"><x-money :satang="$summary['monthlyIncome']" /></dd></div>
            <div class="rounded-xl border border-zinc-300 p-5 dark:border-zinc-600"><dt>รายจ่ายเดือนที่เลือก</dt><dd class="mt-2 text-xl font-semibold"><x-money :satang="$summary['monthlyExpense']" /></dd></div>
            <div class="rounded-xl border border-zinc-300 p-5 dark:border-zinc-600"><dt>ส่วนต่างรายรับและรายจ่ายเดือนนี้</dt><dd class="mt-2 text-xl font-semibold"><x-money :satang="$summary['monthlyBalance']" /></dd></div>
        </dl>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="space-y-5 rounded-xl border-2 border-zinc-900 p-5 dark:border-zinc-200" aria-labelledby="trends-heading">
            <flux:heading id="trends-heading" level="2" size="lg">แนวโน้มรายเดือน</flux:heading>
            <flux:text>6 เดือนจนถึงเดือนที่เลือก แถบทึบคือรายรับ แถบลายคือรายจ่าย ใช้สเกลเดียวกันทุกเดือน</flux:text>
            @foreach ($trends as $trend)
                <div wire:key="trend-{{ $trend['month'] }}" class="space-y-2">
                    <h3 class="text-sm font-bold">{{ $trend['label'] }}</h3>
                    <div class="space-y-1">
                        <div class="flex flex-wrap justify-between gap-1 text-xs"><span>รายรับ</span><x-money :satang="$trend['income']" /></div>
                        <div class="h-3 overflow-hidden rounded-sm bg-zinc-100 dark:bg-zinc-700" aria-hidden="true"><div class="h-full bg-zinc-900 dark:bg-zinc-100" style="width: {{ round($trend['income'] / $maximum * 100, 2) }}%"></div></div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-wrap justify-between gap-1 text-xs"><span>รายจ่าย</span><x-money :satang="$trend['expense']" /></div>
                        <div class="h-3 overflow-hidden rounded-sm bg-zinc-100 dark:bg-zinc-700" aria-hidden="true"><div class="h-full border border-zinc-500 bg-[repeating-linear-gradient(135deg,#737373_0px,#737373_2px,#e5e5e5_2px,#e5e5e5_5px)]" style="width: {{ round($trend['expense'] / $maximum * 100, 2) }}%"></div></div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="space-y-5 rounded-xl border-2 border-zinc-900 p-5 dark:border-zinc-200" aria-labelledby="categories-heading">
            <flux:heading id="categories-heading" level="2" size="lg">ค่าใช้จ่ายแยกตามหมวดหมู่</flux:heading>
            <flux:text>{{ $this->monthLabel }} · เรียงจากยอดมากไปน้อย</flux:text>
            @forelse ($this->expenseCategories as $category)
                @php
                    $percentage = round($category['satang'] / max(1, $summary['monthlyExpense']) * 100, 1);
                @endphp
                <div wire:key="expense-category-{{ $category['id'] }}" class="space-y-2">
                    <div class="flex flex-wrap justify-between gap-2 text-sm"><span class="break-words font-semibold">{{ $category['name'] }}</span><span><x-money :satang="$category['satang']" /> ({{ $percentage }}%)</span></div>
                    <div class="h-3 overflow-hidden rounded-sm bg-zinc-100 dark:bg-zinc-700" aria-hidden="true"><div class="h-full bg-zinc-600 dark:bg-zinc-300" style="width: {{ $percentage }}%"></div></div>
                </div>
            @empty
                <p class="py-10 text-center text-sm">เดือนนี้ยังไม่มีรายจ่าย</p>
            @endforelse
        </section>
    </div>

    <section class="space-y-4" aria-labelledby="recent-heading">
        <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading id="recent-heading" level="2" size="lg">รายการล่าสุด</flux:heading><flux:button :href="route('transactions.index')" wire:navigate>ดูรายการทั้งหมด</flux:button></div>
        <flux:text>5 รายการล่าสุดตามวันที่ทำรายการ จากทุกเดือน</flux:text>
        <div class="overflow-x-auto rounded-xl border border-zinc-300 dark:border-zinc-600">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-100 dark:bg-zinc-900"><tr><th scope="col" class="p-3">วันที่</th><th scope="col" class="p-3">ประเภท / หมวดหมู่</th><th scope="col" class="p-3">รายละเอียด</th><th scope="col" class="p-3 text-right">จำนวนเงิน</th><th scope="col" class="p-3">จัดการ</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->recentTransactions as $transaction)
                        <tr wire:key="recent-{{ $transaction->id }}"><td class="whitespace-nowrap p-3">{{ $transaction->transaction_date->locale('th')->translatedFormat('j M') }} {{ $transaction->transaction_date->year + 543 }}</td><td class="p-3">{{ $transaction->type === 'income' ? 'รายรับ' : 'รายจ่าย' }} / {{ $transaction->category->name }}</td><td class="max-w-xs break-words p-3">{{ $transaction->description ?: '—' }}</td><td class="whitespace-nowrap p-3 text-right"><x-money :satang="$transaction->amount_satang" /></td><td class="p-3"><flux:button size="sm" :href="route('transactions.edit', $transaction)" wire:navigate>แก้ไข</flux:button></td></tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center">ยังไม่มีรายการ เริ่มจดรายรับหรือรายจ่ายแรกของคุณได้เลย</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>

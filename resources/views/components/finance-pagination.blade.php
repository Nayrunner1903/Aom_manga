@props(['paginator'])
<nav aria-label="การแบ่งหน้า" class="flex flex-wrap items-center justify-between gap-3">
    <flux:text>ทั้งหมด {{ $paginator->total() }} รายการ · หน้า {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</flux:text>
    @if ($paginator->hasPages())
        <div class="flex gap-2">
            <flux:button wire:click="previousPage" :disabled="$paginator->onFirstPage()" wire:loading.attr="disabled">ก่อนหน้า</flux:button>
            <flux:button wire:click="nextPage" :disabled="! $paginator->hasMorePages()" wire:loading.attr="disabled">ถัดไป</flux:button>
        </div>
    @endif
</nav>

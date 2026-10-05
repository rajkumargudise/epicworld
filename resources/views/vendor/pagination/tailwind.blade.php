@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3 text-sm">
        @if ($paginator->onFirstPage())
            <span class="chip cursor-not-allowed rounded-full px-5 py-2.5 font-medium opacity-40">&larr; Newer</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="chip rounded-full px-5 py-2.5 font-medium">&larr; Newer</a>
        @endif

        <span class="text-muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="chip rounded-full px-5 py-2.5 font-medium">Older &rarr;</a>
        @else
            <span class="chip cursor-not-allowed rounded-full px-5 py-2.5 font-medium opacity-40">Older &rarr;</span>
        @endif
    </nav>
@endif

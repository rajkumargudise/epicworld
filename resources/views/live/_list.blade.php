{{-- Polled list fragment. Expects $items. --}}
@forelse ($items as $item)
    @include('live._item', ['item' => $item, 'showSummary' => $summary ?? true])
@empty
    <p class="p-4 text-sm text-muted">Fetching the latest headlines&hellip; check back in a moment.</p>
@endforelse

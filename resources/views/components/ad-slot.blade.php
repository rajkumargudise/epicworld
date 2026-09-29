@props(['name', 'context'])

{{--
    Milestone 18: the reusable replacement for the old always-empty
    partials.ad-slot placeholder. Every decision (globally enabled,
    this slot enabled, eligible for $context) is made by
    AdSlotRegistry from config/monetization.php - never here, and
    never inferred from the request.

    The prop is called "name" rather than "slot" - Blade reserves
    $slot for a component's default slot content, so a prop literally
    named "slot" is silently shadowed by that reserved variable and
    never receives the passed-in value.

    While ineligible (the default: MONETIZATION_ENABLED=false),
    this renders nothing at all - no markup, no reserved space, no
    data-* attributes naming the slot. That keeps a disabled slot from
    shifting or altering surrounding editorial content in any way, and
    means nothing here can be mistaken for an active ad by anyone
    inspecting the page source.

    When eligible, this renders a stable, dimensioned, empty container
    only - no provider script, no publisher ID, no external request.
    No ad SDK exists in this codebase yet (see AdSlotRegistry's
    docblock); the reserved min-height simply means a future script
    filling this container won't shift the page once it does.
--}}
@php
    $registry = app(\App\Services\Monetization\AdSlotRegistry::class);
    $eligible = $registry->isEligible($name, $context);
@endphp
@if ($eligible)
    @php
        $dimensions = $registry->dimensions($name);
        $reservedHeight = $dimensions['mobile']['height'] ?? $dimensions['desktop']['height'] ?? null;
    @endphp
    <div
        class="ad-slot"
        data-ad-slot="{{ $name }}"
        data-ad-context="{{ $context }}"
        @if ($reservedHeight) style="min-height: {{ $reservedHeight }}px;" @endif
    ></div>
@endif

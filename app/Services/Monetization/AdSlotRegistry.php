<?php

namespace App\Services\Monetization;

/**
 * The one place that decides whether a named ad placement may render
 * anything at all. Provider-agnostic by design: every public template
 * asks this, through the <x-ad-slot> component, rather than reading
 * config('monetization...') directly or reasoning about a specific
 * provider itself. Nothing here ever knows a publisher ID or loads a
 * provider's script - it only decides eligibility and reserved
 * dimensions, both driven entirely by config/monetization.php.
 */
class AdSlotRegistry
{
    public function isEnabled(): bool
    {
        return (bool) config('monetization.enabled', false);
    }

    /**
     * Which future adapter would serve these slots (e.g.
     * "google-adsense") - never rendered into any view. Null when
     * unset, rather than an empty string, so a caller can check with
     * a simple truthy test.
     */
    public function provider(): ?string
    {
        $provider = config('monetization.provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    public function isLazy(): bool
    {
        return (bool) config('monetization.lazy', true);
    }

    /**
     * True only when monetization is globally enabled, this named slot
     * exists and is itself enabled, and $context is one of the
     * contexts that slot is configured for. $context is always an
     * explicit value a template passes in - this never infers
     * targeting from the request.
     */
    public function isEligible(string $slot, string $context): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $definition = $this->slot($slot);

        if ($definition === null || ! ($definition['enabled'] ?? false)) {
            return false;
        }

        return in_array($context, $definition['context'] ?? [], true);
    }

    /**
     * Reserved width/height per breakpoint for this slot, so a future
     * ad filling it doesn't shift surrounding content. Only meaningful
     * when isEligible() is true - a caller should not reserve this
     * space for an ineligible slot.
     *
     * @return array{desktop?: array{width: int, height: int}, mobile?: array{width: int, height: int}}
     */
    public function dimensions(string $slot): array
    {
        $dimensions = $this->slot($slot)['dimensions'] ?? [];

        return is_array($dimensions) ? $dimensions : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function slot(string $slot): ?array
    {
        $slots = config('monetization.slots', []);

        return is_array($slots[$slot] ?? null) ? $slots[$slot] : null;
    }
}

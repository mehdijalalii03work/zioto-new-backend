---
paths:
  - 'app/Filament/Widgets/Traffic/**'
---

# Traffic

## GA4 widgets must degrade gracefully, never throw
Traffic widgets read GA4 through `App\Services\Analytics\AnalyticsService`, which swallows API failures (logs a warning, returns empty) and exposes `isConfigured()`/`unavailableReason()`. Every widget must gate on `isAnalyticsAvailable()` and render the setup hint instead of throwing — the admin panel must never 500 because Google is unreachable or the service-account key is missing. Also: Filament v5 widgets are lazy by default, so assert widget bodies via `Livewire::test()` and only assert `wire:name` in page-level HTTP assertions.

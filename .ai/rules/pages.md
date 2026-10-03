---
paths:
  - app/Filament/Pages/Dashboard.php
---

# Pages

## Dashboard widgets are registered explicitly, not auto-discovered
`AdminPanelProvider` dropped `discoverWidgets()`; the dashboard widgets are listed explicitly in `->widgets([...])` so report-only widgets never leak onto the dashboard. New widgets go either in that array (dashboard) or in `TrafficReport::getTrafficWidgets()` (report page only). Dashboard and report widgets read their window from the page's `filters` array via `InteractsWithPageFilters`, so both pages must use `HasAnalyticsFiltersForm`.

---
paths:
  - app/Filament/Pages/Dashboard.php
  - app/Filament/Pages/UserMapDashboard.php
---

# Pages

## Dashboard widgets are registered explicitly, not auto-discovered
`AdminPanelProvider` dropped `discoverWidgets()`; the dashboard widgets are listed explicitly in `->widgets([...])` so report-only widgets never leak onto the dashboard. New widgets go either in that array (dashboard) or in `TrafficReport::getTrafficWidgets()` (report page only). Dashboard and report widgets read their window from the page's `filters` array via `InteractsWithPageFilters`, so both pages must use `HasAnalyticsFiltersForm`.

## User map: provinceData is keyed by province slug, not id
$provinceData (and $legendColors) feed the static SVG in resources/views/filament/pages/partials/iran-provinces.blade.php, where every path is bound to a hardcoded `provinces.slug` — the page must key $provinceData by slug or paths render empty. That geometry uses relative linetos (`m x,y l dx,dy ... z`); switching the `m`/`l` to uppercase makes every province collapse onto the map origin. Colours come from getColorForIntensity(), and maxUsers must not be computed with max() on an empty array (no addresses yet => 500).

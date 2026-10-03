---
paths:
  - app/Services/Analytics/AnalyticsService.php
---

# Analytics

## Never report an API rejection as "no data"
`guard()` maps Google `ApiException` statuses to Persian hints stored on `failureReason()` (permission denied / quota / API disabled / property not found). Widgets must read it via `InteractsWithAnalyticsPeriod::emptyStateHeading()/emptyStateDescription()` and show the cause instead of an empty chart — silently swallowing a 403 is what makes "no data" look like a broken property. The service also exposes `lastRecordedVisit()` (14-month lookback) so `TrafficSummaryStats` can distinguish "this window is quiet" from "ANALYTICS_PROPERTY_ID points at a property that stopped receiving traffic".

# Performance budget

The portal uses local, repository-owned assets so page rendering does not depend
on a CDN or a third-party image service. The following limits are checked by
`php tests/run.php test_performance`:

| Measure | Budget | Measurement |
|---|---:|---|
| Rendered PHP page source | 150 KB | UTF-8 bytes of each implemented page/template |
| Images per page | 3 | `<img>` elements in each implemented page |
| Total local image bytes | 25 KB | Files under `clinic-base/assets/img/` |
| SQL calls per request | 10 | A request-level query counter, when enabled |
| Slow query threshold | 250 ms | Logged query duration; no request may exceed it |

## Asset inventory

| Asset | Format | Intrinsic dimensions | Bytes |
|---|---|---:|---:|
| `clinic-base/assets/img/clinic-logo.svg` | SVG | 160 × 160 | 372 |

Images must retain intrinsic `width` and `height`, meaningful `alt` text, and
`loading="lazy"` when they are below the initial viewport. Data needed by a
page is fetched in one model query per collection; loops must not issue one
query per row (N+1).

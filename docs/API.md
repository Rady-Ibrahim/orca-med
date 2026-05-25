# API Reference (v1)

Base URL: `/api/v1`

## Auth

| Method | Path | Auth |
|--------|------|------|
| POST | `/login` | — |
| POST | `/logout` | Bearer |
| GET | `/me` | Bearer |

## Shared (Admin + Company)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/dashboard` | KPIs + charts |
| GET | `/reports/sales` | Paginated |
| GET | `/reports/products` | top / bottom / by_company |
| GET | `/reports/sales/export` | CSV download |
| GET | `/search?q=` | products, pharmacies, suppliers |
| GET | `/provinces`, `/suppliers`, `/pharmacies`, `/products`, `/sales` | Read |
| POST | `/pharmacy-access-requests` | Company only |

**Sensitive data:** append `?product_id=` on pharmacies/suppliers/sales/search to apply masking rules.

## Admin only (`/admin/...`)

- CRUD: provinces, suppliers, pharmacies, products, sales, companies, users
- `POST /admin/sales/import` — Excel file field `file`
- `GET /admin/upload-batches`, `GET /admin/upload-batches/{id}/errors`
- `GET /admin/pharmacy-access-requests`
- `POST /admin/pharmacy-access-requests/{id}/approve|reject`

## Frontend

- `/` — login
- `/app` — dashboard UI

Default users: see `ARCHITECTURE.md`.

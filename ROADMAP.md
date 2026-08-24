# Product roadmap

This document is the authoritative product-phase roadmap for the professional car-rental SaaS back office.

1. **Phase 1 — Global layout and design system**
2. **Phase 2 — Translation and RTL correction**
3. **Phase 3 — Vehicle module redesign**
4. **Phase 4 — Customer and reservation redesign**
5. **Phase 5 — Financial and operational modules**
6. **Phase 6 — Dashboard, reports, responsive and accessibility**

The numbered implementation coverage near the beginning of `IMPLEMENTATION_REPORT.md` describes the historical internal domain build-out. It is retained as implementation history, but it is not the authoritative product roadmap and must not be used to select the next product phase.

Phase 4 is limited to the customer and reservation workspaces, their service and security boundaries, protected customer-document delivery, reservation planning, and required compatibility and verification work. Financial, contract, inspection, incident, notification, and customer-portal visual redesign remain outside Phase 4.

## Phase 5B rental-lifecycle status

- Phase 5B.1 — contract lifecycle foundation: complete
- Phase 5B.2 — acknowledgements and signing: complete
- Phase 5B.5 — protected six-photo inspection bundles: complete
- Phase 5B.3 — checkout and vehicle handover: complete
- Phase 5B.4 — check-in and vehicle return: complete
- Phase 5B.6 — consolidation, EN/FR/AR and RTL verification, security regression, cleanup, and release hardening: complete

The implemented lifecycle is `reservation ready → contract signed → checkout → active rental → return inspection → check-in → completed rental`. Checkout and return require the exact protected front, rear, left, right, interior, and dashboard photo bundle. Checkout/check-in are idempotent and remain authoritative behind role permissions and agency isolation.

Phase 5B is complete. Phase 5C.1, the explicit vehicle-damage case lifecycle foundation, is implemented and awaiting independent review.

Phase 5C.2 and later maintenance, fine, accident, claim, replacement, and related incident workflows remain future work; they are not complete and are not part of Phase 5C.1.

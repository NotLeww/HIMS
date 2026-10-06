# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Hospital employees operate inventory, procurement, receiving, warehousing, supplier governance, administration, reporting, and audit workflows.
- Supplier users operate only within the organization they represent. Supported responsibilities are vendor administration/account management, fulfillment operations, and finance/accounts receivable.

## Product Purpose

HIMS coordinates hospital inventory and supply-chain work using one authoritative set of operational records. The Supplier Portal extends those records to approved external suppliers so hospital and supplier users can complete the same procurement lifecycle without duplicate purchase orders, shipments, receipts, or financial records.

## Positioning

The product connects governed supplier participation directly to hospital procurement, receiving, inventory, compliance, and evidence-based performance while preserving strict supplier-organization isolation and hospital control.

## Operating Context

- Hospital staff create and approve supplier organizations, then invite supplier users. There is no public supplier self-registration.
- Supplier access requires the existing account activation, email verification, authentication, session, and applicable MFA controls.
- Supplier users enter a dedicated workspace and never receive the internal hospital interface.
- The shared workflow covers supplier approval, account activation, RFQs and bids, purchase-order acknowledgement, advance ship notices, receiving discrepancies, invoices and matching, permitted VMI/forecast visibility, and supplier performance.

## Capabilities and Constraints

- Laravel server-rendered web application using the existing User model, RBAC/Gates, session authentication, Blade, Alpine.js, Tailwind CSS, notifications, audit trail, scheduler, and domain services.
- Every supplier-facing query is scoped server-side to the authenticated user's supplier organization.
- Supplier users cannot access patient or clinical information, competitor data, unrelated inventory, internal evaluations, or another supplier's records.
- Supplier-side actions update the same underlying records used by hospital workflows.
- External EDI, GS1 registry/GDSN, banking/payment, and carrier services are not represented as connected without real infrastructure and credentials.
- Supplier-facing lists use backend pagination with 10 records per page by default.

## Brand Commitments

The Supplier Portal remains visibly part of HIMS and inherits its Inter typography, clinical-blue palette, semantic status colors, dense operational hierarchy, shared components, light/dark themes, restrained motion, and accessibility conventions. It is a distinct external workspace, not a generic SaaS redesign.

## Evidence on Hand

- Existing HIMS implementation under the repository root is the architectural and visual authority.
- `HIMS Supplier Workflow Analysis.pdf` supplied by the user is the functional reference for supplier integration.
- Existing transactional data and services are authoritative; the implementation must not fabricate counts, scores, forecasts, external integrations, or payment outcomes.

## Product Principles

1. One authoritative business record across hospital and supplier workflows.
2. Server-enforced least privilege and supplier-tenant isolation.
3. Hospital governance before supplier access or trusted master-data changes.
4. Traceable state transitions, notifications, and append-only audit evidence.
5. Honest integrations and operational states with no simulated enterprise services.

## Accessibility & Inclusion

Target WCAG 2.2 AA, keyboard-operable workflows, visible focus, semantic forms/tables/dialogs, readable status communication that does not rely on color alone, responsive layouts, and reduced-motion support.

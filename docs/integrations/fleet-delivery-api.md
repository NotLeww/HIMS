# Logistics 2 Fleet Delivery API

## Endpoint

`GET /api/v1/integrations/fleet/deliveries`

The collection returns Logistics 1 requisitions explicitly marked for Fleet delivery, with an exact destination receiving point recorded, that have been fully issued and are awaiting department acknowledgment. Department pickup and other arrangements are excluded. It is read-only; the Fleet system cannot issue stock or change HIMS requisition status.

An acknowledged Fleet delivery can still be looked up by its delivery ID with `GET /api/v1/integrations/fleet/deliveries/{deliveryId}`. The detail endpoint returns only Fleet-designated requisitions in `issued` or `acknowledged` state with a destination receiving point; other arrangements, states, and unknown IDs return `404`.

Example: `GET /api/v1/integrations/fleet/deliveries/MR-20261120-0001`

## Authentication

Send the shared secret in the `X-API-Key` header over HTTPS. The HIMS deployment stores only its SHA-256 hexadecimal digest in `FLEET_INTEGRATION_API_KEY_SHA256`. Store the plaintext key in Logistics 2's approved secret manager and transfer it through an approved secure channel. Do not send it in chat, email, source control, or URL query parameters.

The endpoint returns `401` for a missing or invalid key and `503` when the integration has not been configured. Requests are limited to 60 per minute.

## Request

Optional query parameter: `per_page` (1–100; default 25).

```http
GET /api/v1/integrations/fleet/deliveries?per_page=25
X-API-Key: <shared-secret>
Accept: application/json
```

## Response

Uses Laravel pagination fields (`data`, `links`, `current_page`, `last_page`, `per_page`, and `total`). Each `data` entry contains the following fields. Sample values are synthetic; the example requester phone is masked.

```json
{
  "delivery_id": "MR-20261120-0001",
  "status": "ready_for_scheduling",
  "supply_status": "issued",
  "department": "Emergency",
  "delivery_method": "fleet",
  "urgency": "urgent",
  "required_date": "2026-11-25",
  "issued_at": "2026-11-20T02:30:00.000000Z",
  "acknowledged_at": null,
  "pickup_contact": null,
  "destination": {
    "department": "Emergency",
    "location": "Main Building / 3rd Floor / Emergency receiving desk",
    "address": null,
    "contact": {
      "name": "Synthetic Emergency Receiver",
      "phone": null,
      "role": "delivery_recipient"
    }
  },
  "delivery_time_window": {
    "start": "08:00",
    "end": "10:00",
    "timezone": "Asia/Manila"
  },
  "items": [
    {
      "sku": "SUP-001",
      "name": "Sample medical supply",
      "quantity": 12,
      "unit": "piece",
      "pickup_location": "Main Hospital / Ground Floor / Central Warehouse"
    }
  ]
}
```

Complete collection response for one result (synthetic example; pagination URLs use a reserved example host):

```json
{
  "current_page": 1,
  "data": [
    {
      "delivery_id": "MR-20261120-0001",
      "status": "ready_for_scheduling",
      "supply_status": "issued",
      "department": "Emergency",
      "delivery_method": "fleet",
      "urgency": "urgent",
      "required_date": "2026-11-25",
      "issued_at": "2026-11-20T02:30:00.000000Z",
      "acknowledged_at": null,
      "pickup_contact": null,
      "destination": {
        "department": "Emergency",
        "location": "Main Building / 3rd Floor / Emergency receiving desk",
        "address": null,
        "contact": {
          "name": "Synthetic Emergency Receiver",
          "phone": null,
          "role": "delivery_recipient"
        }
      },
      "delivery_time_window": {
        "start": "08:00",
        "end": "10:00",
        "timezone": "Asia/Manila"
      },
      "items": [
        {
          "sku": "SUP-001",
          "name": "Sample medical supply",
          "quantity": 12,
          "unit": "piece",
          "pickup_location": "Main Hospital / Ground Floor / Central Warehouse"
        }
      ]
    }
  ],
  "first_page_url": "https://hims-staging.example/api/v1/integrations/fleet/deliveries?per_page=25&page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "https://hims-staging.example/api/v1/integrations/fleet/deliveries?per_page=25&page=1",
  "links": [
    { "url": null, "label": "Previous", "page": null, "active": false },
    { "url": "https://hims-staging.example/api/v1/integrations/fleet/deliveries?per_page=25&page=1", "label": "1", "page": 1, "active": true },
    { "url": null, "label": "Next", "page": null, "active": false }
  ],
  "next_page_url": null,
  "path": "https://hims-staging.example/api/v1/integrations/fleet/deliveries",
  "per_page": 25,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

The collection includes only requisitions currently in `issued` status without an acknowledgment timestamp. After department acknowledgment, the item disappears from the collection, but remains available from the detail endpoint. The detail response uses `status: "department_acknowledged"`, `supply_status: "acknowledged"`, and a populated `acknowledged_at`. These are HIMS supply-handoff states; Fleet transportation states such as `assigned`, `en_route`, and `completed` remain owned by Fleet.

`delivery_id` is the HIMS requisition number, protected by a database uniqueness constraint and assigned when the requisition is created. Normal partial issuance does not create a new ID: the requisition remains `picking` and is excluded from the Fleet collection until all lines are issued. The current feed therefore represents a fully issued requisition, not separate transport jobs for each partial issue.

New web requisitions record one of `fleet`, `department_pickup`, or `other`. A fleet choice requires a destination location and receiving contact name. The optional off-site address, receiving phone, and preferred time range are stored when supplied. Other arrangements require a short description. The general authenticated requisition API accepts the same fields when supplied for compatibility with existing callers; API-created requisitions without an arrangement remain unclassified and are excluded from this Fleet feed. The feed includes only records marked `fleet` with a destination location, so issued status alone never signals that Fleet should schedule it. Existing rows remain unclassified after migration and must not be presumed Fleet deliveries.

Example detail response after department acknowledgment:

```json
{
  "data": {
    "delivery_id": "MR-20261120-0001",
    "status": "department_acknowledged",
    "supply_status": "acknowledged",
    "department": "Emergency",
    "delivery_method": "fleet",
    "urgency": "urgent",
    "required_date": "2026-11-25",
    "issued_at": "2026-11-20T02:30:00.000000Z",
    "acknowledged_at": "2026-11-20T05:10:00.000000Z",
    "pickup_contact": null,
    "destination": {
      "department": "Emergency",
      "location": "Main Building / 3rd Floor / Emergency receiving desk",
      "address": null,
      "contact": {
        "name": "Synthetic Emergency Receiver",
        "phone": null,
        "role": "delivery_recipient"
      }
    },
    "delivery_time_window": {
      "start": "08:00",
      "end": "10:00",
      "timezone": "Asia/Manila"
    },
    "items": [
      {
        "sku": "SUP-001",
        "name": "Sample medical supply",
        "quantity": 12,
        "unit": "piece",
        "pickup_location": "Main Hospital / Ground Floor / Central Warehouse"
      }
    ]
  }
}
```

Pickup location uses the configured storage-location hierarchy (`parent / child / location`). Destination location, receiving contact, optional address, and optional time window come from requisition details; the time range is in the configured HIMS timezone. If no time window was requested, `delivery_time_window` is `null`.

## Polling and error handling

Poll the collection every 2 minutes, as requested by LOG2, and request each page until `current_page` reaches `last_page`. Honor `Retry-After` when receiving `429`; retry transient `5xx` responses with increasing delays. A `401` requires checking the key configuration, and `503` means HIMS integration configuration is missing. Invalid query parameters return `422`.

LOG2 has proposed a separate Fleet transportation-status API, but it is not live and HIMS has not connected to it:

`GET {LOG2_BASE_URL}/api/v1/integrations/deliveries/transport-status?delivery_id={deliveryId}`

It will use `X-API-Key` and return `delivery_id`, `reservation_number`, `dispatch_number`, `transport_status`, and `delivery_proof_status`. Proposed transport states are `pending_approval`, `approved`, `awaiting_driver`, `assigned`, `en_route`, `arrived`, `completed`, `cancelled`, and `rejected`. HIMS will use the requisition number as `delivery_id`. LOG2 still needs to provide the live staging URL and final response/error contract before HIMS can consume it.

## Deployment configuration

Create a high-entropy shared key, place the plaintext only in the approved secret manager for the Fleet client, and configure its SHA-256 hexadecimal digest as `FLEET_INTEGRATION_API_KEY_SHA256` in the HIMS deployment environment. Rebuild Laravel's configuration cache after setting it. Rotate the key by coordinating a replacement with Logistics 2 and updating both systems together.

The endpoint is not active until a key digest is configured. The production endpoint is `https://supply.djnrmhs.com/api/v1/integrations/fleet/deliveries` (deployment hostname supplied by Logistics 1). Use a separate production key from staging and configure its SHA-256 digest only in the production secret environment. Follow `docs/integrations/fleet-production-launch.md` for the coordinated rollout. Never transfer or store the plaintext key in this repository or in Codex chat.

## Staging smoke-check

1. Configure the key digest in the staging secret store and rebuild the Laravel configuration cache.
2. In the staging HIMS UI, create a synthetic department requisition and issue it through the normal Logistics 1 workflow. Do not create test records in production.
   Existing synthetic Fleet sample records created before the delivery-arrangement migration remain unclassified. Re-run `FleetIntegrationStagingSeeder` in staging to refresh those sample records before checking the feed.
3. Request the endpoint with the staging URL and key. Confirm the delivery ID, department, issued quantities, and pickup location match the staged requisition.
4. Repeat without a key and with an incorrect key; both requests should return `401`. A request when the key digest is unset should return `503`.
5. Acknowledge the requisition in the staging HIMS UI; it should disappear from the collection but remain retrievable from the detail endpoint by delivery ID with `department_acknowledged` status.
6. Confirm pagination using more than one staged delivery and verify the top-level `total` and page fields.

Automated coverage for authentication, filtering, and the response fields lives in `tests/Feature/FleetDeliveryApiTest.php` and uses an isolated test database with synthetic records.
